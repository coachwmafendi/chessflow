import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync, statSync } from 'fs';
import { join, resolve } from 'path';
import { dictionaries } from '.';

const TS_ROOT = resolve(new URL(import.meta.url).pathname, '../..');
const EN = dictionaries.en;

function walk(dir: string): string[] {
    const out: string[] = [];
    for (const entry of readdirSync(dir)) {
        const full = join(dir, entry);
        const s = statSync(full);
        if (s.isDirectory()) {
            out.push(...walk(full));
        } else if (s.isFile() && full.endsWith('.ts') && !full.endsWith('.test.ts')) {
            out.push(full);
        }
    }
    return out;
}

/** Extract the Malay source strings passed to the i18n helper `t('...')` or `t("..." )`. */
function extractT(src: string): string[] {
    const found: string[] = [];
    // Only match calls where `t` is a standalone identifier/method (not `.createElement('div')`).
    const boundary = String.raw`(?<![A-Za-z0-9_$])`;
    const single = new RegExp(boundary + String.raw`t\(\s*'((?:[^'\\]|\\.)*)'`, 'g');
    const double = new RegExp(boundary + String.raw`t\(\s*"((?:[^"\\]|\\.)*)"`, 'g');
    for (const m of src.matchAll(single)) found.push(m[1].replace(/\\'/g, "'"));
    for (const m of src.matchAll(double)) found.push(m[1].replace(/\\"/g, '"'));
    return found;
}

describe('i18n coverage', () => {
    it('has an English translation for every Malay source string used in TS islands', () => {
        const files = walk(TS_ROOT);
        const used = new Set<string>();
        for (const f of files) {
            for (const key of extractT(readFileSync(f, 'utf-8'))) {
                used.add(key);
            }
        }

        const missing: string[] = [];
        for (const key of used) {
            if (!(key in EN)) missing.push(key);
        }

        expect(missing).toEqual([]);
    });
});
