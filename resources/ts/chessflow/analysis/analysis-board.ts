import { Chess, type Move, type Square } from 'chess.js';

const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
import type { ArrowDrawSpec, BoardApi } from '../types';
import { createBoard } from '../core/board';
import { nm, sq } from '../core/squares';
import { sharedEngine, type StockfishEngine, type Evaluation, type EngineLine } from '../engine/stockfish';
import { t } from '../i18n';

const MAX_LINE_MOVES = 6;

/** A free-form analysis board: move both sides, ask Stockfish for evaluation, lines and best moves. */
export class AnalysisRunner {
    private readonly root: HTMLElement;
    private readonly engine: StockfishEngine;
    private g = new Chess();
    private startFen = START_FEN;
    private moves: Move[] = [];
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

        const initial = this.root.dataset.fen?.trim();
        if (initial) this.applyInput(initial, false);
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
            '<ol class="engine-lines"></ol>' +
            '</div>' +
            '<div class="actions analysis-actions">' +
            '<button class="ghost" type="button" data-act="reset">' + t('Mula semula') + '</button>' +
            '<button class="ghost" type="button" data-act="undo">' + t('Undur') + '</button>' +
            '<button class="ghost" type="button" data-act="flip">' + t('Terbalikkan papan') + '</button>' +
            '<button class="ghost" type="button" data-act="copy">' + t('Salin URL') + '</button>' +
            '<button class="cta" type="button" data-act="best">' + t('Main langkah terbaik') + '</button>' +
            '</div>' +
            '<div class="analysis-moves"><small>' + t('Langkah') + '</small><ol></ol>' +
            '<div class="nav-btns">' +
            '<button type="button" class="ghost" data-act="first" title="' + t('Mula') + '">|←</button>' +
            '<button type="button" class="ghost" data-act="prev" title="' + t('Undur satu') + '">←</button>' +
            '<button type="button" class="ghost" data-act="next" title="' + t('Maju satu') + '">→</button>' +
            '<button type="button" class="ghost" data-act="last" title="' + t('Akhir') + '">→|</button>' +
            '</div></div>' +
            '<div class="analysis-import">' +
            '<label for="fen-pgn">' + t('Muat FEN atau PGN:') + '</label>' +
            '<textarea id="fen-pgn" data-input="fen-pgn" rows="2" placeholder="rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"></textarea>' +
            '<button class="ghost" type="button" data-act="load">' + t('Muatkan') + '</button>' +
            '<span class="load-status" aria-live="polite"></span>' +
            '</div>' +
            '<p class="analysis-help">' + t('Ketuk buah, kemudian ketuk petak untuk bergerak. Pusingan giliran ikut peraturan catur.') + '</p>' +
            '</div></div>';

        this.$('[data-act="reset"]').onclick = () => this.reset();
        this.$('[data-act="undo"]').onclick = () => this.undo();
        this.$('[data-act="flip"]').onclick = () => this.flip();
        this.$('[data-act="best"]').onclick = () => this.playBestMove();
        this.$('[data-act="copy"]').onclick = () => this.copyUrl();
        this.$('[data-act="first"]').onclick = () => this.goToPly(0);
        this.$('[data-act="prev"]').onclick = () => this.goToPly(this.g.history().length - 1);
        this.$('[data-act="next"]').onclick = () => this.goToPly(this.g.history().length + 1);
        this.$('[data-act="last"]').onclick = () => this.goToPly(this.moves.length);
        this.$('[data-act="load"]').onclick = () => this.loadFromInput();
        const input = this.$('[data-input="fen-pgn"]') as HTMLTextAreaElement;
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                this.loadFromInput();
            }
        });
    }

    private buildBoard(): void {
        this.board = createBoard(this.$('.gbw'), this.orient);
        this.board.setFen(this.g.fen());
        this.board.on((i) => this.onTap(i));
        this.board.draggable((i) => !this.busy && this.g.get(nm(i) as Square) !== null);
    }

    private reset(): void {
        this.clearTimer();
        this.startFen = START_FEN;
        this.moves = [];
        this.g = new Chess();
        this.sel = null;
        this.board.setFen(this.g.fen());
        this.board.mark({ sel: null, dots: [], last: [], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
        this.syncUrl();
    }

    private undo(): void {
        this.goToPly(this.g.history().length - 1);
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
        if (!this.sel) {
            if (p) {
                this.sel = s;
                this.board.mark({ sel: i, dots: this.g.moves({ square: s, verbose: true }).map((m) => sq(m.to)) });
            }
            return;
        }
        const legal = this.g.moves({ square: this.sel, verbose: true }).filter((m) => m.to === s);
        if (!legal.length) {
            if (p) {
                this.sel = s;
                this.board.mark({ sel: i, dots: this.g.moves({ square: s, verbose: true }).map((m) => sq(m.to)) });
            }
            return;
        }
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
        const ply = this.g.history().length - 1;
        this.moves = this.moves.slice(0, ply);
        this.moves.push(m);
        this.board.applyMove(m);
        this.board.mark({ sel: null, dots: [], last: [sq(m.from), sq(m.to)], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
        this.syncUrl();
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
        const ev = await this.engine.analyse({ fen, depth: 14, timeoutMs: 5000, lines: 3 });
        if (fen !== this.g.fen()) return;
        this.currentEval = ev;
        this.updateInfo();
    }

    private playBestMove(): void {
        if (!this.currentEval?.best) return;
        const u = this.currentEval.best;
        try {
            const m = this.g.move({ from: u.slice(0, 2) as Square, to: u.slice(2, 4) as Square, promotion: u[4] });
            const ply = this.g.history().length - 1;
            this.moves = this.moves.slice(0, ply);
            this.moves.push(m);
            this.board.applyMove(m);
            this.board.mark({ sel: null, dots: [], last: [sq(m.from), sq(m.to)], check: [] });
            this.currentEval = null;
            this.updateInfo();
            this.syncUrl();
            this.debouncedAnalyse();
        } catch {
            // illegal best move (should not happen): ignore
        }
    }

    private updateInfo(): void {
        this.setTurnText();
        this.renderMoves();
        if (!this.engine.available) {
            this.setScoreText(t('Enjin catur tidak dapat dimuatkan. Awak masih boleh gerakkan buah.'));
            this.setBestText('');
            this.renderLines(null);
            this.setEvalBar(null);
            return;
        }
        if (!this.currentEval) {
            this.setScoreText(t('Gerakkan buah dan Pak Kuda akan nilai kedudukan.'));
            this.setBestText('');
            this.renderLines(null);
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
        this.renderLines(ev);
        this.setEvalBar(ev);
    }

    private copyUrl(): void {
        if (typeof location === 'undefined') return;
        const url = location.href;
        void navigator.clipboard.writeText(url).then(
            () => this.setLoadStatus(t('URL disalin.')),
            () => this.setLoadStatus(t('Gagal salin URL.')),
        );
    }

    private goToPly(ply: number): void {
        const target = Math.max(0, Math.min(ply, this.moves.length));
        if (target === this.g.history().length && this.g.fen() === this.currentPositionFen(target)) return;
        this.clearTimer();
        this.g = new Chess(this.startFen);
        for (let i = 0; i < target; i++) this.g.move(this.moves[i].san);
        this.sel = null;
        this.board.setFen(this.g.fen());
        this.board.mark({ sel: null, dots: [], last: target > 0 ? [sq(this.moves[target - 1].from), sq(this.moves[target - 1].to)] : [], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
        this.syncUrl();
        this.debouncedAnalyse();
    }

    private currentPositionFen(ply: number): string {
        const temp = new Chess(this.startFen);
        for (let i = 0; i < ply; i++) temp.move(this.moves[i].san);
        return temp.fen();
    }

    private renderMoves(): void {
        const ol = this.$('.analysis-moves ol');
        const h = this.g.history();
        let out = '';
        for (let i = 0; i < this.moves.length; i += 2) {
            const white = this.moves[i];
            const black = this.moves[i + 1];
            const activeW = i + 1 === h.length;
            const activeB = i + 2 === h.length;
            out +=
                '<li>' +
                '<button type="button" class="move-btn' + (activeW ? ' active' : '') + '" data-ply="' + (i + 1) + '">' +
                white.san +
                '</button>' +
                (black
                    ? '<button type="button" class="move-btn' + (activeB ? ' active' : '') + '" data-ply="' + (i + 2) + '">' + black.san + '</button>'
                    : '') +
                '</li>';
        }
        ol.innerHTML = out;
        ol.querySelectorAll<HTMLElement>('.move-btn').forEach((b) => (b.onclick = () => this.goToPly(Number(b.dataset.ply))));
        const wrap = this.$('.analysis-moves');
        wrap.hidden = this.moves.length === 0;
    }

    private renderLines(ev: Evaluation | null): void {
        const box = this.$('.engine-lines');
        if (!ev?.lines?.length) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML =
            '<small>' + t('Line terbaik') + '</small>' +
            ev.lines
                .slice(0, 3)
                .map((line, k) => {
                    const score = formatLineScore(line);
                    const text = formatLine(this.g, line, MAX_LINE_MOVES);
                    return '<li><span class="line-score">' + (k + 1) + '. ' + score + '</span><span class="line-pv">' + text + '</span></li>';
                })
                .join('');
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

    private loadFromInput(): void {
        const raw = (this.$('[data-input="fen-pgn"]') as HTMLTextAreaElement).value;
        this.applyInput(raw, true);
    }

    private applyInput(raw: string, shouldSyncUrl: boolean): boolean {
        const text = raw.trim();
        if (!text) return false;
        try {
            if (text.includes('/')) {
                const next = new Chess();
                next.load(text);
                this.setGame(next, { startFen: next.fen(), moves: [], shouldSyncUrl });
            } else {
                const next = new Chess();
                next.loadPgn(text);
                this.setGame(next, { startFen: START_FEN, moves: next.history({ verbose: true }), shouldSyncUrl });
            }
            this.setLoadStatus(t('Kedudukan dimuatkan.'));
            return true;
        } catch {
            this.setLoadStatus(t('FEN atau PGN tidak sah. Semak semula teks tersebut.'));
            return false;
        }
    }

    private setGame(next: Chess, opts?: { startFen?: string; moves?: Move[]; shouldSyncUrl?: boolean }): void {
        this.clearTimer();
        this.startFen = opts?.startFen ?? next.fen();
        this.moves = opts?.moves ?? [];
        this.g = next;
        this.sel = null;
        this.board.setFen(this.g.fen());
        this.board.mark({ sel: null, dots: [], last: [], check: [] });
        this.board.arrows([]);
        this.currentEval = null;
        this.updateInfo();
        if (opts?.shouldSyncUrl) this.syncUrl();
        this.debouncedAnalyse();
    }

    private syncUrl(): void {
        if (typeof location === 'undefined') return;
        const url = new URL(location.href);
        if (this.g.fen() === START_FEN) {
            url.searchParams.delete('fen');
        } else {
            url.searchParams.set('fen', this.g.fen());
        }
        history.replaceState({}, '', url.toString());
    }

    private setLoadStatus(text: string): void {
        const el = this.$('.load-status');
        el.textContent = text;
        if (text) setTimeout(() => (el.textContent = ''), 3000);
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

function formatLineScore(line: EngineLine): string {
    if (line.mate !== null) {
        return line.mate > 0 ? '#+' + line.mate : '#-' + Math.abs(line.mate);
    }
    if (line.cp !== null) {
        const v = line.cp / 100;
        return (v > 0 ? '+' : '') + v.toFixed(1);
    }
    return '?';
}

function formatLine(g: Chess, line: EngineLine, maxMoves: number): string {
    let out = '';
    let moveNo = Math.floor(g.history().length / 2) + 1;
    let whiteTurn = g.turn() === 'w';
    const temp = new Chess(g.fen());
    for (let i = 0; i < Math.min(line.pv.length, maxMoves); i++) {
        const uci = line.pv[i];
        try {
            const m = temp.move({ from: uci.slice(0, 2) as Square, to: uci.slice(2, 4) as Square, promotion: uci[4] });
            if (whiteTurn) {
                if (out) out += ' ';
                out += moveNo + '. ' + m.san;
            } else {
                out += ' ' + m.san;
                moveNo++;
            }
            whiteTurn = !whiteTurn;
        } catch {
            break;
        }
    }
    if (line.pv.length > maxMoves) out += ' …';
    return out || '-';
}
