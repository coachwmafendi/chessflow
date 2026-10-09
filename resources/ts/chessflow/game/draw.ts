import type { Chess } from 'chess.js';

/** Plies (half-moves) before Pak Kuda will consider a draw: 20 moves each. */
export const DRAW_MIN_PLIES = 40;
/** After a refused offer the player must play this many more plies (10 moves each) before asking again. */
export const DRAW_COOLDOWN_PLIES = 20;

const VALUE: Record<string, number> = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 0 };

export interface DrawVerdict {
    accept: boolean;
    /** Why: only a 'material' refusal starts the cooldown. */
    reason: 'early' | 'cooldown' | 'material' | 'equal' | 'player-ahead';
    message: string;
}

/** Material of white minus material of black, in pawns. */
export function materialBalance(g: Chess): number {
    let sum = 0;
    for (const row of g.board()) {
        for (const p of row) {
            if (p) sum += (p.color === 'w' ? 1 : -1) * VALUE[p.type];
        }
    }
    return sum;
}

/**
 * Pak Kuda's answer to "Minta seri". He only refuses when it is too early, the player just asked,
 * or he is ahead on material; the reason is always explained so the child learns why.
 */
export function judgeDrawOffer(g: Chess, user: 'w' | 'b', lastRefusedAt: number | null): DrawVerdict {
    const plies = g.history().length;
    if (plies < DRAW_MIN_PLIES) {
        return { accept: false, reason: 'early', message: 'Terlalu awal untuk seri. Pak Kuda mahu main sekurang-kurangnya 20 langkah dulu.' };
    }
    if (lastRefusedAt !== null && plies - lastRefusedAt < DRAW_COOLDOWN_PLIES) {
        return { accept: false, reason: 'cooldown', message: 'Awak baru minta seri. Main 10 langkah lagi sebelum minta semula.' };
    }
    const botAhead = (user === 'w' ? -1 : 1) * materialBalance(g);
    if (botAhead >= 2) {
        return { accept: false, reason: 'material', message: 'Pak Kuda tolak seri: dia ada lebih buah (' + botAhead + ' mata). Cuba pertahankan dan cari peluang!' };
    }
    if (botAhead <= -2) {
        return { accept: true, reason: 'player-ahead', message: 'Pak Kuda terima seri dengan gembira. Awak sebenarnya ada lebih buah, mungkin boleh menang!' };
    }
    return { accept: true, reason: 'equal', message: 'Pak Kuda setuju. Kedudukan seimbang, permainan seri.' };
}
