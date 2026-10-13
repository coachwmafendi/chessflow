// @vitest-environment jsdom
import { Chess } from 'chess.js';
import { beforeEach, describe, expect, it } from 'vitest';
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
});
