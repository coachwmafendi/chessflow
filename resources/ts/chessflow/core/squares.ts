import type { ArrowDrawSpec, Piece, SquareSpec } from '../types';

export const FILES = 'abcdefgh';

const KN: [number, number][] = [
    [1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2],
];
const DIAG: [number, number][] = [[1, 1], [1, -1], [-1, 1], [-1, -1]];
const ORTH: [number, number][] = [[1, 0], [-1, 0], [0, 1], [0, -1]];

/** 0-63 index -> algebraic notation, e.g. "e4". */
export const nm = (i: number): string => FILES[i % 8] + String((i >> 3) + 1);

/** Algebraic notation -> 0-63 index. */
export const sq = (n: string): number => FILES.indexOf(n[0]) + (parseInt(n[1], 10) - 1) * 8;

/** Parse "wNd4 bPe5" into a flat piece list. */
export function pcs(str?: string): Piece[] {
    return (str || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map((t) => ({ c: t[0] as 'w' | 'b', t: t[1], sq: sq(t.slice(2)) }));
}

/** Parse the piece-placement field of a FEN into a flat piece list. */
export function fenList(fen: string): Piece[] {
    const out: Piece[] = [];
    const rows = fen.split(' ')[0].split('/');
    rows.forEach((row, ri) => {
        let f = 0;
        for (const ch of row) {
            if (/\d/.test(ch)) {
                f += +ch;
                continue;
            }
            out.push({ c: ch === ch.toUpperCase() ? 'w' : 'b', t: ch.toUpperCase(), sq: (7 - ri) * 8 + f });
            f++;
        }
    });
    return out;
}

/** Expand square specs, including "file:e" / "rank:4" line specs, into indices. */
export function expandSq(list?: (SquareSpec | number)[]): number[] {
    const out: number[] = [];
    (list || []).forEach((x) => {
        if (typeof x !== 'string') {
            out.push(x);
            return;
        }
        if (x.startsWith('file:')) {
            const f = FILES.indexOf(x[5]);
            for (let r = 0; r < 8; r++) out.push(r * 8 + f);
        } else if (x.startsWith('rank:')) {
            const r = +x[5] - 1;
            for (let f = 0; f < 8; f++) out.push(r * 8 + f);
        } else {
            out.push(sq(x));
        }
    });
    return out;
}

/**
 * Pseudo-legal destinations for the piece at `from`, given a flat occupancy list.
 * Geometric only — does not account for pins or check, matching the prototype's
 * behaviour for `find`/`collect`/`tap` steps (which don't need full chess rules).
 */
export function gen(list: Piece[], from: number): number[] {
    const occ = new Map(list.map((p) => [p.sq, p]));
    const p = occ.get(from);
    if (!p) return [];
    const f = from % 8;
    const r = from >> 3;
    const out: number[] = [];
    const add = (x: number, y: number): boolean => {
        if (x < 0 || x > 7 || y < 0 || y > 7) return false;
        const i = y * 8 + x;
        const o = occ.get(i);
        if (o) {
            if (o.c !== p.c) out.push(i);
            return false;
        }
        out.push(i);
        return true;
    };
    const slide = (dirs: [number, number][]) =>
        dirs.forEach(([dx, dy]) => {
            let x = f + dx;
            let y = r + dy;
            while (add(x, y)) {
                x += dx;
                y += dy;
            }
        });
    switch (p.t) {
        case 'N':
            KN.forEach(([a, b]) => add(f + a, r + b));
            break;
        case 'B':
            slide(DIAG);
            break;
        case 'R':
            slide(ORTH);
            break;
        case 'Q':
            slide(DIAG.concat(ORTH));
            break;
        case 'K':
            DIAG.concat(ORTH).forEach(([a, b]) => add(f + a, r + b));
            break;
        case 'P': {
            const d = p.c === 'w' ? 1 : -1;
            const startRank = p.c === 'w' ? 1 : 6;
            const y = r + d;
            if (y >= 0 && y < 8 && !occ.has(y * 8 + f)) {
                out.push(y * 8 + f);
                if (r === startRank && !occ.has((r + 2 * d) * 8 + f)) out.push((r + 2 * d) * 8 + f);
            }
            [-1, 1].forEach((dx) => {
                const x = f + dx;
                if (x >= 0 && x < 8 && y >= 0 && y < 8) {
                    const o = occ.get(y * 8 + x);
                    if (o && o.c !== p.c) out.push(y * 8 + x);
                }
            });
            break;
        }
    }
    return out;
}

/** 3-square path for a knight jump (for SVG polyline animation); [from, to] otherwise. */
export function lpath(a: number, b: number): number[] {
    const fa = a % 8;
    const ra = a >> 3;
    const df = (b % 8) - fa;
    const dr = (b >> 3) - ra;
    const mid = Math.abs(dr) === 2 ? (ra + dr) * 8 + fa : ra * 8 + fa + df;
    return [a, mid, b];
}

/** Arrow/path draw spec for a move, routing knights through their jump path. */
export function pathFor(pieceType: string, from: number, to: number): ArrowDrawSpec {
    return pieceType === 'N' ? { path: lpath(from, to), kind: 'path' } : { from, to, kind: 'path' };
}

/**
 * Minimum number of moves for the piece at `start` to visit every square in
 * `stars`, using pseudo-legal geometry only (brute-force permutation search —
 * fine for the handful of stars used in `collect` steps).
 */
export function tourOpt(list: Piece[], start: number, stars: number[]): number {
    const piece = list.find((p) => p.sq === start);
    if (!piece) return 0;
    const others = list.filter((p) => p !== piece);
    const dist = (a: number, b: number): number => {
        if (a === b) return 0;
        const d = new Array(64).fill(-1);
        d[a] = 0;
        const queue = [a];
        while (queue.length) {
            const x = queue.shift() as number;
            for (const y of gen(others.concat([{ c: piece.c, t: piece.t, sq: x }]), x)) {
                if (d[y] < 0) {
                    d[y] = d[x] + 1;
                    if (y === b) return d[y];
                    queue.push(y);
                }
            }
        }
        return 99;
    };
    let best = Infinity;
    const perm = (cur: number, rest: number[], acc: number) => {
        if (acc >= best) return;
        if (!rest.length) {
            best = acc;
            return;
        }
        rest.forEach((s, i) => perm(s, rest.filter((_, j) => j !== i), acc + dist(cur, s)));
    };
    perm(start, stars, 0);
    return best;
}

/** "e2e4q" -> {from, to, promotion}. */
export function uciToMove(u: string): { from: string; to: string; promotion?: string } {
    return { from: u.slice(0, 2), to: u.slice(2, 4), promotion: u[4] };
}
