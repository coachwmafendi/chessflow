import { t } from '../i18n';
// "Lencana baru!" block shown in the lesson/daily/review result card and under the game status.
// Badges are decided on the server (App\Support\Badges); this only draws the ones it sends back.

export interface NewBadge {
    key: string;
    name: string;
    description: string;
    icon: string;
}

const FLAME = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2s5 4.5 5 10a5 5 0 0 1-10 0c0-2.4 1.3-4.2 2.4-5.2.2 1.8 1.1 3 2.3 3.4C11 7.6 12 2 12 2zm0 12.5c-1.1.9-1.6 1.8-1.6 2.7a1.6 1.6 0 0 0 3.2 0c0-.9-.5-1.8-1.6-2.7z"/></svg>';
const MEDAL = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h4l1 4 1-4h4l-3.2 6.4A7 7 0 1 1 7.2 8.4zM12 10a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2 1.2 2.3 2.6.4-1.9 1.8.5 2.5-2.4-1.2-2.3 1.2.4-2.5-1.9-1.8 2.6-.4z"/></svg>';

function esc(s: string): string {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);
}

function icon(code: string): string {
    if (code === 'star') return '<i class="star-ico"></i>';
    if (code === 'flame') return FLAME;
    if (code === 'medal') return MEDAL;
    if (code === 'half') return '<span class="badge-glyph">½</span>';
    if (code === 'xp') return '<span class="badge-glyph">XP</span>';
    return /^[wb][KQRBNP]$/.test(code) ? '<i class="pc ' + code + '"></i>' : '';
}

export function newBadgesHtml(list: NewBadge[] | undefined): string {
    if (!list?.length) return '';
    return (
        '<div class="new-badges" role="status"><b>' +
        (list.length === 1 ? t('Lencana baru!') : t(':n lencana baru!', { n: list.length })) +
        '</b><ul>' +
        list
            .map((b) => '<li title="' + esc(b.description) + '"><span class="badge-medal" aria-hidden="true">' + icon(b.icon) + '</span>' + esc(b.name) + '</li>')
            .join('') +
        '</ul></div>'
    );
}
