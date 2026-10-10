import { t } from '../i18n';

const BASE: Record<string, string> = {
    K: 'Raja',
    Q: 'Menteri',
    R: 'Tir',
    B: 'Gajah',
    N: 'Kuda',
    P: 'Bidak',
};

/**
 * Chess piece names keyed by chess.js/engine piece letter (K/Q/R/B/N/P), in the page language
 * (Bahasa Melayu source names, looked up through t()).
 */
export const PNAME: Record<string, string> = new Proxy(BASE, {
    get: (names, key) => (typeof key === 'string' && key in names ? t(names[key]) : undefined),
});
