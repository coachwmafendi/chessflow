import type { FindStep, StepContext } from '../types';
import { gen, nm, pathFor, pcs, sq } from '../core/squares';
import { PNAME } from '../core/names';
import { SFX } from '../core/sound';
import { base } from './base';
import { t } from '../i18n';

export function find(st: FindStep, c: StepContext): void {
    const b = base(st, c);
    const list = pcs(st.pcs);
    const k = sq(st.piece);
    const targets = gen(list, k);
    const piece = list.find((p) => p.sq === k);
    if (!piece) return;
    const found = new Set<number>();

    b.mark({ sel: k });
    c.counter(t('Jumpa: :n/:total', { n: 0, total: targets.length }));

    b.on((i) => {
        if (i === k || found.has(i)) return;
        if (targets.includes(i)) {
            found.add(i);
            b.mark({ good: [...found] });
            b.arrows([pathFor(piece.t, k, i)]);
            SFX.good();
            c.counter(t('Jumpa: :n/:total', { n: found.size, total: targets.length }));
            if (found.size === targets.length) {
                c.status(st.done || t('Hebat! Awak jumpa semua petak.'), 'good');
                SFX.win();
                c.done();
            } else {
                c.status(t('Betul! Teruskan cari.'), 'good');
            }
        } else {
            b.flash(i);
            SFX.bad();
            c.mistake();
            const occupant = b.at(i);
            c.status(
                occupant && occupant.c === piece.c
                    ? t('Tak boleh. Itu buah sendiri.')
                    : t(':sq bukan petak yang :piece boleh pergi.', { sq: nm(i), piece: PNAME[piece.t] }),
                'bad',
            );
        }
    });
}
