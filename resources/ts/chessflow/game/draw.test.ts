import { Chess } from 'chess.js';
import { describe, expect, it } from 'vitest';
import { DRAW_COOLDOWN_PLIES, DRAW_MIN_PLIES, judgeDrawOffer, materialBalance } from './draw';

/** A game with `plies` knight shuffles from `fen`, so material stays as given. */
function shuffled(fen: string, plies: number): Chess {
    const g = new Chess(fen);
    const loop = ['Nf3', 'Nf6', 'Ng1', 'Ng8'];
    for (let i = 0; i < plies; i++) g.move(loop[i % 4]);
    return g;
}

const EQUAL = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
const WHITE_UP_ROOK = 'rnbqkbn1/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQq - 0 1';

describe('materialBalance', () => {
    it('is zero at the start and counts a missing rook as 5', () => {
        expect(materialBalance(new Chess(EQUAL))).toBe(0);
        expect(materialBalance(new Chess(WHITE_UP_ROOK))).toBe(5);
    });
});

describe('judgeDrawOffer', () => {
    it('refuses before 20 moves each', () => {
        const v = judgeDrawOffer(shuffled(EQUAL, DRAW_MIN_PLIES - 1), 'w', null);
        expect(v.accept).toBe(false);
        expect(v.message).toContain('Terlalu awal');
    });

    it('accepts an equal position after enough moves', () => {
        expect(judgeDrawOffer(shuffled(EQUAL, DRAW_MIN_PLIES), 'w', null).accept).toBe(true);
    });

    it('refuses when Pak Kuda is ahead on material', () => {
        // The user plays Black, Pak Kuda (White) is a rook up.
        const v = judgeDrawOffer(shuffled(WHITE_UP_ROOK, DRAW_MIN_PLIES), 'b', null);
        expect(v.accept).toBe(false);
        expect(v.message).toContain('5 mata');
    });

    it('accepts gladly when the player is ahead', () => {
        const v = judgeDrawOffer(shuffled(WHITE_UP_ROOK, DRAW_MIN_PLIES), 'w', null);
        expect(v.accept).toBe(true);
        expect(v.message).toContain('mungkin boleh menang');
    });

    it('makes the player wait 10 moves after a refusal', () => {
        const g = shuffled(EQUAL, DRAW_MIN_PLIES + 4);
        expect(judgeDrawOffer(g, 'w', DRAW_MIN_PLIES).message).toContain('10 langkah lagi');
        const later = shuffled(EQUAL, DRAW_MIN_PLIES + DRAW_COOLDOWN_PLIES);
        expect(judgeDrawOffer(later, 'w', DRAW_MIN_PLIES).accept).toBe(true);
    });
});
