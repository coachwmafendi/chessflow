import { Chess, type Move } from 'chess.js';
import { describe, expect, it } from 'vitest';
import { isDiscovered, isFork, isPinOrSkewer, lessonForMistake } from './patterns';

/** The move `san` from `fen`, verbose. */
const mv = (fen: string, san: string): Move => new Chess(fen).move(san);
const after = (m: Move) => new Chess(m.after);

describe('patterns', () => {
    it('spots a knight fork of king and rook', () => {
        const m = mv('r3k3/8/8/1N6/8/8/8/4K3 w - - 0 1', 'Nc7+');
        expect(isFork(after(m), m.to)).toBe(true);
        expect(isFork(after(mv('r3k3/8/8/1N6/8/8/8/4K3 w - - 0 1', 'Nd4')), 'd4')).toBe(false);
    });

    it('spots a pin and a skewer', () => {
        const pin = mv('4k3/8/2n5/8/8/8/8/4KB2 w - - 0 1', 'Bb5');
        expect(isPinOrSkewer(after(pin), pin.to)).toBe(true);
        const skewer = mv('6q1/8/8/3k4/8/8/8/4KB2 w - - 0 1', 'Bc4+');
        expect(isPinOrSkewer(after(skewer), skewer.to)).toBe(true);
        const nothing = mv('6q1/8/8/3k4/8/8/8/4KB2 w - - 0 1', 'Bd3');
        expect(isPinOrSkewer(after(nothing), nothing.to)).toBe(false);
    });

    it('spots a discovered attack', () => {
        const fen = '4q2k/8/8/8/4N3/8/8/K3R3 w - - 0 1';
        expect(isDiscovered(new Chess(fen), mv(fen, 'Nc5'))).toBe(true);
        expect(isDiscovered(new Chess(fen), mv(fen, 'Kb1'))).toBe(false);
        // a queen lining up on a defended queen is not worth pointing at
        const trade = '3qk3/3p4/8/8/3N4/8/8/K2Q4 w - - 0 1';
        expect(isDiscovered(new Chess(trade), mv(trade, 'Nb5'))).toBe(false);
    });

    it('picks the lesson for a mistake, most telling first', () => {
        const scholar = 'r1bqkbnr/pppp1ppp/2n5/4p2Q/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 3 3';
        const played = mv(scholar, 'Nf6');
        expect(lessonForMistake({ played, best: mv(scholar, 'Qe7'), reply: mv(played.after, 'Qxf7#'), mateBefore: null, mateAfter: 1 })).toBe('scholar');

        const fork = 'r3k3/8/8/1N6/8/8/8/4K3 w - - 0 1';
        expect(lessonForMistake({ played: mv(fork, 'Nd4'), best: mv(fork, 'Nc7+'), reply: null, mateBefore: null, mateAfter: null })).toBe('fork');

        const mate = '6k1/5ppp/8/8/8/8/8/R5K1 w - - 0 1';
        expect(lessonForMistake({ played: mv(mate, 'Kf1'), best: mv(mate, 'Ra8#'), reply: null, mateBefore: 1, mateAfter: null })).toBe('sah');

        const hang = '4k3/8/8/3q4/8/8/3Q4/4K3 w - - 0 1';
        const blunder = mv(hang, 'Qd4');
        expect(lessonForMistake({ played: blunder, best: mv(hang, 'Qe2+'), reply: mv(blunder.after, 'Qxd4'), mateBefore: null, mateAfter: null })).toBe('cct');

        // Pak Kuda's reply wins a piece: that is the lesson, even if the better move had a discovered attack
        const both = '4q2k/8/8/8/4N3/8/3B4/K3R2r w - - 0 1';
        const left = mv(both, 'Kb2');
        expect(lessonForMistake({ played: left, best: mv(both, 'Nc5'), reply: mv(left.after, 'Rxe1'), mateBefore: null, mateAfter: null })).toBe('cct');

        const quiet = '4k3/8/8/8/8/8/3P4/4K3 w - - 0 1';
        expect(lessonForMistake({ played: mv(quiet, 'd3'), best: mv(quiet, 'd4'), reply: null, mateBefore: null, mateAfter: null })).toBeNull();
    });
});
