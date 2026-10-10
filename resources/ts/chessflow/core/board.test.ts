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
    const cls = (name: string) => (root.querySelector(`[data-sq="${sq(name)}"]`) as HTMLElement).classList;

    it('applyMove() highlights the last move and clears the previous one', () => {
        const b = createBoard(root);
        b.setFen('4k3/8/8/8/8/8/4P3/4K3 w - - 0 1');
        b.applyMove({ from: 'e2', to: 'e4', flags: 'b', san: 'e4' });
        expect(cls('e2').contains('last')).toBe(true);
        expect(cls('e4').contains('last')).toBe(true);
        b.applyMove({ from: 'e8', to: 'd8', flags: 'n', san: 'Kd8' });
        expect(cls('e2').contains('last')).toBe(false);
        expect(cls('d8').contains('last')).toBe(true);
    });

    it('applyMove() lights up the king that is put in check, and clears it after the reply', () => {
        const b = createBoard(root);
        b.setFen('4k3/8/8/8/8/8/8/R3K3 w - - 0 1');
        b.applyMove({ from: 'a1', to: 'a8', flags: 'n', san: 'Ra8+' });
        expect(cls('e8').contains('check')).toBe(true);
        b.applyMove({ from: 'e8', to: 'e7', flags: 'n', san: 'Ke7' });
        expect(root.querySelectorAll('.sq.check').length).toBe(0);
    });

    it('draws capture targets as rings and empty targets as dots', () => {
        const b = createBoard(root);
        b.set([
            { c: 'w', t: 'N', sq: sq('d4') },
            { c: 'b', t: 'P', sq: sq('f5') },
        ]);
        b.mark({ dots: [sq('f5'), sq('e6')] });
        expect(cls('f5').contains('cap')).toBe(true);
        expect(cls('f5').contains('dot')).toBe(false);
        expect(cls('e6').contains('dot')).toBe(true);
    });

    it('set() clears last-move and check marks', () => {
        const b = createBoard(root);
        b.setFen('4k3/8/8/8/8/8/8/R3K3 w - - 0 1');
        b.applyMove({ from: 'a1', to: 'a8', flags: 'n', san: 'Ra8+' });
        b.setFen('4k3/8/8/8/8/8/8/R3K3 w - - 0 1');
        expect(root.querySelectorAll('.sq.last, .sq.check').length).toBe(0);
    });
    // jsdom has no layout: give the grid an 800x800 box at (0,0), so each square is 100px.
    const sized = (b: ReturnType<typeof createBoard>) => {
        const grid = root.querySelector('.grid') as HTMLElement;
        grid.getBoundingClientRect = () => ({ left: 0, top: 0, width: 800, height: 800, right: 800, bottom: 800, x: 0, y: 0, toJSON: () => ({}) }) as DOMRect;
        return { b, grid };
    };
    const pointer = (el: Element, type: string, x: number, y: number) => {
        const e = new MouseEvent(type, { bubbles: true, clientX: x, clientY: y, button: 0 });
        Object.defineProperty(e, 'pointerId', { value: 1 });
        el.dispatchEvent(e);
    };
    // Centre of a square for White's orientation: file a..h → x, rank 8..1 → y.
    const at = (name: string): [number, number] => [(name.charCodeAt(0) - 97) * 100 + 50, (8 - Number(name[1])) * 100 + 50];

    it('reports a drag from A to B as a tap on A then a tap on B', () => {
        const { b, grid } = sized(createBoard(root));
        b.set([{ c: 'w', t: 'N', sq: sq('g1') }]);
        const taps: number[] = [];
        b.on((i) => taps.push(i));
        b.draggable(() => true);

        const sqEl = root.querySelector(`[data-sq="${sq('g1')}"]`) as HTMLElement;
        pointer(sqEl, 'pointerdown', ...at('g1'));
        pointer(grid, 'pointermove', ...at('g2'));
        pointer(grid, 'pointermove', ...at('f3'));
        expect(root.querySelector('.piece.dragging')).not.toBeNull();
        expect(cls('f3').contains('drag-over')).toBe(true);
        pointer(grid, 'pointerup', ...at('f3'));

        expect(taps).toEqual([sq('g1'), sq('f3')]);
        expect(root.querySelector('.piece.dragging')).toBeNull();
    });

    it('leaves taps alone and only drags when enabled for that piece', () => {
        const { b, grid } = sized(createBoard(root));
        b.set([{ c: 'w', t: 'N', sq: sq('g1') }, { c: 'b', t: 'P', sq: sq('e5') }]);
        const taps: number[] = [];
        b.on((i) => taps.push(i));

        const g1 = root.querySelector(`[data-sq="${sq('g1')}"]`) as HTMLElement;
        pointer(g1, 'pointerdown', ...at('g1'));
        pointer(grid, 'pointermove', ...at('f3'));
        pointer(grid, 'pointerup', ...at('f3'));
        expect(taps).toEqual([]); // dragging is off by default

        b.draggable((i) => i === sq('g1'));
        const e5 = root.querySelector(`[data-sq="${sq('e5')}"]`) as HTMLElement;
        pointer(e5, 'pointerdown', ...at('e5'));
        pointer(grid, 'pointermove', ...at('e4'));
        pointer(grid, 'pointerup', ...at('e4'));
        expect(taps).toEqual([]); // not a draggable piece

        pointer(g1, 'pointerdown', ...at('g1'));
        pointer(g1, 'pointerup', ...at('g1')); // no movement: stays a tap (the click event)
        expect(taps).toEqual([]);
    });

    it('asks which piece to promote to, and can be cancelled', async () => {
        const b = createBoard(root);
        const first = b.choosePromotion('w');
        const opts = [...root.querySelectorAll<HTMLElement>('.promo [data-p]')];
        expect(opts.map((o) => o.textContent)).toEqual(['Menteri', 'Tir', 'Gajah', 'Kuda']);
        expect(root.querySelector('.promo .pc.wN')).not.toBeNull();
        opts[3].click();
        await expect(first).resolves.toBe('n');
        expect(root.querySelector('.promo')).toBeNull();

        const second = b.choosePromotion('b');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await expect(second).resolves.toBeNull();
    });
});
