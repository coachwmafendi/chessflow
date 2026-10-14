import { Chess } from 'chess.js';
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { lessonForOpening, loadOpenings, openingFor, resetOpenings, type OpeningTable } from './openings';

const table = JSON.parse(readFileSync('public/openings/openings.json', 'utf8')) as OpeningTable;
const after = (sans: string[]) => {
    const g = new Chess();
    return sans.map((s) => g.move(s).after);
};

describe('openings', () => {
    it('names the most specific opening reached', () => {
        expect(openingFor(table, after(['e4', 'e5', 'Nf3', 'Nc6', 'Bc4']))).toEqual({ eco: 'C50', name: 'Italian Game' });
        // past the book, the last named position still counts
        expect(openingFor(table, after(['e4', 'e5', 'Nf3', 'Nc6', 'Bc4', 'a6', 'a3', 'h6']))?.name).toMatch(/^Italian Game/);
        expect(openingFor(table, after(['e4', 'c6']))?.name).toBe('Caro-Kann Defense');
        expect(openingFor(table, [])).toBeNull();
    });

    it('recognises a transposition by position', () => {
        const a = openingFor(table, after(['d4', 'Nf6', 'c4', 'e6', 'Nc3', 'Bb4']));
        const b = openingFor(table, after(['c4', 'e6', 'Nc3', 'Nf6', 'd4', 'Bb4']));
        expect(a).toEqual({ eco: 'E20', name: 'Nimzo-Indian Defense' });
        expect(b).toEqual(a);
    });

    it('points named openings to the lessons that teach them', () => {
        expect(lessonForOpening('Italian Game: Two Knights Defense')).toBe('putih');
        expect(lessonForOpening('Indian Defense: London System')).toBe('putih');
        expect(lessonForOpening('Caro-Kann Defense: Advance Variation')).toBe('hitam');
        expect(lessonForOpening("Queen's Gambit Declined")).toBe('gambit');
        expect(lessonForOpening('Sicilian Defense')).toBeNull();
    });

    it('loads the table once and copes with a failed request', async () => {
        resetOpenings();
        let calls = 0;
        const ok = (() => {
            calls++;
            return Promise.resolve(new Response(JSON.stringify({ x: ['A00', 'Test'] })));
        }) as typeof fetch;
        expect(await loadOpenings(ok)).toEqual({ x: ['A00', 'Test'] });
        await loadOpenings(ok);
        expect(calls).toBe(1);
        resetOpenings();
        expect(await loadOpenings((() => Promise.reject(new Error('offline'))) as typeof fetch)).toBeNull();
        resetOpenings();
    });
});
