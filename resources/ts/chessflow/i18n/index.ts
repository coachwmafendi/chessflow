// UI strings in the TS islands are written in Bahasa Melayu (the source language) and
// looked up here for other locales. Locale comes from <html lang>, set by the server.
import en from './en.json';

const DICTS: Record<string, Record<string, string>> = { en };

export function locale(): string {
    return (typeof document !== 'undefined' && document.documentElement.lang) || 'ms';
}

/** Translate a Malay source string; `:name` placeholders are filled from `params`. */
export function t(ms: string, params: Record<string, string | number> = {}): string {
    const dict = DICTS[locale()];
    let out = (dict && dict[ms]) || ms;
    for (const [k, v] of Object.entries(params)) out = out.split(':' + k).join(String(v));
    return out;
}

/** Translations available to tests (source strings with an English entry). */
export const dictionaries = DICTS;
