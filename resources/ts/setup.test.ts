// Fasa 0 sanity check: toolchain (Vitest + chess.js v1) wired correctly.
// chess.js v1 uses isCheckmate() and move() throws on illegal (see MIGRATION_PLAN §2).
import { describe, it, expect } from 'vitest';
import { Chess } from 'chess.js';

describe('toolchain', () => {
    it('chess.js v1 API is available', () => {
        const game = new Chess();
        expect(typeof game.isCheckmate).toBe('function');
        expect(game.moves().length).toBe(20);
    });

    it("chess.js v1 move() throws on illegal moves", () => {
        const game = new Chess();
        expect(() => game.move('e5')).toThrow();
    });
});
