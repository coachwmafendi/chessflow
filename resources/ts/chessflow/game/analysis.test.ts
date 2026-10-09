import { Chess } from 'chess.js';
import { describe, expect, it } from 'vitest';
import type { Evaluation } from '../engine/stockfish';
import { analyseGame, scoreFor } from './analysis';

/** Plays `sans` from `fen` and returns the verbose moves plus every position (before ply i). */
function game(sans: string[], fen?: string) {
    const g = fen ? new Chess(fen) : new Chess();
    sans.forEach((s) => g.move(s));
    const moves = g.history({ verbose: true });
    return { moves, fens: [...moves.map((m) => m.before), moves[moves.length - 1].after] };
}

/** Fake engine: a fixed answer per position. */
const engine = (table: Map<string, Evaluation>) => async (fen: string) => table.get(fen) ?? null;
const ev = (cp: number | null, best: string | null = null, mate: number | null = null): Evaluation => ({ cp, mate, best });

describe('scoreFor', () => {
    it('turns the side-to-move score into the player view and clamps mates', () => {
        expect(scoreFor(ev(120), 'w', 'w')).toBe(120);
        expect(scoreFor(ev(120), 'b', 'w')).toBe(-120);
        expect(scoreFor(ev(null, null, 3), 'w', 'w')).toBe(1500);
        expect(scoreFor(ev(null, null, 0), 'b', 'w')).toBe(1500); // black to move is mated
        expect(scoreFor(ev(9000), 'w', 'w')).toBe(1500);
        expect(scoreFor(ev(null), 'w', 'w')).toBeNull();
    });
});

describe('analyseGame', () => {
    it('finds a hung queen and says who can take it', async () => {
        const { moves, fens } = game(['e4', 'e5', 'Qh5', 'Nc6', 'Qxf7+', 'Kxf7']);
        const t = new Map<string, Evaluation>([
            [fens[0], ev(30, 'e2e4')],
            [fens[1], ev(-30, 'e7e5')],
            [fens[2], ev(30, 'g1f3')],
            [fens[3], ev(-10, 'b8c6')],
            [fens[4], ev(20, 'g1f3')],
            [fens[5], ev(800, 'e8f7')],
        ]);
        const out = await analyseGame(moves, 'w', engine(t));
        expect(out).toHaveLength(1);
        expect(out![0]).toMatchObject({ moveNo: 3, san: 'Qxf7+', severity: 'besar', best: { san: 'Nf3' } });
        expect(out![0].text).toBe('Selepas Qxf7+, Pak Kuda boleh makan Menteri awak dengan Kxf7.');
    });

    it('points out a missed mate in one', async () => {
        const { moves, fens } = game(['Kf1'], '6k1/5ppp/8/8/8/8/8/R5K1 w - - 0 1');
        const t = new Map<string, Evaluation>([
            [fens[0], ev(null, 'a1a8', 1)],
            [fens[1], ev(-500, 'g8f8')],
        ]);
        const out = await analyseGame(moves, 'w', engine(t));
        expect(out![0].text).toBe('Ada sah mati! Cuba Ra8#.');
    });

    it('does not blame a big drop on a missed pawn', async () => {
        // 1.e4 d5: taking on d5 was best, but losing 4 points is about more than that pawn.
        const { moves, fens } = game(['e4', 'd5', 'Qh5']);
        const t = new Map<string, Evaluation>([
            [fens[0], ev(30, 'e2e4')],
            [fens[1], ev(-30, 'd7d5')],
            [fens[2], ev(60, 'e4d5')],
            [fens[3], ev(350, 'g8f6')],
        ]);
        const out = await analyseGame(moves, 'w', engine(t));
        expect(out![0].text).toBe('Langkah yang lebih kuat ialah exd5.');
    });

    it('does not flag a different mate than the engine chose', async () => {
        // The engine names another (slower) mating line; the player mates at once with Rb8#.
        const { moves, fens } = game(['Rb8#'], '6k1/5ppp/8/8/8/8/8/1R4K1 w - - 0 1');
        const t = new Map<string, Evaluation>([
            [fens[0], ev(null, 'b1a1', 2)],
            [fens[1], ev(null, null, 0)],
        ]);
        expect(await analyseGame(moves, 'w', engine(t))).toEqual([]);
    });

    it('stays quiet about a well-played game or a still-winning position', async () => {
        const { moves, fens } = game(['e4', 'e5', 'Nf3']);
        const t = new Map<string, Evaluation>([
            [fens[0], ev(900, 'e2e4')],
            [fens[1], ev(-880, 'e7e5')],
            [fens[2], ev(900, 'g1f3')],
            [fens[3], ev(-500, 'b8c6')], // dropped 400 but still +500: not worth a remark
        ]);
        expect(await analyseGame(moves, 'w', engine(t))).toEqual([]);
    });

    it('only looks at the player\'s own moves (Black here)', async () => {
        const { moves, fens } = game(['e4', 'f6', 'd4', 'g5', 'Qh5#']);
        const t = new Map<string, Evaluation>(fens.map((f) => [f, ev(0, null)]));
        t.set(fens[3], ev(-20, 'e7e6')); // black to move, slightly worse
        t.set(fens[4], ev(null, 'd1h5', 1)); // after g5: White mates in one
        const out = await analyseGame(moves, 'b', engine(t));
        expect(out).toHaveLength(1);
        expect(out![0]).toMatchObject({ san: 'g5', moveNo: 2 });
        expect(out![0].text).toBe('Langkah ini beri Pak Kuda peluang sah mati, bermula dengan Qh5#.');
    });

    it('reports progress and can be stopped', async () => {
        const { moves, fens } = game(['e4', 'e5', 'Nf3', 'Nc6']);
        const t = new Map<string, Evaluation>(fens.map((f) => [f, ev(0, null)]));
        const seen: number[] = [];
        await analyseGame(moves, 'w', engine(t), (p) => seen.push(p));
        expect(seen[seen.length - 1]).toBe(1);
        expect(await analyseGame(moves, 'w', engine(t), () => {}, () => true)).toBeNull();
    });
});
