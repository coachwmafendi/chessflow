import type { StepContext, TapStep } from '../types';
import { gen, nm, sq } from '../core/squares';
import { SFX } from '../core/sound';
import { base } from './base';
import { t } from '../i18n';

export function tap(st: TapStep, c: StepContext): void {
    const b = base(st, c);
    let idx = 0;
    let got = new Set<number>();
    let busy = false;

    const show = () => {
        const it = st.seq[idx];
        c.task(it.ask);
        c.counter(st.seq.length > 1 ? t('Soalan :n/:total', { n: idx + 1, total: st.seq.length }) : '');
    };
    show();

    b.on((i) => {
        if (busy) return;
        const it = st.seq[idx];
        const ok = it.sq.map(sq);

        if (ok.includes(i)) {
            if (got.has(i)) return;
            got.add(i);
            b.mark({ good: [...got] });
            SFX.good();
            if (it.all && got.size < ok.length) {
                c.status(t('Betul! Ada lagi.'), 'good');
                return;
            }
            if (it.arrows) b.arrows(it.arrows);
            if (it.dotsOf) {
                const list = b.all().map((p) => ({ c: p.c, t: p.t, sq: p.sq }));
                b.mark({ dots: it.dotsOf.flatMap((s) => gen(list, sq(s))) });
            }
            c.status(it.ok || t('Betul!'), 'good');
            if (idx < st.seq.length - 1) {
                busy = true;
                c.later(() => {
                    idx++;
                    got = new Set();
                    b.mark({ good: [] });
                    b.arrows(st.arrows || []);
                    busy = false;
                    show();
                }, 1100);
            } else {
                SFX.win();
                c.done();
            }
        } else {
            b.flash(i);
            SFX.bad();
            c.mistake();
            const msg = (st.wrongMap && st.wrongMap[nm(i)]) || it.wrong || st.wrong || t('Bukan itu. Cuba lagi.');
            c.status(msg.replace('{sq}', nm(i)), 'bad');
        }
    });
}
