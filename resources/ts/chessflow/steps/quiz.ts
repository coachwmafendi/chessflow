import type { QuizStep, StepContext } from '../types';
import { SFX } from '../core/sound';
import { base } from './base';

export function quiz(st: QuizStep, c: StepContext): void {
    base(st, c);
    const box = c.opts();

    st.options.forEach((op) => {
        const bt = document.createElement('button');
        bt.type = 'button';
        bt.className = 'opt';
        bt.innerHTML = op.t;
        bt.onclick = () => {
            if (op.ok) {
                bt.classList.add('good');
                box.querySelectorAll('button').forEach((x) => ((x as HTMLButtonElement).disabled = true));
                c.status(op.why || 'Betul!', 'good');
                c.statusHtml(op.why || 'Betul!');
                SFX.good();
                c.done();
            } else {
                bt.classList.add('bad');
                bt.disabled = true;
                c.mistake();
                SFX.bad();
                c.status('', 'bad');
                c.statusHtml(op.why || 'Cuba lagi.');
            }
        };
        box.appendChild(bt);
    });
}
