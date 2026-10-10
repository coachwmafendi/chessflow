import { Chess, type Move, type Square } from 'chess.js';
import type { PuzzleStep, StepContext } from '../types';
import { nm, sq, uciToMove } from '../core/squares';
import { SFX } from '../core/sound';
import { base } from './base';

/** True if the side to move in `g` has a mate-in-one available. */
export function oppHasMate(g: Chess): boolean {
    const moves = g.moves({ verbose: true });
    for (const m of moves) {
        g.move(m);
        const mate = g.isCheckmate();
        g.undo();
        if (mate) return true;
    }
    return false;
}

/**
 * Pure acceptance check for a puzzle move, factored out of the DOM-driven step
 * runner so it can be unit tested directly against chess.js. `ply` is the
 * number of plies already accepted (0-based, read BEFORE this move is counted).
 * `game` must reflect the position AFTER `result` was played (for isCheckmate
 * / oppHasMate checks).
 */
export function evaluatePuzzleAcceptance(st: PuzzleStep, ply: number, result: Move, game: Chess): boolean {
    if (st.line) {
        const expected = st.line[ply];
        // The promotion piece counts too (a 4-letter line move that promotes means a queen).
        const want = expected.length === 4 && result.promotion ? expected + 'q' : expected;
        if (result.from + result.to + (result.promotion ?? '') === want) return true;
        if (st.alts && st.alts[ply] && st.alts[ply].includes(result.san)) return true;
        return ply === 0 && !!st.acceptSan && st.acceptSan.includes(result.san);
    }
    if (st.acceptSan) return st.acceptSan.includes(result.san);
    if (st.acceptMate) return game.isCheckmate();
    const a = st.accept || {};
    if (a.piece && result.piece !== a.piece) return false;
    if (a.notPiece && result.piece === a.notPiece) return false;
    if (a.to && !a.to.includes(result.to)) return false;
    if (a.toFile && !a.toFile.includes(result.to[0])) return false;
    if (a.capture && !result.captured) return false;
    if (a.noMateInOne && oppHasMate(game)) return false;
    return true;
}

export function puzzle(st: PuzzleStep, c: StepContext): void {
    const b = base(st, c);
    const g = new Chess(st.fen);
    const user = g.turn();
    let ply = 0;
    let sel: Square | null = null;
    let busy = false;
    let wrong = 0;

    const hint = () => {
        if (st.hintRing) b.mark({ ring: st.hintRing });
    };
    if (st.hintRing) {
        c.actions([
            {
                label: 'Petunjuk',
                fn: () => {
                    c.mistake();
                    hint();
                    c.status('Petunjuk: perhatikan petak bulat merah.', 'info');
                },
            },
        ]);
    }

    b.on((i) => {
        if (busy) return;
        const square = nm(i) as Square;
        const p = g.get(square);

        if (p && p.color === user && g.turn() === user) {
            sel = square;
            b.mark({ sel: i, dots: g.moves({ square, verbose: true }).map((m) => sq(m.to)) });
            return;
        }
        if (!sel) return;
        const legal = g.moves({ square: sel, verbose: true }).filter((m) => m.to === square);
        if (!legal.length) return;

        const from = legal[0].from;
        if (legal[0].promotion) {
            // The player picks the piece; the puzzle line decides whether it was the right one.
            busy = true;
            void b.choosePromotion(user).then((piece) => {
                busy = false;
                if (piece) attempt(from, square, i, piece);
                else {
                    sel = null;
                    b.mark({ sel: null, dots: [] });
                }
            });
            return;
        }
        attempt(from, square, i);
    });

    function attempt(from: Square, square: Square, i: number, promotion?: string): void {
        const res = g.move({ from, to: square, promotion });
        b.applyMove(res);
        b.mark({ sel: null, dots: [], ring: [] });
        b.arrows([]);
        sel = null;
        c.later(() => b.quiet(g.fen()), 420);

        if (evaluatePuzzleAcceptance(st, ply, res, g)) {
            ply++;
            SFX.good();
            b.mark({ good: [sq(res.to)] });

            if (st.line && ply < st.line.length) {
                if (ply === 1 && st.after1) {
                    if (st.after1.arrows) b.arrows(st.after1.arrows);
                    if (st.after1.status) c.status(st.after1.status, 'good');
                } else {
                    c.status('Bagus!', 'good');
                }
                busy = true;
                c.later(
                    () => {
                        const m = g.move(uciToMove(st.line![ply]));
                        b.arrows([]);
                        b.mark({ good: [] });
                        b.applyMove(m);
                        SFX.move();
                        ply++;
                        c.later(() => b.quiet(g.fen()), 420);
                        if (ply >= st.line!.length) {
                            c.status(st.win || 'Betul!', 'good');
                            SFX.win();
                            c.done();
                            return;
                        }
                        busy = false;
                        const mi = (ply - 2) / 2;
                        c.status((st.mids && st.mids[mi]) || st.mid || 'Teruskan!', 'info');
                    },
                    st.after1 && ply === 1 ? 1700 : 900,
                );
                return;
            }
            busy = true;
            c.status(st.win || 'Betul!', 'good');
            SFX.win();
            c.done();
        } else {
            wrong++;
            c.mistake();
            SFX.bad();
            b.flash(i);
            const rightSquareWrongPiece = !!res.promotion && !!st.line && st.line[ply]?.slice(0, 4) === res.from + res.to;
            c.status(
                g.isStalemate()
                    ? 'Stalemate! Itu seri, bukan menang. Raja lawan mesti ada langkah atau kena sah mati.'
                    : rightSquareWrongPiece
                      ? 'Hampir! Petak betul, tapi pilih buah lain untuk promosi.'
                      : st.wrongMsg || 'Belum betul. Cuba lagi.',
                'bad',
            );
            busy = true;
            c.later(() => {
                g.undo();
                b.quiet(g.fen());
                b.mark({ good: [], last: [], check: [] });
                busy = false;
                if (wrong >= 2) hint();
            }, 950);
        }
    }

    b.draggable((i) => !busy && g.turn() === user && g.get(nm(i) as Square)?.color === user);
}
