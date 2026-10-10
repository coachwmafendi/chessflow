// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { initMapLevels } from './map-levels';

function memoryStore() {
    const m = new Map<string, string>();
    return { getItem: (k: string) => m.get(k) ?? null, setItem: (k: string, v: string) => void m.set(k, v), map: m };
}

function page(): HTMLElement {
    const root = document.createElement('div');
    root.innerHTML =
        '<div data-map-store="chessflow-map:7">' +
        '<details data-level="1"><summary>1</summary></details>' +
        '<details data-level="2" open><summary>2</summary></details>' +
        '<details data-level="3"><summary>3</summary></details>' +
        '</div>';
    return root;
}
const levels = (root: HTMLElement) => [...root.querySelectorAll<HTMLDetailsElement>('details')].map((d) => d.open);
const toggle = async (d: HTMLDetailsElement) => {
    d.open = !d.open;
    await new Promise((r) => setTimeout(r, 0)); // the toggle event is async
};

describe('initMapLevels', () => {
    it('keeps the server defaults when nothing is saved', () => {
        const root = page();
        initMapLevels(root, memoryStore());
        expect(levels(root)).toEqual([false, true, false]);
    });

    it('remembers what the student opens and closes, per account', async () => {
        const store = memoryStore();
        const first = page();
        initMapLevels(first, store);
        const [one, two] = first.querySelectorAll<HTMLDetailsElement>('details');
        await toggle(one);
        await toggle(two);
        expect(JSON.parse(store.map.get('chessflow-map:7')!)).toEqual({ '1': true, '2': false });

        const again = page();
        initMapLevels(again, store);
        expect(levels(again)).toEqual([true, false, false]);
    });

    it('ignores broken saved data', () => {
        const store = memoryStore();
        store.map.set('chessflow-map:7', 'not json');
        const root = page();
        initMapLevels(root, store);
        expect(levels(root)).toEqual([false, true, false]);
    });
});
