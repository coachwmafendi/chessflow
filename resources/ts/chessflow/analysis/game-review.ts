import type { Move } from 'chess.js';
import type { Evaluation } from '../engine/stockfish';
import { analyseGame, type Evaluate, type Mistake } from '../game/analysis';

// Whole-game review for the analysis board (any PGN, both sides): one engine pass over every position, then the
// same mistake finder as the post-game review, run for White and for Black on the cached scores.

export interface GameReview {
    /** White's score in centipawns (mates as ±1000) for the start position and after every move; null if unknown. */
    scores: (number | null)[];
    mistakes: { w: Mistake[]; b: Mistake[] };
}

const CLAMP = 1000;

/** Side-to-move evaluation as White's score, clamped for the graph. */
export function whiteScore(ev: Evaluation | null | undefined, fen: string): number | null {
    if (!ev || (ev.cp === null && ev.mate === null)) return null;
    const k = fen.split(' ')[1] === 'w' ? 1 : -1;
    // mate 0 means the side to move is already mated
    const s = ev.mate !== null ? (ev.mate > 0 ? CLAMP : -CLAMP) : Math.max(-CLAMP, Math.min(CLAMP, ev.cp as number));
    return s * k;
}

/**
 * Evaluate every position of the game once, then find each side's mistakes.
 * `onProgress` gets 0..1; `stop()` returning true aborts (returns null).
 */
export async function reviewGame(moves: Move[], evaluate: Evaluate, onProgress: (done: number) => void = () => {}, stop: () => boolean = () => false): Promise<GameReview | null> {
    if (!moves.length) return { scores: [], mistakes: { w: [], b: [] } };
    const fens = [moves[0].before, ...moves.map((m) => m.after)];
    const seen = new Map<string, Evaluation | null>();
    for (let i = 0; i < fens.length; i++) {
        if (stop()) return null;
        if (!seen.has(fens[i])) seen.set(fens[i], await evaluate(fens[i]));
        onProgress((i + 1) / fens.length);
    }
    if (stop()) return null;
    const cached: Evaluate = async (fen) => (seen.has(fen) ? (seen.get(fen) ?? null) : evaluate(fen));
    const w = await analyseGame(moves, 'w', cached, () => {}, () => false, 'lawan');
    const b = await analyseGame(moves, 'b', cached, () => {}, () => false, 'lawan');
    if (w === null || b === null) return null;
    return { scores: fens.map((f) => whiteScore(seen.get(f), f)), mistakes: { w, b } };
}

/** Small SVG graph of White's advantage over the game; the current ply is marked. Width maps to plies. */
export function evalGraphSvg(scores: (number | null)[], current: number, mistakePlies: number[] = []): string {
    const n = Math.max(scores.length - 1, 1);
    const W = 600;
    const H = 90;
    const x = (i: number) => ((i / n) * W).toFixed(1);
    // Squash big scores so normal advantages stay visible: ±1000 cp maps to the edges.
    const y = (s: number) => (H / 2 - (Math.sign(s) * Math.min(1, Math.sqrt(Math.abs(s) / CLAMP)) * H) / 2).toFixed(1);
    let last = 0;
    const pts = scores.map((s, i) => {
        if (s !== null) last = s;
        return x(i) + ',' + y(last);
    });
    const area = 'M0,' + H / 2 + ' L' + pts.join(' L') + ' L' + x(scores.length - 1) + ',' + H / 2 + ' Z';
    const dots = mistakePlies.map((p) => '<circle class="eg-mistake" cx="' + x(p + 1) + '" cy="' + y(scores[p + 1] ?? 0) + '" r="4"/>').join('');
    return (
        '<svg class="eval-graph" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img">' +
        '<rect class="eg-black" x="0" y="0" width="' + W + '" height="' + H + '"/>' +
        '<path class="eg-white" d="' + area + '"/>' +
        '<line class="eg-mid" x1="0" y1="' + H / 2 + '" x2="' + W + '" y2="' + H / 2 + '"/>' +
        '<line class="eg-now" x1="' + x(current) + '" y1="0" x2="' + x(current) + '" y2="' + H + '"/>' +
        dots +
        '</svg>'
    );
}
