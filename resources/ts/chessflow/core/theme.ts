// Colour theme shared with the Flux pages (login, settings): the same `flux.appearance` key and meaning,
// so choosing "Gelap" anywhere applies everywhere. 'light' | 'dark' are stored; "system" removes the key.
// base.css reads :root[data-theme]; without it the board follows prefers-color-scheme.

export type Theme = 'system' | 'light' | 'dark';

export const THEME_KEY = 'flux.appearance';

export const THEME_LABEL: Record<Theme, string> = { system: 'Sistem', light: 'Cerah', dark: 'Gelap' };

const ORDER: Theme[] = ['system', 'light', 'dark'];

type Store = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

function defaultStore(): Store | null {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function getTheme(store: Store | null = defaultStore()): Theme {
    try {
        const v = store?.getItem(THEME_KEY);
        return v === 'light' || v === 'dark' ? v : 'system';
    } catch {
        return 'system';
    }
}

export function applyTheme(theme: Theme, root: HTMLElement = document.documentElement): void {
    if (theme === 'system') delete root.dataset.theme;
    else root.dataset.theme = theme;
}

export function setTheme(theme: Theme, store: Store | null = defaultStore(), root: HTMLElement = document.documentElement): void {
    try {
        if (theme === 'system') store?.removeItem(THEME_KEY);
        else store?.setItem(THEME_KEY, theme);
    } catch {
        // Private mode or storage disabled: the choice still applies to this page.
    }
    applyTheme(theme, root);
}

/** Sistem → Cerah → Gelap → Sistem. */
export function nextTheme(theme: Theme): Theme {
    return ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length];
}
