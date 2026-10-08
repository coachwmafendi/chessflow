import type { Step, StepContext } from '../types';
import { collect } from './collect';
import { explain } from './explain';
import { find } from './find';
import { play } from './play';
import { puzzle } from './puzzle';
import { quiz } from './quiz';
import { tap } from './tap';

export function runStep(st: Step, c: StepContext): void {
    switch (st.type) {
        case 'explain':
            return explain(st, c);
        case 'find':
            return find(st, c);
        case 'collect':
            return collect(st, c);
        case 'tap':
            return tap(st, c);
        case 'quiz':
            return quiz(st, c);
        case 'puzzle':
            return puzzle(st, c);
        case 'play':
            return play(st, c);
    }
}
