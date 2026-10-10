import { afterEach, describe, expect, it } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { dictionaries, t } from './index';

function sourceFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((f) => {
        const p = join(dir, f);
        if (statSync(p).isDirectory()) return sourceFiles(p);
        return p.endsWith('.ts') && !p.endsWith('.test.ts') ? [p] : [];
    });
}

describe('t()', () => {
    const g = globalThis as { document?: { documentElement: { lang: string } } };
    afterEach(() => delete g.document);

    it('returns the Malay source when the page is Malay or has no document', () => {
        expect(t('Cuba lagi')).toBe('Cuba lagi');
        g.document = { documentElement: { lang: 'ms' } };
        expect(t('Cuba lagi')).toBe('Cuba lagi');
    });

    it('fills :placeholders', () => {
        expect(t('Soalan :n/:total', { n: 2, total: 5 })).toBe('Soalan 2/5');
    });

    it('has an English entry for every t() string in the islands', () => {
        const missing: string[] = [];
        for (const file of sourceFiles('resources/ts/chessflow')) {
            const src = readFileSync(file, 'utf8');
            for (const m of src.matchAll(/\bt\(\s*'((?:[^'\\]|\\.)*)'/g)) {
                const key = m[1].replace(/\\'/g, "'");
                if (!(key in dictionaries.en)) missing.push(`${file}: ${key}`);
            }
        }
        expect(missing).toEqual([]);
    });
});
