import { Chess, type Move, type Square } from 'chess.js';
import type { ArrowDrawSpec, BoardApi } from '../types';
import { createBoard } from '../core/board';
import { nm, sq } from '../core/squares';
import { sharedEngine, type StockfishEngine, type Evaluation } from '../engine/stockfish';
import { t } from '../i18n';

/** A free-form analysis board: move both sides, ask Stockfish for evaluation and best move. */
export class AnalysisRunner {
    private readonly root: HTMLElement;
    private readonly engine: StockfishEngine;
    private g = new Chess();
    private board!: BoardApi;
    private orient: 'w' | 'b' = 'w';
    private sel: Square | null = null;
    private busy = false;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private currentEval: Evaluation | null = null;

    constructor(root: HTMLElement, engine: StockfishEngine = sharedEngine()) {
        this.root = root;
        this.engine = engine;
        this.render();
        this.buildBoard();
        this.updateInfo();
        this.debouncedAnalyse();
    }

    destroy(): void {
        this.clearTimer();
    }

    private clearTimer(): void {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private $(sel: string): HTMLElement {
        return this.root.querySelector(sel) as HTMLElement;
    }

    private render(): void {
        this.root.innerHTML =
            '<div class="lesson-bar"><h2>' + t('Analisis papan bebas') + '</h2></div>' +
            '<div class="lesson analysis-layout"><div class="board-wrap"><div class="gbw"></div><p class="turn eval-turn"></p></div>' +
            '<div class="panel">' +
            '<div class="analysis-info">' +
            '<div class="eval-bar-wrap" aria-hidden="true"><div class="eval-bar"><span class="eval-white"></span><span class="eval-black"></span><span class="eval-needle"></span></div></div>' +
            '<p class="eval-score">' + t('Gerakkan buah dan Pak Kuda akan nilai kedudukan.') + '</p>' +
            '<p class="eval-best"></p>' +
            '</div>' +
            '<div class="actions analysis-actions">' +
            '<button class="ghost" type="button" data-act="reset">' + t('Mula semula') + '</button>' +
            '<button class="ghost" type="button" data-act="undo">' + t('Undur') + '</button>' +
            '<button class="ghost" type="button" data-act="flip">' + t('Terbalikkan papan') + '</button>' +
            '<button class="cta" type="button" data-act="best">' + t('Main langkah terbaik') + '</button>' +
            '</div>' +
            '<p class="analysis-help">' + t('Ketuk buah, kemudian ketuk petak untuk bergerak. Pusingan giliran ikut peraturan catur.') + '</p>' +
            '</div></div>';

        this.$('[data-act="reset"]').onclick = () => this.reset();
        this.$('[data-act="undo"]').onclick = () => this.undo();
        this.$('[data-act="flip"]').onclick = () => this.flip();
        this.$('[data-act="best"]').onclick = () => this.playBestMove();
    }

    private buildBoard(): void {
        this.board = createBoard(this.$('.gbw'), this.orient);
        this.board.setFen(this.g.fen());
        this.board.on((i) => this.onTap(i));
        this.board.draggable((i) => !this.busy && this.g.get(nm(i) as Square) !== null);
    }

    private reset(): void {
        this.clearTimer();
        this.g = new Chess();
        this.sel = null;
        this.board.setFen(this.g.fen());
        this.board.mark({ sel: null, dots: [], last: [], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
    }

    private undo(): void {
        this.clearTimer();
        this.g.undo();
        this.sel = null;
        this.board.quiet(this.g.fen());
        this.board.mark({ sel: null, dots: [], last: [], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
    }

    private flip(): void {
        this.orient = this.orient === 'w' ? 'b' : 'w';
        const fen = this.g.fen();
        this.buildBoard();
        this.board.setFen(fen);
        this.sel = null;
        this.board.mark({ sel: null, dots: [] });
    }

    private onTap(i: number): void {
        if (this.busy) return;
        const s = nm(i) as Square;
        const p = this.g.get(s);
        if (p) {
            this.sel = s;
            this.board.mark({ sel: i, dots: this.g.moves({ square: s, verbose: true }).map((m) => sq(m.to)) });
            return;
        }
        if (!this.sel) return;
        const legal = this.g.moves({ square: this.sel, verbose: true }).filter((m) => m.to === s);
        if (!legal.length) return;
        const from = this.sel;
        this.sel = null;
        if (legal[0].promotion) {
            this.busy = true;
            void this.board.choosePromotion(this.g.turn()).then((piece) => {
                this.busy = false;
                if (piece) this.move(from, s, piece);
                else this.board.mark({ sel: null, dots: [] });
            });
            return;
        }
        this.move(from, s);
    }

    private move(from: Square, to: Square, promotion?: string): Move | null {
        let m: Move | null = null;
        try {
            m = this.g.move({ from, to, promotion });
        } catch {
            return null;
        }
        this.board.applyMove(m);
        this.board.mark({ sel: null, dots: [], last: [sq(m.from), sq(m.to)], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
        this.debouncedAnalyse();
        return m;
    }

    private debouncedAnalyse(): void {
        this.clearTimer();
        this.timer = setTimeout(() => void this.analyse(), 400);
    }

    private async analyse(): Promise<void> {
        if (!this.engine.available) return;
        this.setScoreText(t('Pak Kuda sedang fikir…'));
        const fen = this.g.fen();
        const ev = await this.engine.analyse({ fen, depth: 14, timeoutMs: 5000 });
        if (fen !== this.g.fen()) return; // position changed while engine thought
        this.currentEval = ev;
        this.updateInfo();
    }

    private playBestMove(): void {
        if (!this.currentEval?.best) return;
        const u = this.currentEval.best;
        try {
            const m = this.g.move({ from: u.slice(0, 2) as Square, to: u.slice(2, 4) as Square, promotion: u[4] });
            this.board.applyMove(m);
            this.board.mark({ sel: null, dots: [], last: [sq(m.from), sq(m.to)], check: [] });
            this.currentEval = null;
            this.updateInfo();
            this.debouncedAnalyse();
        } catch {
            // illegal best move (should not happen): ignore
        }
    }

    private updateInfo(): void {
        if (!this.engine.available) {
            this.setScoreText(t('Enjin catur tidak dapat dimuatkan. Awak masih boleh gerakkan buah.'));
            this.setBestText('');
            this.setEvalBar(null);
            return;
        }
        if (!this.currentEval) {
            this.setTurnText();
            this.setEvalBar(null);
            return;
        }

        const ev = this.currentEval;
        const scoreText = formatEval(ev);
        this.setScoreText(t('Penilaian Pak Kuda: :score', { score: scoreText }));

        let bestText = '';
        if (ev.best) {
            try {
                const best = new Chess(this.g.fen()).move({ from: ev.best.slice(0, 2) as Square, to: ev.best.slice(2, 4) as Square, promotion: ev.best[4] });
                bestText = t('Cadangan: :san', { san: best.san });
                const arrow: ArrowDrawSpec = { from: sq(best.from), to: sq(best.to), kind: 'path' };
                this.board.arrows([arrow]);
            } catch {
                bestText = '';
            }
        }
        this.setBestText(bestText);
        this.setEvalBar(ev);
        this.setTurnText();
    }

    private setTurnText(): void {
        this.$('.eval-turn').textContent = this.g.turn() === 'w' ? t('Putih untuk bergerak') : t('Hitam untuk bergerak');
    }

    private setScoreText(text: string): void {
        this.$('.eval-score').textContent = text;
    }

    private setBestText(text: string): void {
        this.$('.eval-best').textContent = text;
    }

    private setEvalBar(ev: Evaluation | null): void {
        const wrap = this.$('.eval-bar');
        if (!ev || (ev.cp === null && ev.mate === null)) {
            wrap.classList.add('eval-unknown');
            return;
        }
        wrap.classList.remove('eval-unknown');

        let value = 0; // -1 = black much better, +1 = white much better
        if (ev.mate !== null) {
            value = ev.mate > 0 ? 0.95 : -0.95;
        } else if (ev.cp !== null) {
            value = Math.max(-1, Math.min(1, ev.cp / 600));
        }
        const pct = ((value + 1) / 2) * 100;
        this.$('.eval-needle').style.left = pct + '%';
        this.$('.eval-white').style.width = pct + '%';
        this.$('.eval-black').style.width = 100 - pct + '%';
    }
}

function formatEval(ev: Evaluation): string {
    if (ev.mate !== null) {
        return ev.mate > 0 ? '#+' + ev.mate : '#-' + Math.abs(ev.mate);
    }
    if (ev.cp !== null) {
        const v = ev.cp / 100;
        return (v > 0 ? '+' : '') + v.toFixed(1);
    }
    return '?';
}
