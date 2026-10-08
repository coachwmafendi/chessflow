import { Chess } from 'chess.js';
import type { ExplainStep, StepContext } from '../types';
import { gen, nm, pathFor, pcs, sq, uciToMove } from '../core/squares';
import { PNAME } from '../core/names';
import { SFX } from '../core/sound';
import { base } from './base';

export function explain(st: ExplainStep, c: StepContext): void {
    base(st, c);

    if (st.showMoves) {
        const list = pcs(st.pcs);
        const k = sq(st.showMoves);
        const t = gen(list, k);
        const piece = list.find((p) => p.sq === k);
        if (piece) {
            const b = c.board();
            b?.mark({ sel: k, dots: t });
            if (st.initPath) b?.arrows([pathFor(piece.t, k, sq(st.initPath))]);
            b?.on((i) => {
                if (!t.includes(i)) return;
                b.arrows([pathFor(piece.t, k, i)]);
                SFX.move();
                c.status(PNAME[piece.t] + ' boleh pergi dari ' + nm(k) + ' ke ' + nm(i) + '.', 'info');
            });
        }
    }

    if (st.demo) {
        const play = () => {
            c.clear();
            const bb = base(st, c);
            c.status('', '');
            const g = new Chess(st.fen);
            let t = 800;
            st.demo!.forEach((u, idx) => {
                c.later(() => {
                    bb.arrows([]);
                    bb.mark({ ring: [] });
                    const m = g.move(uciToMove(u));
                    bb.applyMove(m);
                    SFX.move();
                    c.later(() => bb.quiet(g.fen()), 420);
                    const n = st.demoNotes && st.demoNotes[idx];
                    if (n) {
                        if (n.arrows) bb.arrows(n.arrows);
                        if (n.ring) bb.mark({ ring: n.ring });
                        if (n.status) c.status(n.status, 'info');
                    }
                    if (idx === st.demo!.length - 1 && st.demoEnd) c.status(st.demoEnd, 'good');
                }, t);
                t += st.demoNotes && st.demoNotes[idx] ? 1700 : 950;
            });
        };
        c.actions([{ label: 'Tengok semula', fn: play }]);
        play();
    }

    c.done();
}
