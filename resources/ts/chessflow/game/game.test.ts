// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import type { StockfishEngine } from '../engine/stockfish';
import { GameRunner, type GameReport } from './game';

const offlineEngine = { available: false, bestMove: async () => null } as unknown as StockfishEngine;

describe('GameRunner resign confirmation', () => {
    let root: HTMLDivElement;
    let reports: GameReport[];
    let game: GameRunner;

    beforeEach(() => {
        root = document.createElement('div');
        document.body.appendChild(root);
        reports = [];
        game = new GameRunner(root, { finished: (r) => reports.push(r) }, offlineEngine);
    });

    afterEach(() => {
        game.destroy();
        root.remove();
    });

    const resignBtn = () => root.querySelector('[data-act="resign"]') as HTMLButtonElement;
    const dialog = () => root.querySelector('.modal [role="alertdialog"]');

    it('explains the notation letters under the move list', () => {
        expect(root.querySelector('.notation-key')?.textContent).toContain('N Kuda');
        expect(root.querySelector('.notation-key a')?.getAttribute('href')).toBe('/istilah');
    });

    it('asks first and focuses "Teruskan main"', () => {
        resignBtn().click();
        expect(dialog()).not.toBeNull();
        expect(document.activeElement?.textContent).toBe('Teruskan main');
        expect(reports).toEqual([]);
    });

    it('keeps playing on "Teruskan main", Escape or a backdrop click', () => {
        resignBtn().click();
        (root.querySelector('[data-r="no"]') as HTMLButtonElement).click();
        expect(dialog()).toBeNull();

        resignBtn().click();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(dialog()).toBeNull();

        resignBtn().click();
        (root.querySelector('.modal') as HTMLElement).click();
        expect(dialog()).toBeNull();
        expect(reports).toEqual([]);
    });

    it('reports a resignation only after "Ya, mengaku kalah"', () => {
        resignBtn().click();
        (root.querySelector('[data-r="yes"]') as HTMLButtonElement).click();
        expect(dialog()).toBeNull();
        expect(reports).toHaveLength(1);
        expect(reports[0].result).toBe('resign');
    });

    it('removes an open dialog on destroy (Livewire navigation)', () => {
        resignBtn().click();
        game.destroy();
        expect(dialog()).toBeNull();
        // The Escape listener was removed with the dialog: nothing to throw.
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    });
});
