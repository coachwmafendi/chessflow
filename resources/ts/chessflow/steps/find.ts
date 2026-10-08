import type { FindStep, StepContext } from '../types';
import { gen, nm, pathFor, pcs, sq } from '../core/squares';
import { PNAME } from '../core/names';
import { SFX } from '../core/sound';
import { base } from './base';

export function find(st: FindStep, c: StepContext): void {
    const b = base(st, c);
    const list = pcs(st.pcs);
    const k = sq(st.piece);
    const t = gen(list, k);
    const piece = list.find((p) => p.sq === k);
    if (!piece) return;
    const found = new Set<number>();

    b.mark({ sel: k });
    c.counter('Jumpa: 0/' + t.length);

    b.on((i) => {
        if (i === k || found.has(i)) return;
        if (t.includes(i)) {
            found.add(i);
            b.mark({ good: [...found] });
            b.arrows([pathFor(piece.t, k, i)]);
            SFX.good();
            c.counter('Jumpa: ' + found.size + '/' + t.length);
            if (found.size === t.length) {
                c.status(st.done || 'Hebat! Awak jumpa semua petak.', 'good');
                SFX.win();
                c.done();
            } else {
                c.status('Betul! Teruskan cari.', 'good');
            }
        } else {
            b.flash(i);
            SFX.bad();
            c.mistake();
            const occupant = b.at(i);
            c.status(
                occupant && occupant.c === piece.c
                    ? 'Tak boleh. Itu buah sendiri.'
                    : nm(i) + ' bukan petak yang ' + PNAME[piece.t] + ' boleh pergi.',
                'bad',
            );
        }
    });
}
