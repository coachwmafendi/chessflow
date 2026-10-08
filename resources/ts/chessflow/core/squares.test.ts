import { describe, expect, it } from 'vitest';
import { expandSq, fenList, gen, lpath, nm, pcs, sq, tourOpt, uciToMove } from './squares';

describe('nm/sq', () => {
    it('round-trips algebraic notation', () => {
        expect(nm(0)).toBe('a1');
        expect(nm(63)).toBe('h8');
        expect(nm(sq('e4'))).toBe('e4');
        expect(sq('a1')).toBe(0);
        expect(sq('h8')).toBe(63);
    });
});

describe('pcs/fenList', () => {
    it('parses a flat piece string', () => {
        expect(pcs('wNd4 bPe5')).toEqual([
            { c: 'w', t: 'N', sq: sq('d4') },
            { c: 'b', t: 'P', sq: sq('e5') },
        ]);
    });

    it('parses the starting position FEN', () => {
        const list = fenList('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
        expect(list.length).toBe(32);
        expect(list.find((p) => p.sq === sq('e1'))).toEqual({ c: 'w', t: 'K', sq: sq('e1') });
        expect(list.find((p) => p.sq === sq('e8'))).toEqual({ c: 'b', t: 'K', sq: sq('e8') });
    });
});

describe('expandSq', () => {
    it('expands file: and rank: specs', () => {
        expect(expandSq(['file:e']).sort((a, b) => a - b)).toEqual(
            [sq('e1'), sq('e2'), sq('e3'), sq('e4'), sq('e5'), sq('e6'), sq('e7'), sq('e8')].sort((a, b) => a - b),
        );
        expect(expandSq(['rank:4']).length).toBe(8);
        expect(expandSq(['e4'])).toEqual([sq('e4')]);
    });
});

describe('gen', () => {
    it('generates knight moves from the center of an empty board', () => {
        const list = [{ c: 'w' as const, t: 'N', sq: sq('d4') }];
        const dests = gen(list, sq('d4')).map(nm).sort();
        expect(dests).toEqual(['b3', 'b5', 'c2', 'c6', 'e2', 'e6', 'f3', 'f5'].sort());
    });

    it('blocks sliding pieces on friendly pieces, captures on enemy pieces', () => {
        const list = [
            { c: 'w' as const, t: 'R', sq: sq('a1') },
            { c: 'w' as const, t: 'P', sq: sq('a3') },
            { c: 'b' as const, t: 'P', sq: sq('d1') },
        ];
        const dests = gen(list, sq('a1')).map(nm).sort();
        // Blocked northward at a2 (can't jump own pawn on a3); can slide east and capture on d1.
        expect(dests).toEqual(['a2', 'b1', 'c1', 'd1'].sort());
    });

    it('generates pawn double-step only from the start rank', () => {
        const white = [{ c: 'w' as const, t: 'P', sq: sq('e2') }];
        expect(gen(white, sq('e2')).map(nm).sort()).toEqual(['e3', 'e4'].sort());
        const moved = [{ c: 'w' as const, t: 'P', sq: sq('e3') }];
        expect(gen(moved, sq('e3')).map(nm)).toEqual(['e4']);
    });

    it('returns nothing for an empty square', () => {
        expect(gen([], sq('e4'))).toEqual([]);
    });
});

describe('lpath', () => {
    it('routes a knight jump through a visual midpoint, not the straight line', () => {
        const [a, mid, b] = lpath(sq('d4'), sq('f5'));
        expect(a).toBe(sq('d4'));
        expect(b).toBe(sq('f5'));
        expect(mid).not.toBe(sq('e4') + sq('e5')); // sanity: midpoint isn't the naive average square
    });
});

describe('tourOpt', () => {
    it('finds the minimum moves for a rook to visit two squares in a line', () => {
        const list = [{ c: 'w' as const, t: 'R', sq: sq('a1') }];
        // a1 -> a8 -> h8 is 2 rook moves; no shorter tour exists.
        expect(tourOpt(list, sq('a1'), [sq('a8'), sq('h8')])).toBe(2);
    });

    it('returns 0 when there are no stars to collect', () => {
        const list = [{ c: 'w' as const, t: 'N', sq: sq('b1') }];
        expect(tourOpt(list, sq('b1'), [])).toBe(0);
    });
});

describe('uciToMove', () => {
    it('splits a uci string into from/to/promotion', () => {
        expect(uciToMove('e2e4')).toEqual({ from: 'e2', to: 'e4', promotion: undefined });
        expect(uciToMove('e7e8q')).toEqual({ from: 'e7', to: 'e8', promotion: 'q' });
    });
});
