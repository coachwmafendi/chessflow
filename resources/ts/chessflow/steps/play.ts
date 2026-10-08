import { Chess, type Square } from 'chess.js';
import type { BoardApi, PlayStep, StepContext } from '../types';
import { nm, sq } from '../core/squares';
import { PNAME } from '../core/names';
import { SFX } from '../core/sound';
import { bot } from '../engine/simple-bot';
import { base } from './base';

export function play(st: PlayStep, c: StepContext): void {
    let g: Chess;
    let b: BoardApi;
    let user: 'w' | 'b';
    let n = 0;
    let over = false;
    let sel: Square | null = null;

    const cnt = () => c.counter('Langkah: ' + n + '/' + st.maxMoves);
    const fail = (msg: string) => {
        over = true;
        c.mistake();
        SFX.bad();
        c.status(msg + ' Tekan "Cuba lagi".', 'bad');
    };
    const userPawns = () => g.board().flat().filter((x) => x && x.color === user && x.type === 'p').length;

    function onTap(i: number): void {
        if (over || g.turn() !== user) return;
        const square = nm(i) as Square;
        const p = g.get(square);
        if (p && p.color === user) {
            sel = square;
            b.mark({ sel: i, dots: g.moves({ square, verbose: true }).map((m) => sq(m.to)) });
            return;
        }
        if (!sel) return;
        const legal = g.moves({ square: sel, verbose: true }).filter((m) => m.to === square);
        if (!legal.length) return;

        const res = g.move({ from: sel, to: square, promotion: legal[0].promotion ? 'q' : undefined });
        b.applyMove(res);
        b.mark({ sel: null, dots: [] });
        sel = null;
        n++;
        cnt();
        SFX.move();
        c.later(() => b.quiet(g.fen()), 420);

        const won =
            (st.goal === 'mate' && g.isCheckmate()) ||
            (st.goal === 'promote' && res.flags.includes('p')) ||
            (st.goal === 'captureQ' && res.captured === 'q');
        if (won) {
            over = true;
            b.mark({ good: [sq(res.to)] });
            c.status(st.win, 'good');
            SFX.win();
            c.done();
            return;
        }
        if (g.isStalemate()) return fail('Stalemate! Raja hitam tiada langkah tapi tidak kena sah. Itu seri.');
        if (g.isDraw()) return fail('Permainan seri.');
        if (n >= st.maxMoves) return fail('Sudah ' + st.maxMoves + ' langkah.');

        c.later(() => {
            const m = bot(g, st.bot, user);
            if (!m) return;
            const r = g.move(m);
            b.applyMove(r);
            c.later(() => b.quiet(g.fen()), 420);
            if (st.goal === 'promote' && !userPawns()) return fail('Raja hitam tangkap bidak awak!');
            if (st.goal === 'mate' && r.captured && r.captured !== 'p') {
                return fail('Alamak, ' + PNAME[r.captured.toUpperCase()] + ' awak dimakan! Jauhkan buah daripada Raja lawan.');
            }
            if (st.goal === 'captureQ' && n >= st.maxMoves) return fail('Menteri hitam terlepas.');
            c.status(g.isCheck() ? 'Sah!' : '', 'info');
        }, 550);
    }

    const reset = () => {
        b = base(st, c);
        g = new Chess(st.fen);
        user = g.turn();
        n = 0;
        over = false;
        sel = null;
        cnt();
        c.status('', '');
        b.on(onTap);
    };

    c.actions([{ label: 'Cuba lagi', fn: reset }]);
    reset();
}
