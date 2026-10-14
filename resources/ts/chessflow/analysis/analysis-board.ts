import { Chess, type Move, type Square } from 'chess.js';
import type { ArrowDrawSpec, BoardApi } from '../types';
import { createBoard } from '../core/board';
import { nm, pathFor, sq } from '../core/squares';
import { analysisEngine, sharedEngine, type StockfishEngine, type Evaluation, type EngineLine } from '../engine/stockfish';
import { t } from '../i18n';

export const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
const MAX_LINE_MOVES = 6;
const ENGINE_LINES = 3;

const ICON_SVG = '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
const ICON_FIRST = '<span class="nav-ico-wrap">' + ICON_SVG + '<path d="M17 5l-9 7 9 7"/><path d="M5 5v14"/></svg></span>';
const ICON_PREV = '<span class="nav-ico-wrap">' + ICON_SVG + '<path d="M15 5l-7 7 7 7"/></svg></span>';
const ICON_NEXT = '<span class="nav-ico-wrap">' + ICON_SVG + '<path d="M9 5l7 7-7 7"/></svg></span>';
const ICON_LAST = '<span class="nav-ico-wrap">' + ICON_SVG + '<path d="M7 5l9 7-9 7"/><path d="M19 5v14"/></svg></span>';

/** A score from White's point of view (Stockfish reports it for the side to move). */
export interface WhiteScore {
    cp: number | null;
    mate: number | null;
}

export function toWhite(score: { cp: number | null; mate: number | null }, turn: 'w' | 'b'): WhiteScore {
    const k = turn === 'w' ? 1 : -1;
    return { cp: score.cp === null ? null : score.cp * k, mate: score.mate === null ? null : score.mate * k };
}

/** "+1.3", "-0.4", "#3" (White mates in 3), "#-2" (Black mates in 2). */
export function formatScore(s: WhiteScore): string {
    if (s.mate !== null) return s.mate > 0 ? '#' + s.mate : '#-' + Math.abs(s.mate);
    if (s.cp !== null) {
        const v = s.cp / 100;
        return (v > 0 ? '+' : '') + v.toFixed(1);
    }
    return '?';
}

/** The score in words a beginner can follow. */
export function verdict(s: WhiteScore): string {
    if (s.mate !== null) {
        if (s.mate === 0) return '';
        return s.mate > 0 ? t('Putih boleh sah mati dalam :n langkah.', { n: s.mate }) : t('Hitam boleh sah mati dalam :n langkah.', { n: -s.mate });
    }
    if (s.cp === null) return '';
    const a = Math.abs(s.cp);
    const side = s.cp > 0 ? t('Putih') : t('Hitam');
    if (a < 50) return t('Seimbang. Kedua-dua pihak ada peluang.');
    if (a < 150) return t(':side lebih baik sedikit.', { side });
    if (a < 400) return t(':side lebih baik.', { side });
    return t(':side jauh lebih baik.', { side });
}

/** Engine line in SAN with move numbers taken from the position, e.g. "3… Nc6 4. Nc3 Nf6". */
export function formatLine(fen: string, pv: string[], maxMoves: number): string {
    const temp = new Chess(fen);
    let moveNo = Number(fen.split(' ')[5]) || 1;
    let out = '';
    for (let i = 0; i < Math.min(pv.length, maxMoves); i++) {
        const u = pv[i];
        let m: Move;
        try {
            m = temp.move({ from: u.slice(0, 2), to: u.slice(2, 4), promotion: u[4] });
        } catch {
            break;
        }
        if (m.color === 'w') out += (out ? ' ' : '') + moveNo + '. ' + m.san;
        else {
            out += (out ? ' ' : moveNo + '… ') + m.san;
            moveNo++;
        }
    }
    if (pv.length > maxMoves && out) out += ' …';
    return out || '–';
}

/** A FEN has a "/" in its first field and no move numbers; anything else is treated as PGN. */
function isFen(text: string): boolean {
    return !text.includes('[') && text.split(/\s+/)[0].split('/').length === 8;
}

/** A free analysis board: move both sides, step through the moves, and ask Stockfish for the score and best lines. */
export class AnalysisRunner {
    private readonly root: HTMLElement;
    private engine: StockfishEngine;
    /** Position at `ply`. */
    private g = new Chess();
    private startFen = START_FEN;
    /** All moves from the start position; the board shows the first `ply` of them. */
    private moves: Move[] = [];
    private ply = 0;
    private board!: BoardApi;
    private orient: 'w' | 'b' = 'w';
    private sel: Square | null = null;
    private busy = false;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private statusTimer: ReturnType<typeof setTimeout> | null = null;
    private currentEval: Evaluation | null = null;
    private readonly onKey = (e: KeyboardEvent): void => {
        if (e.metaKey || e.ctrlKey || e.altKey || e.defaultPrevented) return;
        const el = e.target instanceof HTMLElement ? e.target : null;
        if (el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.closest('.modal, .promo'))) return;
        const go: Record<string, number> = { Home: 0, End: this.moves.length, ArrowLeft: this.ply - 1, ArrowRight: this.ply + 1 };
        if (!(e.key in go)) return;
        e.preventDefault();
        this.goToPly(go[e.key]);
    };

    constructor(root: HTMLElement, engine: StockfishEngine = analysisEngine()) {
        this.root = root;
        this.engine = engine;
        this.render();
        this.buildBoard();

        // Shared links: ?fen=<start position>&moves=e4 e5 Nf3&ply=2 (all optional).
        const d = this.root.dataset;
        if (!this.loadFromUrl(d.fen?.trim() || '', d.moves?.trim() || '', d.ply)) {
            this.updateInfo();
            this.debouncedAnalyse();
        }
    }

    destroy(): void {
        this.clearTimer();
        if (this.statusTimer) clearTimeout(this.statusTimer);
        document.removeEventListener('keydown', this.onKey);
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
            '<div class="eval-bar-wrap" aria-hidden="true"><div class="eval-bar eval-unknown"><span class="eval-white"></span><span class="eval-black"></span><span class="eval-needle"></span></div></div>' +
            '<p class="eval-score" aria-live="polite"></p>' +
            '<p class="eval-verdict"></p>' +
            '<p class="eval-best"></p>' +
            '<div class="engine-lines"></div>' +
            '<p class="engine-name"></p>' +
            '</div>' +
            '<div class="actions analysis-actions">' +
            '<button class="ghost" type="button" data-act="reset">' + t('Mula semula') + '</button>' +
            '<button class="ghost" type="button" data-act="undo">' + t('Undur') + '</button>' +
            '<button class="ghost" type="button" data-act="flip">' + t('Terbalikkan papan') + '</button>' +
            '<button class="ghost" type="button" data-act="copy">' + t('Salin pautan') + '</button>' +
            '<button class="cta" type="button" data-act="best">' + t('Main langkah terbaik') + '</button>' +
            '</div>' +
            '<div class="analysis-moves"><small>' + t('Langkah') + '</small><ol></ol>' +
            '<div class="nav-btns">' +
            '<button type="button" class="ghost" data-act="first" aria-label="' + t('Mula') + '" title="' + t('Mula') + ' (Home)">' + ICON_FIRST + '</button>' +
            '<button type="button" class="ghost" data-act="prev" aria-label="' + t('Undur satu') + '" title="' + t('Undur satu') + ' (←)">' + ICON_PREV + '</button>' +
            '<button type="button" class="ghost" data-act="next" aria-label="' + t('Maju satu') + '" title="' + t('Maju satu') + ' (→)">' + ICON_NEXT + '</button>' +
            '<button type="button" class="ghost" data-act="last" aria-label="' + t('Akhir') + '" title="' + t('Akhir') + ' (End)">' + ICON_LAST + '</button>' +
            '</div></div>' +
            '<div class="analysis-import">' +
            '<label for="fen-pgn">' + t('Muat FEN atau PGN:') + '</label>' +
            '<textarea id="fen-pgn" data-input="fen-pgn" rows="2" placeholder="' + START_FEN + '"></textarea>' +
            '<button class="ghost" type="button" data-act="load">' + t('Muatkan') + '</button>' +
            '<span class="load-status" aria-live="polite"></span>' +
            '</div>' +
            '<p class="analysis-help">' + t('Gerakkan buah untuk kedua-dua pihak (ketuk atau seret). Tekan satu barisan Pak Kuda untuk main langkah pertamanya.') + '</p>' +
            '</div></div>';

        this.$('[data-act="reset"]').onclick = () => this.reset();
        this.$('[data-act="undo"]').onclick = () => this.undo();
        this.$('[data-act="flip"]').onclick = () => this.flip();
        this.$('[data-act="best"]').onclick = () => this.playBestMove();
        this.$('[data-act="copy"]').onclick = () => this.copyUrl();
        this.$('[data-act="first"]').onclick = () => this.goToPly(0);
        this.$('[data-act="prev"]').onclick = () => this.goToPly(this.ply - 1);
        this.$('[data-act="next"]').onclick = () => this.goToPly(this.ply + 1);
        this.$('[data-act="last"]').onclick = () => this.goToPly(this.moves.length);
        this.$('[data-act="load"]').onclick = () => this.loadFromInput();
        this.$('.engine-lines').onclick = (e) => {
            const b = (e.target as HTMLElement).closest<HTMLElement>('[data-line]');
            const line = b && this.currentEval?.lines?.[Number(b.dataset.line)];
            if (line?.pv[0]) this.playUci(line.pv[0]);
        };
        document.addEventListener('keydown', this.onKey);
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
        this.board.draggable((i) => !this.busy && !!this.g.get(nm(i) as Square));
    }

    /** Last move, check and the engine's best-move arrow for the current position. */
    private drawMarks(): void {
        const last = this.ply > 0 ? this.moves[this.ply - 1] : null;
        const king = this.g.inCheck() ? this.g.findPiece({ type: 'k', color: this.g.turn() })[0] : null;
        this.board.mark({ sel: null, dots: [], last: last ? [sq(last.from), sq(last.to)] : [], check: king ? [sq(king)] : [] });
        // Knights get an L-shaped arrow, like in the lessons.
        const best = this.currentEval?.best;
        const piece = best ? this.g.get(best.slice(0, 2) as Square) : null;
        const arrow: ArrowDrawSpec[] = best && piece ? [pathFor(piece.type.toUpperCase(), sq(best.slice(0, 2)), sq(best.slice(2, 4)))] : [];
        this.board.arrows(arrow);
    }

    private reset(): void {
        this.setGame(START_FEN, [], 0);
        this.syncUrl();
    }

    private undo(): void {
        this.goToPly(this.ply - 1);
    }

    private flip(): void {
        this.orient = this.orient === 'w' ? 'b' : 'w';
        this.sel = null;
        this.buildBoard();
        this.drawMarks();
    }

    private onTap(i: number): void {
        if (this.busy) return;
        const s = nm(i) as Square;
        const p = this.g.get(s);
        const pick = (): void => {
            this.sel = p && p.color === this.g.turn() ? s : null;
            this.board.mark({ sel: this.sel ? i : null, dots: this.sel ? this.g.moves({ square: s, verbose: true }).map((m) => sq(m.to)) : [] });
        };
        if (!this.sel) return pick();
        const legal = this.g.moves({ square: this.sel, verbose: true }).filter((m) => m.to === s);
        if (!legal.length) return pick();
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

    /** Play a move at the current ply. A new move replaces the moves after it; replaying the next move keeps them. */
    private move(from: string, to: string, promotion?: string): Move | null {
        let m: Move;
        try {
            m = this.g.move({ from, to, promotion });
        } catch {
            return null;
        }
        const next = this.moves[this.ply];
        if (!next || next.san !== m.san) this.moves = [...this.moves.slice(0, this.ply), m];
        this.ply++;
        this.board.applyMove(m);
        this.afterPositionChange();
        return m;
    }

    private playUci(u: string): void {
        this.move(u.slice(0, 2), u.slice(2, 4), u[4]);
    }

    private playBestMove(): void {
        if (this.currentEval?.best) this.playUci(this.currentEval.best);
    }

    private afterPositionChange(): void {
        this.sel = null;
        this.currentEval = null;
        this.drawMarks();
        this.updateInfo();
        this.syncUrl();
        this.debouncedAnalyse();
    }

    private debouncedAnalyse(): void {
        this.clearTimer();
        if (this.g.isGameOver()) return;
        this.timer = setTimeout(() => void this.analyse(), 300);
    }

    private async analyse(): Promise<void> {
        if (!this.engine.available) return;
        this.$('.eval-score').textContent = t('Pak Kuda sedang fikir…');
        const fen = this.g.fen();
        let ev = await this.engine.analyse({ fen, depth: 14, timeoutMs: 5000, lines: ENGINE_LINES });
        // Stockfish 19 needs a newer browser (e.g. iOS 16+); fall back to the Stockfish 10 used everywhere else.
        if (!this.engine.available && this.engine !== sharedEngine()) {
            this.engine = sharedEngine();
            ev = await this.engine.analyse({ fen, depth: 14, timeoutMs: 5000, lines: ENGINE_LINES });
        }
        if (fen !== this.g.fen()) return;
        this.currentEval = ev;
        this.drawMarks();
        this.updateInfo();
    }

    private updateInfo(): void {
        this.$('.engine-name').textContent = this.engine.available ? t('Enjin: :name', { name: this.engine.name }) : '';
        this.$('.eval-turn').textContent = this.g.turn() === 'w' ? t('Putih untuk bergerak') : t('Hitam untuk bergerak');
        this.renderMoves();
        (this.$('[data-act="best"]') as HTMLButtonElement).disabled = !this.currentEval?.best;

        const over = this.gameOverText();
        if (over) return this.showScore(over.text, '', '', over.white);
        if (!this.engine.available) {
            return this.showScore(t('Enjin catur tidak dapat dimuatkan. Awak masih boleh gerakkan buah.'), '', '', null);
        }
        const ev = this.currentEval;
        if (!ev || (ev.cp === null && ev.mate === null)) {
            return this.showScore(t('Gerakkan buah dan Pak Kuda akan nilai kedudukan.'), '', '', null);
        }
        const score = toWhite(ev, this.g.turn());
        let best = '';
        if (ev.best) {
            try {
                best = t('Cadangan: :san', { san: new Chess(this.g.fen()).move({ from: ev.best.slice(0, 2), to: ev.best.slice(2, 4), promotion: ev.best[4] }).san });
            } catch {
                // stale or illegal suggestion: show nothing
            }
        }
        this.showScore(t('Penilaian Pak Kuda: :score', { score: formatScore(score) }), verdict(score), best, score);
    }

    /** Checkmate and draws are decided by the rules, not the engine. */
    private gameOverText(): { text: string; white: WhiteScore } | null {
        const g = this.g;
        if (g.isCheckmate()) {
            const whiteWon = g.turn() === 'b';
            return { text: whiteWon ? t('Sah mati. Putih menang.') : t('Sah mati. Hitam menang.'), white: { cp: null, mate: whiteWon ? 1 : -1 } };
        }
        if (g.isStalemate()) return { text: t('Stalemate. Permainan seri.'), white: { cp: 0, mate: null } };
        if (g.isDraw()) return { text: t('Permainan seri.'), white: { cp: 0, mate: null } };
        return null;
    }

    private showScore(score: string, verdictText: string, best: string, white: WhiteScore | null): void {
        this.$('.eval-score').textContent = score;
        this.$('.eval-verdict').textContent = verdictText;
        this.$('.eval-best').textContent = best;
        this.renderLines(white ? this.currentEval : null);
        this.setEvalBar(white);
    }

    private renderLines(ev: Evaluation | null): void {
        const box = this.$('.engine-lines');
        const lines = ev?.lines?.slice(0, ENGINE_LINES) ?? [];
        if (!lines.length) {
            box.innerHTML = '';
            return;
        }
        const fen = this.g.fen();
        box.innerHTML =
            '<small>' + t('Barisan terbaik Pak Kuda') + '</small><ol>' +
            lines
                .map(
                    (line: EngineLine, k) =>
                        '<li><button type="button" data-line="' + k + '"><span class="line-score">' + formatScore(toWhite(line, this.g.turn())) + '</span>' +
                        '<span class="line-pv">' + formatLine(fen, line.pv, MAX_LINE_MOVES) + '</span></button></li>',
                )
                .join('') +
            '</ol>';
    }

    private renderMoves(): void {
        const ol = this.$('.analysis-moves ol');
        const firstNo = Number(this.startFen.split(' ')[5]) || 1;
        const blackFirst = this.startFen.split(' ')[1] === 'b';
        let out = '';
        this.moves.forEach((m, i) => {
            const n = i + 1;
            const btn = '<button type="button" class="move-btn' + (n === this.ply ? ' active' : '') + '" data-ply="' + n + '"' + (n === this.ply ? ' aria-current="true"' : '') + '>' + m.san + '</button>';
            const no = firstNo + Math.floor((i + (blackFirst ? 1 : 0)) / 2);
            if (m.color === 'w') out += '<li><span class="move-no">' + no + '.</span>' + btn;
            else out += (i === 0 ? '<li><span class="move-no">' + no + '…</span>' : '') + btn + '</li>';
        });
        ol.innerHTML = out;
        ol.querySelectorAll<HTMLElement>('.move-btn').forEach((b) => (b.onclick = () => this.goToPly(Number(b.dataset.ply))));
        this.$('.analysis-moves').hidden = this.moves.length === 0;
        ol.querySelector('.active')?.scrollIntoView?.({ block: 'nearest' });
    }

    private goToPly(ply: number): void {
        const target = Math.max(0, Math.min(ply, this.moves.length));
        if (target === this.ply) return;
        this.g = new Chess(this.startFen);
        for (let i = 0; i < target; i++) this.g.move(this.moves[i].san);
        this.ply = target;
        this.board.setFen(this.g.fen());
        this.afterPositionChange();
    }

    private setGame(startFen: string, moves: Move[], ply: number): void {
        this.clearTimer();
        this.startFen = startFen;
        this.moves = moves;
        this.g = new Chess(startFen);
        this.board.setFen(this.g.fen());
        this.ply = 0;
        if (ply > 0) this.goToPly(ply);
        else this.afterPositionChange();
    }

    private loadFromInput(): void {
        this.applyInput((this.$('[data-input="fen-pgn"]') as HTMLTextAreaElement).value, true);
    }

    /** A FEN (has "/") or a PGN, which may start from its own [FEN "…"] position. */
    private applyInput(raw: string, shouldSyncUrl: boolean): boolean {
        const text = raw.trim();
        if (!text) return false;
        try {
            const next = new Chess();
            if (isFen(text)) {
                next.load(text);
                this.setGame(next.fen(), [], 0);
            } else {
                next.loadPgn(text);
                const moves = next.history({ verbose: true });
                this.setGame(next.getHeaders().FEN || START_FEN, moves, moves.length);
            }
            if (shouldSyncUrl) this.syncUrl();
            this.setLoadStatus(t('Kedudukan dimuatkan.'));
            return true;
        } catch {
            this.setLoadStatus(t('FEN atau PGN tidak sah. Semak semula teks tersebut.'));
            return false;
        }
    }

    private loadFromUrl(fen: string, moves: string, ply?: string): boolean {
        if (!fen && !moves) return false;
        try {
            const g = new Chess(fen || START_FEN);
            const start = g.fen();
            const list = moves ? moves.split(/\s+/).map((san) => g.move(san)) : [];
            const at = ply === undefined || ply === '' ? list.length : Math.max(0, Math.min(Number(ply) || 0, list.length));
            this.setGame(start, list, at);
            return true;
        } catch {
            this.setLoadStatus(t('Pautan ini ada kedudukan yang tidak sah.'));
            return false;
        }
    }

    /** Keep the whole line in the address bar, so "Copy link" and reloads keep the moves too. */
    private syncUrl(): void {
        if (typeof location === 'undefined') return;
        const url = new URL(location.href);
        const set = (k: string, v: string | null) => (v ? url.searchParams.set(k, v) : url.searchParams.delete(k));
        set('fen', this.startFen === START_FEN ? null : this.startFen);
        set('moves', this.moves.length ? this.moves.map((m) => m.san).join(' ') : null);
        set('ply', this.ply < this.moves.length ? String(this.ply) : null);
        history.replaceState(history.state, '', url.toString());
    }

    private copyUrl(): void {
        if (typeof location === 'undefined') return;
        const url = location.href;
        const done = () => this.setLoadStatus(t('Pautan disalin.'));
        const fail = () => this.setLoadStatus(t('Gagal salin pautan.'));
        const fallback = () => (this.copyWithFallback(url) ? done() : fail());
        if (navigator.clipboard?.writeText) void navigator.clipboard.writeText(url).then(done, fallback);
        else fallback();
    }

    /** For pages where the Clipboard API is blocked (e.g. inside an iframe). */
    private copyWithFallback(text: string): boolean {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;left:-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            return document.execCommand('copy');
        } catch {
            return false;
        } finally {
            ta.remove();
        }
    }

    private setLoadStatus(text: string): void {
        const el = this.$('.load-status');
        el.textContent = text;
        if (this.statusTimer) clearTimeout(this.statusTimer);
        this.statusTimer = setTimeout(() => (el.textContent = ''), 3000);
    }

    /** Horizontal bar: the white part grows as White gets better. */
    private setEvalBar(s: WhiteScore | null): void {
        const bar = this.$('.eval-bar');
        bar.classList.toggle('eval-unknown', s === null);
        let value = 0; // -1 Black winning … +1 White winning
        if (s?.mate != null) value = s.mate > 0 ? 1 : -1;
        else if (s?.cp != null) value = Math.max(-1, Math.min(1, s.cp / 600));
        const pct = ((value + 1) / 2) * 100;
        this.$('.eval-needle').style.left = pct + '%';
        this.$('.eval-white').style.width = pct + '%';
        this.$('.eval-black').style.width = 100 - pct + '%';
    }
}
