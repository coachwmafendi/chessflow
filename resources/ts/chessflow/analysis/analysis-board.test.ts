// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AnalysisRunner, formatLine, formatScore, toWhite, verdict } from './analysis-board';
import type { Evaluation, StockfishEngine } from '../engine/stockfish';

function fakeEngine(ev: Evaluation | ((fen: string) => Evaluation)): StockfishEngine & { calls: string[] } {
    const calls: string[] = [];
    return {
        available: true,
        calls,
        analyse: ({ fen }: { fen: string }) => {
            calls.push(fen);
            return Promise.resolve(typeof ev === 'function' ? ev(fen) : ev);
        },
        bestMove: () => Promise.resolve(null),
        destroy: () => {},
    } as unknown as StockfishEngine & { calls: string[] };
}

const flush = () => vi.advanceTimersByTimeAsync(400);
const text = (root: HTMLElement, sel: string) => root.querySelector(sel)?.textContent ?? '';
const click = (root: HTMLElement, sel: string) => (root.querySelector(sel) as HTMLElement).click();
type Internals = { applyInput(raw: string, sync: boolean): boolean };

describe('score helpers', () => {
    it('turns side-to-move scores into White scores', () => {
        expect(toWhite({ cp: -1300, mate: null }, 'b')).toEqual({ cp: 1300, mate: null });
        expect(toWhite({ cp: null, mate: 2 }, 'b')).toEqual({ cp: null, mate: -2 });
        expect(formatScore({ cp: 1300, mate: null })).toBe('+13.0');
        expect(formatScore({ cp: null, mate: -2 })).toBe('#-2');
    });

    it('describes the score in words', () => {
        expect(verdict({ cp: 20, mate: null })).toContain('Seimbang');
        expect(verdict({ cp: -200, mate: null })).toBe('Hitam lebih baik.');
        expect(verdict({ cp: 900, mate: null })).toBe('Putih jauh lebih baik.');
        expect(verdict({ cp: null, mate: 3 })).toBe('Putih boleh sah mati dalam 3 langkah.');
    });

    it('numbers engine lines from the position, also when Black starts', () => {
        const fen = 'rnb1kbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 3';
        expect(formatLine(fen, ['b8c6', 'b1c3', 'g8f6'], 6)).toBe('3… Nc6 4. Nc3 Nf6');
        expect(formatLine('4k3/P7/8/8/8/8/8/4K3 w - - 0 50', ['a7a8q'], 6)).toBe('50. a8=Q+');
    });
});

describe('AnalysisRunner', () => {
    let root: HTMLDivElement;
    let runner: AnalysisRunner | null = null;

    beforeEach(() => {
        vi.useFakeTimers();
        root = document.createElement('div');
        document.body.appendChild(root);
        history.replaceState(null, '', '/analisis');
    });

    afterEach(() => {
        runner?.destroy();
        runner = null;
        root.remove();
        vi.useRealTimers();
    });

    it('shows the score from White’s side when Black is to move', async () => {
        root.dataset.fen = 'rnb1kbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 3';
        runner = new AnalysisRunner(root, fakeEngine({ cp: -1310, mate: null, best: 'b8c6', lines: [{ pv: ['b8c6', 'b1c3'], cp: -1310, mate: null }] }));
        await flush();
        expect(root.querySelectorAll('.sq').length).toBe(64);
        expect(text(root, '.eval-score')).toContain('+13.1');
        expect(text(root, '.eval-verdict')).toBe('Putih jauh lebih baik.');
        expect(text(root, '.eval-best')).toContain('Nc6');
        expect((root.querySelector('.eval-white') as HTMLElement).style.width).toBe('100%');
        expect(text(root, '.engine-lines')).toContain('3… Nc6 4. Nc3');
    });

    it('plays the best move and a pressed engine line', async () => {
        runner = new AnalysisRunner(root, fakeEngine((fen) => (fen.includes(' w ') ? { cp: 30, mate: null, best: 'e2e4', lines: [{ pv: ['d2d4'], cp: 20, mate: null }] } : { cp: -30, mate: null, best: 'e7e5', lines: [{ pv: ['c7c5'], cp: -20, mate: null }] })));
        await flush();
        click(root, '[data-act="best"]');
        await flush();
        click(root, '.engine-lines [data-line="0"]');
        expect(new URL(location.href).searchParams.get('moves')).toBe('e4 c5');
    });

    it('steps through moves without losing them, and a new move replaces the rest', async () => {
        root.dataset.moves = 'e4 e5 Nf3 Nc6';
        runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: 'g1f3' }));
        await flush();
        expect(root.querySelectorAll('.move-btn').length).toBe(4);
        click(root, '[data-act="first"]');
        click(root, '[data-act="next"]');
        expect(root.querySelector('.move-btn.active')?.textContent).toBe('e4');
        expect(new URL(location.href).searchParams.get('ply')).toBe('1');
        click(root, '[data-act="next"]'); // replaying e5 keeps Nf3 Nc6
        expect(root.querySelectorAll('.move-btn').length).toBe(4);
        click(root, '[data-act="prev"]');
        await flush();
        click(root, '[data-act="best"]'); // 2. ... different from e5? best is g1f3, illegal for Black: nothing happens
        expect(root.querySelectorAll('.move-btn').length).toBe(4);
        click(root, '[data-act="last"]');
        click(root, '[data-act="undo"]');
        expect(root.querySelector('.move-btn.active')?.textContent).toBe('Nf3');
    });

    it('opens a shared link at its move, and a PGN with its own start position', () => {
        root.dataset.moves = 'e4 e5 Nf3';
        root.dataset.ply = '1';
        runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        expect(root.querySelector('.move-btn.active')?.textContent).toBe('e4');
        expect(text(root, '.eval-turn')).toBe('Hitam untuk bergerak');

        const ok = (runner as unknown as Internals).applyInput('[SetUp "1"]\n[FEN "4k3/8/8/8/8/8/4P3/4K3 w - - 0 1"]\n\n1. e4 Kd7', false);
        expect(ok).toBe(true);
        expect([...root.querySelectorAll('.move-btn')].map((b) => b.textContent)).toEqual(['e4', 'Kd7']);
    });

    it('loads a FEN, and rejects text that is neither', () => {
        runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        const api = runner as unknown as Internals;
        expect(api.applyInput('r1bqkbnr/pppp1ppp/2n5/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 2 3', true)).toBe(true);
        expect(new URL(location.href).searchParams.get('fen')).toContain('r1bqkbnr');
        expect(api.applyInput('not a position', false)).toBe(false);
        expect(text(root, '.load-status')).toContain('FEN atau PGN tidak sah');
    });

    it('keeps the last panel while thinking and reuses scores when stepping back', async () => {
        root.dataset.moves = 'e4 e5 Nf3';
        const engine = fakeEngine({ cp: 25, mate: null, best: 'b8c6', lines: [{ pv: ['b8c6'], cp: 25, mate: null }] });
        runner = new AnalysisRunner(root, engine);
        await flush();
        expect(engine.calls.length).toBe(1);
        click(root, '[data-act="prev"]');
        // not scored yet: the old lines stay (faded) instead of disappearing
        expect(root.querySelector('.analysis-info')?.classList.contains('is-thinking')).toBe(true);
        expect(root.querySelectorAll('.engine-lines li').length).toBe(1);
        await flush();
        expect(root.querySelector('.analysis-info')?.classList.contains('is-thinking')).toBe(false);
        click(root, '[data-act="next"]');
        await flush();
        expect(engine.calls.length).toBe(2); // the position after Nf3 came from the cache
        expect(text(root, '.eval-score')).toContain('Penilaian');
    });

    it('announces checkmate itself and does not ask the engine', async () => {
        root.dataset.moves = 'f3 e5 g4 Qh4#';
        const engine = fakeEngine({ cp: 0, mate: 0, best: null });
        runner = new AnalysisRunner(root, engine);
        await flush();
        expect(text(root, '.eval-score')).toBe('Sah mati. Hitam menang.');
        expect(engine.calls).toEqual([]);
        expect((root.querySelector('[data-act="best"]') as HTMLButtonElement).disabled).toBe(true);
    });

    it('flips the board and keeps the arrow keys away from text fields', () => {
        runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        click(root, '[data-act="flip"]');
        expect(root.querySelector('.sq')?.getAttribute('data-sq')).toBe('7'); // h1 top-left when Black is at the bottom
        root.dataset.moves = '';
        (runner as unknown as Internals).applyInput('1. e4 e5', false);
        const ta = root.querySelector('textarea') as HTMLTextAreaElement;
        ta.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
        expect(root.querySelector('.move-btn.active')?.textContent).toBe('e5');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home' }));
        expect(root.querySelector('.move-btn.active')).toBeNull();
    });
});
