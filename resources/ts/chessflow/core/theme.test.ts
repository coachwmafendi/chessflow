// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { THEME_KEY, applyTheme, getTheme, nextTheme, setTheme } from './theme';

function memoryStore() {
    const m = new Map<string, string>();
    return {
        getItem: (k: string) => m.get(k) ?? null,
        setItem: (k: string, v: string) => void m.set(k, v),
        removeItem: (k: string) => void m.delete(k),
        map: m,
    };
}

describe('theme', () => {
    it('defaults to system and ignores unknown stored values', () => {
        const s = memoryStore();
        expect(getTheme(s)).toBe('system');
        s.map.set(THEME_KEY, 'purple');
        expect(getTheme(s)).toBe('system');
    });

    it('stores light/dark like Flux and removes the key for system', () => {
        const s = memoryStore();
        const root = document.createElement('html');
        setTheme('dark', s, root);
        expect(s.map.get(THEME_KEY)).toBe('dark');
        expect(root.dataset.theme).toBe('dark');
        setTheme('system', s, root);
        expect(s.map.has(THEME_KEY)).toBe(false);
        expect(root.dataset.theme).toBeUndefined();
    });

    it('still applies the theme when storage throws', () => {
        const root = document.createElement('html');
        const broken = {
            getItem: () => {
                throw new Error('denied');
            },
            setItem: () => {
                throw new Error('denied');
            },
            removeItem: () => {
                throw new Error('denied');
            },
        };
        setTheme('light', broken, root);
        expect(root.dataset.theme).toBe('light');
        expect(getTheme(broken)).toBe('system');
    });

    it('cycles Sistem → Cerah → Gelap → Sistem', () => {
        expect(nextTheme('system')).toBe('light');
        expect(nextTheme('light')).toBe('dark');
        expect(nextTheme('dark')).toBe('system');
    });

    it('applyTheme clears the attribute for system', () => {
        const root = document.createElement('html');
        applyTheme('dark', root);
        applyTheme('system', root);
        expect(root.hasAttribute('data-theme')).toBe(false);
    });
});
