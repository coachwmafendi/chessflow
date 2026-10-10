import { Chess, type Move } from 'chess.js';
import { PNAME } from '../core/names';
import type { Evaluation } from '../engine/stockfish';
import { t } from '../i18n';

// Post-game review: score the position before and after each of the player's moves and
// explain the biggest drops in words a child can follow. Pure (the engine is passed in) so it
// can be tested without Stockfish.

export type Evaluate = (fen: string) => Promise<Evaluation | null>;

export interface Mistake {
    ply: number;
    moveNo: number;
    san: string;
    from: string;
    to: string;
    fenBefore: string;
    best: { from: string; to: string; san: string } | null;
    loss: number;
    severity: 'besar' | 'kecil';
    text: string;
}

/** Scores are clamped so a mate counts as a big, but finite, swing. */
const CAP = 1500;
/** A drop of this many centipawns or more is a mistake; BIG or more is a blunder. */
export const MISTAKE_CP = 200;
export const BIG_CP = 300;
/** Show at most this many, the worst ones. */
export const MAX_SHOWN = 3;
/** Long games: only the first plies are analysed, to keep the wait short. */
export const MAX_PLIES = 160;

/** Engine score (side to move) turned into the player's point of view, clamped. */
export function scoreFor(ev: Evaluation, sideToMove: 'w' | 'b', user: 'w' | 'b'): number | null {
    let s: number;
    if (ev.mate !== null) s = ev.mate > 0 ? CAP : -CAP; // mate 0: the side to move is already mated
    else if (ev.cp !== null) s = Math.max(-CAP, Math.min(CAP, ev.cp));
    else return null;
    return sideToMove === user ? s : -s;
}

function uciMove(fen: string, uci: string | null): Move | null {
    if (!uci) return null;
    try {
        return new Chess(fen).move({ from: uci.slice(0, 2), to: uci.slice(2, 4), promotion: uci[4] });
    } catch {
        return null;
    }
}

const name = (p: string | undefined) => (p ? PNAME[p.toUpperCase()] : '');
const VALUE: Record<string, number> = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 0 };

/** One sentence on what went wrong, from the engine's best move before and best reply after. */
export function explain(san: string, before: Evaluation, after: Evaluation, best: Move | null, reply: Move | null, loss: number): string {
    if (after.mate !== null && after.mate > 0 && reply) {
        return after.mate > 1
            ? t('Langkah ini beri Pak Kuda peluang sah mati dalam :n langkah, bermula dengan :san.', { n: after.mate, san: reply.san })
            : t('Langkah ini beri Pak Kuda peluang sah mati, bermula dengan :san.', { san: reply.san });
    }
    if (reply?.captured && reply.captured !== 'p') {
        return t('Selepas :move, Pak Kuda boleh makan :piece awak dengan :san.', { move: san, piece: name(reply.captured), san: reply.san });
    }
    if (before.mate !== null && before.mate > 0 && best) {
        return before.mate > 1
            ? t('Ada sah mati dalam :n langkah! Cuba :san.', { n: before.mate, san: best.san })
            : t('Ada sah mati! Cuba :san.', { san: best.san });
    }
    // A missed pawn does not explain a big drop; then the stronger move says more.
    if (best?.captured && (VALUE[best.captured] >= 3 || loss < BIG_CP)) {
        return t('Awak terlepas peluang makan :piece dengan :san.', { piece: name(best.captured), san: best.san });
    }
    if (reply?.captured) {
        return t('Selepas :move, Pak Kuda boleh makan bidak awak dengan :san.', { move: san, san: reply.san });
    }
    return best ? t('Langkah yang lebih kuat ialah :san.', { san: best.san }) : t('Langkah ini melemahkan kedudukan awak.');
}

/**
 * Analyse the player's moves. `onProgress` gets 0..1; `stop()` returning true aborts (new game).
 * Returns null if aborted, else the worst mistakes in game order (empty = well played).
 */
export async function analyseGame(
    moves: Move[],
    user: 'w' | 'b',
    evaluate: Evaluate,
    onProgress: (done: number) => void = () => {},
    stop: () => boolean = () => false,
): Promise<Mistake[] | null> {
    const list = moves.slice(0, MAX_PLIES);
    const fens = [...list.map((m) => m.before), list.length ? list[list.length - 1].after : ''];

    // Only the positions around the player's own moves are needed.
    const needed = new Set<number>();
    list.forEach((m, i) => {
        if (m.color === user) needed.add(i).add(i + 1);
    });
    const order = [...needed].sort((a, b) => a - b);
    const evals = new Map<number, Evaluation>();
    for (let k = 0; k < order.length; k++) {
        if (stop()) return null;
        const ev = await evaluate(fens[order[k]]);
        if (ev) evals.set(order[k], ev);
        onProgress((k + 1) / order.length);
    }
    if (stop()) return null;

    const found: Mistake[] = [];
    list.forEach((m, i) => {
        if (m.color !== user) return;
        const evB = evals.get(i);
        const evA = evals.get(i + 1);
        if (!evB || !evA) return;
        const before = scoreFor(evB, user, user);
        const after = scoreFor(evA, user === 'w' ? 'b' : 'w', user);
        if (before === null || after === null) return;
        const loss = before - after;
        // A short forced mate that slipped away is always worth showing, even when still winning.
        const missedMate = evB.mate !== null && evB.mate > 0 && evB.mate <= 3 && !(evA.mate !== null && evA.mate <= 0);
        // Otherwise: still clearly winning, or already lost, is not worth pointing at.
        if (!missedMate && (loss < MISTAKE_CP || after >= 300 || before <= -600)) return;
        const best = uciMove(m.before, evB.best);
        if (best && best.from === m.from && best.to === m.to) return;
        const reply = uciMove(m.after, evA.best);
        found.push({
            ply: i,
            moveNo: Math.floor(i / 2) + 1,
            san: m.san,
            from: m.from,
            to: m.to,
            fenBefore: m.before,
            best: best ? { from: best.from, to: best.to, san: best.san } : null,
            loss,
            severity: loss >= BIG_CP ? 'besar' : 'kecil',
            text: explain(m.san, evB, evA, best, reply, loss),
        });
    });

    return found
        .sort((a, b) => b.loss - a.loss)
        .slice(0, MAX_SHOWN)
        .sort((a, b) => a.ply - b.ply);
}
