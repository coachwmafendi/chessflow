// Opening names (lichess-org/chess-openings, CC0), looked up by position so transpositions count.
// The table (public/openings/openings.json, built by tools/build-openings.cjs) is fetched once, on the pages that show it.

export type OpeningTable = Record<string, [string, string]>;

export interface Opening {
    eco: string;
    name: string;
}

const URL = '/openings/openings.json';
let table: Promise<OpeningTable | null> | null = null;

export function loadOpenings(fetcher: typeof fetch = fetch): Promise<OpeningTable | null> {
    return (table ??= fetcher(URL)
        .then((r) => (r.ok ? (r.json() as Promise<OpeningTable>) : null))
        .catch(() => null));
}

/** For tests. */
export function resetOpenings(): void {
    table = null;
}

/** Position part of a FEN (no move counters), the table's key. */
export const epd = (fen: string): string => fen.split(' ').slice(0, 4).join(' ');

/** The most specific opening reached by these positions (FENs after each move, in order), or null. */
export function openingFor(t: OpeningTable, fens: string[]): Opening | null {
    for (let i = fens.length - 1; i >= 0; i--) {
        const hit = t[epd(fens[i])];
        if (hit) return { eco: hit[0], name: hit[1] };
    }
    return null;
}

/** The ChessFlow lesson that teaches this opening (Tahap 2), if any. */
export function lessonForOpening(name: string): 'putih' | 'hitam' | 'gambit' | null {
    if (/^(Italian Game|.*London System)/.test(name)) return 'putih';
    if (/^(Scandinavian Defense|Caro-Kann Defense)/.test(name)) return 'hitam';
    if (/^(Queen's Gambit|King's Gambit)/.test(name)) return 'gambit';
    return null;
}
