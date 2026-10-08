import type { BoardApi, Step, StepContext } from '../types';
import { pcs } from '../core/squares';

/** Common setup shared by every step type: board + initial markings/arrows/counter. */
export function base(st: Step, c: StepContext): BoardApi {
    const b = c.newBoard(st.orient);
    if (st.fen) b.setFen(st.fen);
    else b.set(pcs(st.pcs));
    b.mark({ hl: st.hl || [], ring: st.ring || [], dots: st.dots || [] });
    if (st.arrows) b.arrows(st.arrows);
    if (st.counter) c.counter(st.counter);
    return b;
}
