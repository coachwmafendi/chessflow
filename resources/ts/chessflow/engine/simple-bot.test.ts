import { Chess } from 'chess.js';
import { describe, expect, it } from 'vitest';
import { bot } from './simple-bot';

describe('bot', () => {
    it('returns null when there are no legal moves (checkmate/stalemate)', () => {
        // Fool's mate position: black has no legal moves.
        const g = new Chess('rnb1kbnr/pppp1ppp/8/4p3/6Pq/5P2/PPPPP2P/RNBQKBNR w KQkq - 1 3');
        expect(g.isCheckmate()).toBe(true);
        expect(bot(g, 'flee', 'b')).toBeNull();
    });

    it('returns a legal move for the side to move', () => {
        const g = new Chess();
        const move = bot(g, 'defend', 'w');
        expect(move).not.toBeNull();
        const legalUci = g.moves({ verbose: true }).map((m) => m.from + m.to);
        expect(legalUci).toContain(move!.from + move!.to);
    });

    it('does not mutate the board position (always undoes trial moves)', () => {
        const g = new Chess();
        const before = g.fen();
        bot(g, 'chase', 'b');
        expect(g.fen()).toBe(before);
    });

    it('"flee" avoids moves that hang the queen for free when a safe move exists', () => {
        // White queen on d4 is attacked by a black pawn on e5; d-file and other squares are safe.
        const g = new Chess('4k3/8/8/4p3/3Q4/8/8/4K3 w - - 0 1');
        const move = bot(g, 'flee', 'b', 0);
        expect(move).not.toBeNull();
        // Moving the queen off d4 (away from the pawn's attack) should score better than leaving it there.
        g.move(move!);
        const stillOnD4 = g.board().some((row) => row.some((sqr) => sqr && sqr.type === 'q' && sqr.square === 'd4'));
        expect(stillOnD4).toBe(false);
    });
});
