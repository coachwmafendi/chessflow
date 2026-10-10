import { Chess } from 'chess.js';
import { describe, expect, it } from 'vitest';
import { evaluatePuzzleAcceptance, oppHasMate } from './puzzle';
import type { PuzzleStep } from '../types';

const base: Omit<PuzzleStep, 'type' | 'title'> = {};
const step = (patch: Partial<PuzzleStep>): PuzzleStep => ({ type: 'puzzle', title: 't', ...base, ...patch });

describe('oppHasMate', () => {
    it('is false from the starting position (no mate-in-one exists)', () => {
        expect(oppHasMate(new Chess())).toBe(false);
    });

    it("is true when the side to move has fool's mate available", () => {
        // 1. f3 e5 2. g4 — black to move, Qh4# is available.
        const g = new Chess('rnbqkbnr/pppp1ppp/8/4p3/6P1/5P2/PPPPP2P/RNBQKBNR b KQkq - 0 2');
        expect(oppHasMate(g)).toBe(true);
    });
});

describe('evaluatePuzzleAcceptance — promotion piece', () => {
    const st = { type: 'puzzle', title: 'Promosi!', fen: '8/1P2k3/8/8/8/8/8/4K3 w - - 0 1', line: ['b7b8q'] } as PuzzleStep;
    const play = (promotion: string) => {
        const g = new Chess(st.fen);
        const m = g.move({ from: 'b7', to: 'b8', promotion });
        return evaluatePuzzleAcceptance(st, 0, m, g);
    };

    it('accepts the piece the line asks for', () => expect(play('q')).toBe(true));
    it('rejects another piece on the right square', () => {
        expect(play('n')).toBe(false);
        expect(play('r')).toBe(false);
    });
    it('reads a four-letter promoting line move as a queen', () => {
        const short = { ...st, line: ['b7b8'] } as PuzzleStep;
        const g = new Chess(st.fen);
        expect(evaluatePuzzleAcceptance(short, 0, g.move({ from: 'b7', to: 'b8', promotion: 'q' }), g)).toBe(true);
        const h = new Chess(st.fen);
        expect(evaluatePuzzleAcceptance(short, 0, h.move({ from: 'b7', to: 'b8', promotion: 'b' }), h)).toBe(false);
    });
});

describe('evaluatePuzzleAcceptance — line', () => {
    it('accepts the exact uci move at the current ply', () => {
        const g = new Chess();
        const res = g.move('e4');
        const st = step({ line: ['e2e4', 'e7e5'] });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(true);
    });

    it('rejects a different move at the current ply', () => {
        const g = new Chess();
        const res = g.move('d4');
        const st = step({ line: ['e2e4', 'e7e5'] });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(false);
    });

    it('accepts an alternate SAN listed in alts for that ply', () => {
        const g = new Chess();
        const res = g.move('d4'); // not the expected e2e4, but listed as an alt
        const st = step({ line: ['e2e4', 'e7e5'], alts: { 0: ['d4'] } });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(true);
    });

    it('falls back to acceptSan only on ply 0', () => {
        const g = new Chess();
        const res = g.move('Nf3');
        const st = step({ line: ['e2e4', 'e7e5'], acceptSan: ['Nf3'] });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(true);
    });

    it('does not fall back to acceptSan on later plies', () => {
        const g = new Chess();
        g.move('e4');
        const res = g.move('Nf6'); // ply 1, not matching line[1]='e7e5'
        const st = step({ line: ['e2e4', 'e7e5'], acceptSan: ['Nf6'] });
        expect(evaluatePuzzleAcceptance(st, 1, res, g)).toBe(false);
    });
});

describe('evaluatePuzzleAcceptance — acceptSan (no line)', () => {
    it('accepts any move whose SAN is listed', () => {
        const g = new Chess();
        const res = g.move('c4');
        const st = step({ acceptSan: ['c4', 'd4'] });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(true);
    });

    it('rejects a move whose SAN is not listed', () => {
        const g = new Chess();
        const res = g.move('a3');
        const st = step({ acceptSan: ['c4', 'd4'] });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(false);
    });
});

describe('evaluatePuzzleAcceptance — acceptMate', () => {
    it('accepts a move that delivers checkmate', () => {
        const g = new Chess();
        ['f3', 'e5', 'g4'].forEach((m) => g.move(m));
        const res = g.move('Qh4#');
        expect(g.isCheckmate()).toBe(true);
        const st = step({ acceptMate: true });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(true);
    });

    it('rejects a non-mating move', () => {
        const g = new Chess();
        ['f3', 'e5'].forEach((m) => g.move(m));
        const res = g.move('g3'); // quiet move, no mate
        const st = step({ acceptMate: true });
        expect(evaluatePuzzleAcceptance(st, 0, res, g)).toBe(false);
    });
});

describe('evaluatePuzzleAcceptance — accept constraints', () => {
    it('enforces piece/notPiece/to/toFile/capture', () => {
        const g = new Chess();
        const res = g.move('Nf3'); // knight move to f3
        expect(evaluatePuzzleAcceptance(step({ accept: { piece: 'n' } }), 0, res, g)).toBe(true);
        expect(evaluatePuzzleAcceptance(step({ accept: { piece: 'p' } }), 0, res, g)).toBe(false);
        expect(evaluatePuzzleAcceptance(step({ accept: { notPiece: 'n' } }), 0, res, g)).toBe(false);
        expect(evaluatePuzzleAcceptance(step({ accept: { to: ['f3'] } }), 0, res, g)).toBe(true);
        expect(evaluatePuzzleAcceptance(step({ accept: { to: ['f6'] } }), 0, res, g)).toBe(false);
        expect(evaluatePuzzleAcceptance(step({ accept: { toFile: ['f'] } }), 0, res, g)).toBe(true);
        expect(evaluatePuzzleAcceptance(step({ accept: { toFile: ['e'] } }), 0, res, g)).toBe(false);
        expect(evaluatePuzzleAcceptance(step({ accept: { capture: true } }), 0, res, g)).toBe(false);
    });

    it('accepts a capturing move when accept.capture is required', () => {
        const g = new Chess('rnbqkbnr/ppp1pppp/8/3p4/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 2');
        const res = g.move('exd5'); // pawn captures on d5
        expect(evaluatePuzzleAcceptance(step({ accept: { capture: true } }), 0, res, g)).toBe(true);
    });

    it('rejects a move that hangs mate-in-one when noMateInOne is set', () => {
        // After 1. f3 e5 2. g4, black has Qh4# available — any white "result" that
        // leads to this position must be rejected when noMateInOne guards against it.
        const g = new Chess('rnbqkbnr/pppp1ppp/8/4p3/6P1/5P2/PPPPP2P/RNBQKBNR w KQkq - 0 2');
        const res = g.move('Nc3'); // a quiet developing move that leaves Qh4# on the board
        expect(evaluatePuzzleAcceptance(step({ accept: { noMateInOne: true } }), 0, res, g)).toBe(false);
    });

    it('accepts a move with noMateInOne when no mate-in-one is left for the opponent', () => {
        const g = new Chess();
        const res = g.move('e4');
        expect(evaluatePuzzleAcceptance(step({ accept: { noMateInOne: true } }), 0, res, g)).toBe(true);
    });
});
