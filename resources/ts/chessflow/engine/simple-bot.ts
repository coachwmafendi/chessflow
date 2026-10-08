import { Chess, type Move } from 'chess.js';
import type { BotKind } from '../types';

const VAL: Record<string, number> = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 0 };

/**
 * Pick a move for the opponent bot using simple heuristics — no search engine.
 * `kind` controls the bot's personality:
 * - "flee": avoid losing material, run the king away from threats
 * - "defend": like flee, but also pulls the king toward the center
 * - "chase": keep the king near the user's pawns to harass them
 */
export function bot(g: Chess, kind: BotKind, user: 'w' | 'b', noise = 1): Move | null {
    const me = g.turn();
    const moves = g.moves({ verbose: true });
    if (!moves.length) return null;
    let best: Move | null = null;
    let bestScore = -Infinity;

    for (const m of moves) {
        g.move(m);
        let s = m.captured ? VAL[m.captured] * 100 : 0;

        const replies = g.moves({ verbose: true });
        let threat = 0;
        for (const r of replies) {
            if (r.captured) threat = Math.max(threat, VAL[r.captured] || 0);
        }

        if (g.isCheckmate()) s -= 10000;

        let kpos: [number, number] | null = null;
        g.board().forEach((row, rowIndex) =>
            row.forEach((x, fi) => {
                if (x && x.type === 'k' && x.color === me) kpos = [fi, 7 - rowIndex];
            }),
        );

        if (kind === 'flee' || kind === 'defend') {
            s -= threat * 60;
            if (kpos) s -= Math.max(Math.abs(kpos[0] - 3.5), Math.abs(kpos[1] - 3.5)) * 6;
            const f = g.fen().split(' ');
            f[1] = me;
            f[3] = '-';
            try {
                s += new Chess(f.join(' ')).moves().length * 3;
            } catch {
                // malformed intermediate FEN (rare edge positions) — skip the mobility bonus.
            }
        }

        if (kind === 'chase' && kpos) {
            let d = 99;
            g.board().forEach((row) =>
                row.forEach((x, fi) => {
                    if (x && x.type === 'p' && x.color === user) {
                        const promoRank = user === 'w' ? 7 : 0;
                        d = Math.min(d, Math.max(Math.abs((kpos as [number, number])[0] - fi), Math.abs((kpos as [number, number])[1] - promoRank)));
                    }
                }),
            );
            s -= d * 10;
        }

        g.undo();
        s += Math.random() * noise;
        if (s > bestScore) {
            bestScore = s;
            best = m;
        }
    }

    return best;
}
