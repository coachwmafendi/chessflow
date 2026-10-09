import { describe, expect, it } from 'vitest';
import { newBadgesHtml } from './badges';

describe('newBadgesHtml', () => {
    it('is empty when nothing new was earned', () => {
        expect(newBadgesHtml(undefined)).toBe('');
        expect(newBadgesHtml([])).toBe('');
    });

    it('lists the badges with their icons and escapes the text', () => {
        const html = newBadgesHtml([
            { key: 'menang-pertama', name: 'Menang Pertama', description: 'Menang lawan Pak Kuda.', icon: 'wR' },
            { key: 'x', name: '<b>Nakal</b>', description: 'a "b"', icon: 'flame' },
        ]);
        expect(html).toContain('2 lencana baru!');
        expect(html).toContain('<i class="pc wR"></i>');
        expect(html).toContain('<svg');
        expect(html).toContain('&lt;b&gt;Nakal&lt;/b&gt;');
        expect(html).toContain('title="a &quot;b&quot;"');
    });

    it('ignores an unknown icon code instead of injecting it', () => {
        expect(newBadgesHtml([{ key: 'k', name: 'N', description: 'D', icon: 'x" onerror="1' }])).not.toContain('onerror');
    });
});
