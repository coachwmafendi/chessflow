// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest';
import { createBoard } from './board';
import { sq } from './squares';

describe('Board', () => {
    let root: HTMLDivElement;

    beforeEach(() => {
        root = document.createElement('div');
    });

    it('renders an 8x8 grid of squares', () => {
        createBoard(root);
        expect(root.querySelectorAll('.sq').length).toBe(64);
    });

    it('places pieces from setFen and exposes them via all()/at()', () => {
        const b = createBoard(root);
        b.setFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
        expect(b.all().length).toBe(32);
        expect(b.at(sq('e1'))).toMatchObject({ c: 'w', t: 'K' });
        expect(b.at(sq('e4'))).toBeNull();
    });

    it('move() relocates a piece and captures what was on the destination', () => {
        const b = createBoard(root);
        b.set([
            { c: 'w', t: 'N', sq: sq('d4') },
            { c: 'b', t: 'P', sq: sq('f5') },
        ]);
        b.move(sq('d4'), sq('f5'));
        expect(b.at(sq('d4'))).toBeNull();
        expect(b.at(sq('f5'))).toMatchObject({ c: 'w', t: 'N' });
        expect(b.all().length).toBe(1);
    });

    it('applyMove() handles en-passant capture via the "e" flag', () => {
        const b = createBoard(root);
        b.set([
            { c: 'w', t: 'P', sq: sq('e5') },
            { c: 'b', t: 'P', sq: sq('d5') },
        ]);
        b.applyMove({ from: 'e5', to: 'd6', flags: 'e' });
        expect(b.at(sq('d5'))).toBeNull(); // captured pawn removed
        expect(b.at(sq('d6'))).toMatchObject({ c: 'w', t: 'P' });
    });

    it('applyMove() handles kingside castling via the "k" flag', () => {
        const b = createBoard(root);
        b.set([
            { c: 'w', t: 'K', sq: sq('e1') },
            { c: 'w', t: 'R', sq: sq('h1') },
        ]);
        b.applyMove({ from: 'e1', to: 'g1', flags: 'k' });
        expect(b.at(sq('g1'))).toMatchObject({ c: 'w', t: 'K' });
        expect(b.at(sq('f1'))).toMatchObject({ c: 'w', t: 'R' });
        expect(b.at(sq('h1'))).toBeNull();
    });

    it('mark() toggles CSS classes for dots/good/ring/sel', () => {
        const b = createBoard(root);
        b.set([{ c: 'w', t: 'N', sq: sq('d4') }]);
        b.mark({ sel: 'd4', dots: ['e6', 'f5'] });
        const sqEl = (name: string) => root.querySelector(`[data-sq="${sq(name)}"]`) as HTMLElement;
        expect(sqEl('d4').classList.contains('sel')).toBe(true);
        expect(sqEl('e6').classList.contains('dot')).toBe(true);
        expect(sqEl('f5').classList.contains('dot')).toBe(true);
        expect(sqEl('a1').classList.contains('dot')).toBe(false);
    });

    it('on() registers a click handler invoked with the square index', () => {
        const b = createBoard(root);
        let clicked = -1;
        b.on((i) => (clicked = i));
        const sqEl = root.querySelector(`[data-sq="${sq('e4')}"]`) as HTMLElement;
        sqEl.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(clicked).toBe(sq('e4'));
    });
});
