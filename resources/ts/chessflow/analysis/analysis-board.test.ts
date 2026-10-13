// @vitest-environment jsdom
import { Chess } from 'chess.js';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AnalysisRunner } from './analysis-board';
import type { Evaluation, StockfishEngine } from '../engine/stockfish';

function fakeEngine(ev: Evaluation): StockfishEngine {
    return {
        available: true,
        analyse: () => Promise.resolve(ev),
        bestMove: () => Promise.resolve(ev.best ?? null),
        destroy: () => {},
    } as unknown as StockfishEngine;
}

describe('AnalysisRunner', () => {
    let root: HTMLDivElement;

    beforeEach(() => {
        root = document.createElement('div');
    });

    it('renders a board and the starting info text', async () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 20, mate: null, best: 'e2e4' }));
        // wait for debounced analyse
        await new Promise((r) => setTimeout(r, 500));
        expect(root.querySelectorAll('.sq').length).toBe(64);
        expect(root.querySelector('.eval-score')?.textContent).toContain('Penilaian Pak Kuda');
        runner.destroy();
    });

    it('resets to the starting position', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        (runner as unknown as { g: Chess }).g.move('e4');
        (runner as unknown as { reset: () => void }).reset();
        expect((runner as unknown as { g: Chess }).g.fen()).toBe('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
        runner.destroy();
    });

    it('undoes the last move', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        const g = (runner as unknown as { g: Chess }).g;
        g.move('e4');
        (runner as unknown as { undo: () => void }).undo();
        expect(g.history()).toEqual([]);
        runner.destroy();
    });

    it('flips the board orientation', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        expect((runner as unknown as { orient: 'w' | 'b' }).orient).toBe('w');
        (runner as unknown as { flip: () => void }).flip();
        expect((runner as unknown as { orient: 'w' | 'b' }).orient).toBe('b');
        runner.destroy();
    });

    it('plays the engine best move when the button is pressed', async () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 30, mate: null, best: 'e2e4' }));
        await new Promise((r) => setTimeout(r, 500));
        const g = (runner as unknown as { g: Chess }).g;
        (runner as unknown as { playBestMove: () => void }).playBestMove();
        expect(g.history()[0]).toBe('e4');
        runner.destroy();
    });

    it('loads a FEN position', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        const ok = (runner as unknown as { applyInput: (raw: string, sync: boolean) => boolean }).applyInput(
            'r1bqkbnr/pppp1ppp/2n5/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 0 1',
            false,
        );
        expect(ok).toBe(true);
        expect((runner as unknown as { g: Chess }).g.fen()).toContain('r1bqkbnr');
        runner.destroy();
    });

    it('loads a PGN string', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        const ok = (runner as unknown as { applyInput: (raw: string, sync: boolean) => boolean }).applyInput('1. e4 e5 2. Nf3', false);
        expect(ok).toBe(true);
        expect((runner as unknown as { g: Chess }).g.history()).toEqual(['e4', 'e5', 'Nf3']);
        runner.destroy();
    });

    it('shows an error for an invalid FEN or PGN', () => {
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        const ok = (runner as unknown as { applyInput: (raw: string, sync: boolean) => boolean }).applyInput('not a position', false);
        expect(ok).toBe(false);
        expect(root.querySelector('.load-status')?.textContent).toContain('FEN atau PGN tidak sah');
        runner.destroy();
    });

    it('syncs the position to the URL', async () => {
        const spy = vi.spyOn(window.history, 'replaceState');
        const runner = new AnalysisRunner(root, fakeEngine({ cp: 0, mate: null, best: null }));
        (runner as unknown as { g: Chess }).g.move('e4');
        (runner as unknown as { syncUrl: () => void }).syncUrl();
        expect(spy).toHaveBeenCalled();
        expect(spy.mock.calls[spy.mock.calls.length - 1][2]).toContain('fen=');
        runner.destroy();
    });
});
