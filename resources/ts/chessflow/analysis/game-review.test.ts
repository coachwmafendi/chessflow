import { Chess } from 'chess.js';
import { describe, expect, it } from 'vitest';
import type { Evaluation } from '../engine/stockfish';
import { evalGraphSvg, reviewGame, whiteScore } from './game-review';

const ev = (cp: number | null, best: string | null = null, mate: number | null = null): Evaluation => ({ cp, mate, best });

describe('game review', () => {
    it('turns side-to-move scores into White scores, mates at the edge', () => {
        expect(whiteScore(ev(120), 'x w - - 0 1')).toBe(120);
        expect(whiteScore(ev(120), 'x b - - 0 1')).toBe(-120);
        expect(whiteScore(ev(null, null, 2), 'x b - - 0 1')).toBe(-1000);
        expect(whiteScore(ev(null, null, 0), 'x b - - 0 1')).toBe(1000); // Black to move is mated
        expect(whiteScore(null, 'x w - - 0 1')).toBeNull();
    });

    it('evaluates each position once and finds mistakes for both sides, naming the opponent neutrally', async () => {
        const g = new Chess();
        ['e4', 'e5', 'Qh5', 'Nc6', 'Qxf7+', 'Kxf7'].forEach((m) => g.move(m));
        const moves = g.history({ verbose: true });
        const scores = new Map<string, Evaluation>([[moves[4].before, ev(20, 'g1f3')], [moves[4].after, ev(800, 'e8f7')]]);
        let calls = 0;
        const progress: number[] = [];
        const r = await reviewGame(moves, async (fen) => (calls++, scores.get(fen) ?? ev(0, null)), (d) => progress.push(d));
        expect(calls).toBe(7); // start + 6 moves, each once
        expect(progress.at(-1)).toBe(1);
        expect(r!.scores).toHaveLength(7);
        expect(r!.mistakes.w.map((m) => m.san)).toEqual(['Qxf7+']);
        expect(r!.mistakes.w[0].text).toBe('Selepas Qxf7+, lawan boleh makan Menteri dengan Kxf7.');
        expect(r!.mistakes.b).toEqual([]);
    });

    it('can be stopped', async () => {
        const g = new Chess();
        g.move('e4');
        expect(await reviewGame(g.history({ verbose: true }), async () => ev(0), () => {}, () => true)).toBeNull();
    });

    it('draws the graph with the current move and the mistakes marked', () => {
        const svg = evalGraphSvg([0, 50, -200, null, 1000], 2, [1]);
        expect(svg).toContain('class="eval-graph"');
        expect(svg).toContain('class="eg-now" x1="300.0"');
        expect(svg.match(/eg-mistake/g)).toHaveLength(1);
    });
});
