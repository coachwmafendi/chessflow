import { Chess, type Color, type Move, type Square } from 'chess.js';

// Which lesson teaches the idea behind a mistake. Checked with chess.js on the actual moves, most telling first:
// a missed or allowed mate, a fork, a pin or skewer, a discovered attack, then a piece left hanging or not taken.

/** Lesson slugs a mistake can point to (data/lessons.json). */
export type PatternLesson = 'sah' | 'mate2' | 'scholar' | 'fork' | 'pin' | 'terbuka' | 'cct';
export const PATTERN_LESSONS: PatternLesson[] = ['sah', 'mate2', 'scholar', 'fork', 'pin', 'terbuka', 'cct'];

const VALUE: Record<string, number> = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 100 };
const FILES = 'abcdefgh';
const DIRS: Record<string, [number, number][]> = {
    b: [[1, 1], [1, -1], [-1, 1], [-1, -1]],
    r: [[1, 0], [-1, 0], [0, 1], [0, -1]],
    q: [[1, 1], [1, -1], [-1, 1], [-1, -1], [1, 0], [-1, 0], [0, 1], [0, -1]],
};

export interface MistakeMoves {
    /** The player's move, and the engine's better one from the same position. */
    played: Move;
    best: Move | null;
    /** The opponent's best reply after the player's move. */
    reply: Move | null;
    /** Mate distances from the engine (side to move), before and after the player's move. */
    mateBefore: number | null;
    mateAfter: number | null;
}

/** Opponent pieces worth forking that the piece on `from` attacks: the king, or anything worth more than it. */
function forkTargets(g: Chess, from: Square): Square[] {
    const piece = g.get(from);
    if (!piece) return [];
    const out: Square[] = [];
    for (const row of g.board())
        for (const p of row) {
            if (!p || p.color === piece.color) continue;
            if ((p.type === 'k' || VALUE[p.type] > VALUE[piece.type]) && g.attackers(p.square, piece.color).includes(from)) out.push(p.square);
        }
    return out;
}

export function isFork(after: Chess, to: Square): boolean {
    return forkTargets(after, to).length >= 2;
}

/** A bishop, rook or queen on `from` lines up two enemy pieces: the front one pinned (worth less) or skewered (worth more). */
export function isPinOrSkewer(after: Chess, from: Square): boolean {
    const piece = after.get(from);
    if (!piece || !DIRS[piece.type]) return false;
    const f0 = FILES.indexOf(from[0]);
    const r0 = Number(from[1]);
    for (const [df, dr] of DIRS[piece.type]) {
        const hits: { type: string; color: Color }[] = [];
        for (let f = f0 + df, r = r0 + dr; f >= 0 && f < 8 && r >= 1 && r <= 8 && hits.length < 2; f += df, r += dr) {
            const p = after.get((FILES[f] + r) as Square);
            if (p) hits.push(p);
        }
        const [front, back] = hits;
        if (!front || !back || front.color === piece.color || back.color === piece.color) continue;
        const pin = VALUE[back.type] > VALUE[front.type] && VALUE[back.type] > VALUE[piece.type];
        const skewer = front.type === 'k' || (VALUE[front.type] > VALUE[back.type] && VALUE[front.type] > VALUE[piece.type]);
        if (pin || skewer) return true;
    }
    return false;
}

/**
 * Moving a piece opens a line: another of our bishops, rooks or queens now attacks something it did not before,
 * and it is worth it: the king, a piece worth more than the attacker, or an undefended piece worth 3+.
 */
export function isDiscovered(before: Chess, move: Move): boolean {
    const after = new Chess(move.after);
    const us = move.color;
    for (const row of after.board())
        for (const p of row) {
            if (!p || p.color === us || (p.type !== 'k' && VALUE[p.type] < 3)) continue;
            const was = new Set(before.attackers(p.square, us));
            const undefended = after.attackers(p.square, p.color).length === 0;
            const found = after.attackers(p.square, us).some((s) => {
                const a = after.get(s);
                if (s === move.to || was.has(s) || !a || !'brq'.includes(a.type)) return false;
                return p.type === 'k' || VALUE[p.type] > VALUE[a.type] || undefended;
            });
            if (found) return true;
        }
    return false;
}

/** The lesson that teaches what went wrong, or null when no pattern fits. */
export function lessonForMistake(m: MistakeMoves): PatternLesson | null {
    const { played, best, reply } = m;
    // Missed a mate.
    if (best && best.san.endsWith('#')) return 'sah';
    if (m.mateBefore !== null && m.mateBefore > 0 && m.mateBefore <= 3) return 'mate2';
    // Allowed a mate: early queen/bishop mates on f7/f2 are the Scholar's Mate lesson.
    if (reply && (reply.san.endsWith('#') || (m.mateAfter !== null && m.mateAfter > 0 && m.mateAfter <= 3))) {
        const moveNo = Number(played.before.split(' ')[5]);
        return moveNo <= 8 && reply.san.endsWith('#') && (reply.to === 'f7' || reply.to === 'f2') ? 'scholar' : 'cct';
    }
    // What Pak Kuda can do now comes first, as in the explanation shown next to it: a fork, or a piece left hanging.
    if (reply && isFork(new Chess(reply.after), reply.to)) return 'fork';
    if (reply?.captured && VALUE[reply.captured] >= 3) return 'cct';
    // Then the idea of the better move.
    if (best && isFork(new Chess(best.after), best.to)) return 'fork';
    if (best && isPinOrSkewer(new Chess(best.after), best.to)) return 'pin';
    if (best && isDiscovered(new Chess(best.before), best)) return 'terbuka';
    if (best?.captured && VALUE[best.captured] >= 3) return 'cct';
    return null;
}
