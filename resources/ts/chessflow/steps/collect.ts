import type { BoardApi, CollectStep, StepContext } from '../types';
import { gen, pcs, sq, tourOpt } from '../core/squares';
import { SFX } from '../core/sound';
import { base } from './base';

export function collect(st: CollectStep, c: StepContext): void {
    const list0 = pcs(st.pcs);
    const start = sq(st.piece);
    const stars = st.stars.map(sq);
    const opt = tourOpt(list0, start, stars);

    let k = start;
    let left = new Set(stars);
    let moves = 0;
    let fin = false;
    let b: BoardApi;

    const draw = () => {
        const list = b.all().map((p) => ({ c: p.c, t: p.t, sq: p.sq }));
        b.mark({ sel: k, dots: fin ? [] : gen(list, k), stars: [...left] });
        c.counter('Langkah: ' + moves + ' · Bintang: ' + (stars.length - left.size) + '/' + stars.length);
    };

    const reset = () => {
        b = base(st, c);
        k = start;
        left = new Set(stars);
        moves = 0;
        fin = false;
        draw();
        c.status('', '');
        b.on((i) => {
            if (fin) return;
            const list = b.all().map((p) => ({ c: p.c, t: p.t, sq: p.sq }));
            if (!gen(list, k).includes(i)) return;
            b.move(k, i);
            k = i;
            moves++;
            if (left.has(i)) {
                left.delete(i);
                SFX.star();
            } else {
                SFX.move();
            }
            if (!left.size) {
                fin = true;
                const extra = moves - opt;
                if (extra > 3) c.mistake(2);
                else if (extra > 0) c.mistake(1);
                c.status(
                    'Misi selesai dalam ' +
                        moves +
                        ' langkah! ' +
                        (extra <= 0
                            ? 'Itu jumlah paling sedikit. Hebat!'
                            : 'Paling sedikit ialah ' + opt + ' langkah. Tekan "Mula semula" kalau nak cuba lagi.'),
                    'good',
                );
                SFX.win();
                c.done();
            }
            draw();
        });
    };

    c.actions([{ label: 'Mula semula', fn: reset }]);
    reset();
}
