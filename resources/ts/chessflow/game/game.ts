import { Chess, type Move, type Square } from 'chess.js';
import type { ArrowDrawSpec, BoardApi } from '../types';
import { createBoard } from '../core/board';
import { nm, sq } from '../core/squares';
import { SFX } from '../core/sound';
import { bot } from '../engine/simple-bot';
import { sharedEngine, type StockfishEngine } from '../engine/stockfish';
import { judgeDrawOffer } from './draw';
import { analyseGame, type Mistake } from './analysis';
import { newBadgesHtml, type NewBadge } from '../core/badges';

export type GameOutcome = 'win' | 'loss' | 'draw' | 'resign';

export interface GameReport {
    userColor: 'w' | 'b';
    level: number;
    result: GameOutcome;
    pgn: string;
    moveCount: number;
}

export interface GameHandlers {
    finished?: (report: GameReport) => void;
}

// Same three levels as the prototype. Mudah uses simple-bot only.
const LEVELS: { n: string; skill?: number; depth?: number }[] = [
    { n: 'Mudah' },
    { n: 'Sederhana', skill: 0, depth: 2 },
    { n: 'Sukar', skill: 6, depth: 8 },
];

const PREF_KEY = 'chessflow-game';

// White flag (resign) and ½ (draw), the usual chess symbols.
const ICON_RESIGN =
    '<svg class="btn-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/><path d="M6 4h12l-2.5 4.5L18 13H6z" fill="currentColor" fill-opacity=".18" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
const ICON_DRAW = '<span class="btn-ico half" aria-hidden="true">½</span>';

type ColorChoice = 'w' | 'b' | 'r';

function loadPref(): { color: ColorChoice; level: number } {
    try {
        const p = JSON.parse(localStorage.getItem(PREF_KEY) || '{}');
        return {
            color: p.color === 'b' || p.color === 'r' ? p.color : 'w',
            level: p.level >= 0 && p.level < LEVELS.length ? p.level : 0,
        };
    } catch {
        return { color: 'w', level: 0 };
    }
}

/** Full game against Pak Kuda (prototype openGame), reporting the finished game to the server. */
export class GameRunner {
    private readonly root: HTMLElement;
    private readonly handlers: GameHandlers;
    private readonly engine: StockfishEngine;
    private timers: ReturnType<typeof setTimeout>[] = [];

    private color: ColorChoice;
    private level: number;
    private g = new Chess();
    private board!: BoardApi;
    private user: 'w' | 'b' = 'w';
    private over = false;
    private busy = false;
    private sel: Square | null = null;
    private reported = false;
    private gameId = 0;
    /** Ply count when Pak Kuda last refused a draw offer. */
    private drawRefusedAt: number | null = null;
    /** Closes the open confirm dialog, if any. */
    private closeDialog: (() => void) | null = null;

    constructor(root: HTMLElement, handlers: GameHandlers = {}, engine: StockfishEngine = sharedEngine()) {
        this.root = root;
        this.handlers = handlers;
        this.engine = engine;
        const pref = loadPref();
        this.color = pref.color;
        this.level = pref.level;
        this.render();
        this.start();
    }

    destroy(): void {
        this.closeDialog?.();
        this.clearTimers();
        this.gameId++;
    }

    /** Server reply to a reported game (Livewire `game-result`). */
    showResult(r: { xp: number; totalXp?: number; badges?: NewBadge[] }): void {
        if (r.xp > 0) {
            const s = this.$('.status');
            s.textContent = s.textContent + ' +' + r.xp + ' XP';
        }
        this.root.querySelector('.new-badges')?.remove();
        this.$('.status').insertAdjacentHTML('afterend', newBadgesHtml(r.badges));
        if (typeof r.totalXp === 'number') {
            document.querySelectorAll('[data-stat="xp"]').forEach((el) => (el.textContent = String(r.totalXp)));
        }
    }

    private $(sel: string): HTMLElement {
        return this.root.querySelector(sel) as HTMLElement;
    }

    private later(fn: () => void, ms: number): void {
        this.timers.push(setTimeout(fn, ms));
    }

    private clearTimers(): void {
        this.timers.forEach(clearTimeout);
        this.timers = [];
    }

    private render(): void {
        this.root.innerHTML =
            '<div class="lesson-bar"><h2>Main lawan Pak Kuda</h2></div>' +
            '<div class="lesson"><div class="board-wrap"><div class="gbw"></div><p class="turn"></p></div><div class="panel">' +
            '<div class="setup"><div class="seg" role="group" aria-label="Warna"><span class="seg-l">Warna</span><button type="button" data-c="w">Putih</button><button type="button" data-c="b">Hitam</button><button type="button" data-c="r">Rawak</button></div>' +
            '<div class="seg" role="group" aria-label="Tahap"><span class="seg-l">Tahap</span>' +
            LEVELS.map((l, i) => '<button type="button" data-l="' + i + '">' + l.n + '</button>').join('') +
            '</div><button class="cta" type="button" data-act="new">Permainan baru</button></div>' +
            '<div class="status" role="status" aria-live="polite"></div>' +
            '<div class="moves"><small>Langkah</small><ol></ol></div>' +
            '<div class="analysis" aria-live="polite" hidden></div>' +
            '<div class="actions"><button class="ghost" type="button" data-act="undo">Undur</button><button class="ghost" type="button" data-act="hint">Petunjuk</button><span class="spacer"></span><span class="actions-end"><button class="ghost" type="button" data-act="draw">' + ICON_DRAW + 'Minta seri</button><button class="ghost" type="button" data-act="resign">' + ICON_RESIGN + 'Mengaku kalah</button></span></div>' +
            '</div></div>';

        this.root.querySelectorAll<HTMLElement>('[data-c]').forEach(
            (b) =>
                (b.onclick = () => {
                    this.color = b.dataset.c as ColorChoice;
                    this.segs();
                }),
        );
        this.root.querySelectorAll<HTMLElement>('[data-l]').forEach(
            (b) =>
                (b.onclick = () => {
                    this.level = Number(b.dataset.l);
                    this.segs();
                }),
        );
        this.$('[data-act="new"]').onclick = () => this.start();
        this.$('[data-act="undo"]').onclick = () => this.undo();
        this.$('[data-act="hint"]').onclick = () => this.hint();
        this.$('[data-act="draw"]').onclick = () => this.offerDraw();
        this.$('[data-act="resign"]').onclick = () => this.confirmResign();
        this.segs();
    }

    private segs(): void {
        this.root.querySelectorAll<HTMLElement>('[data-c]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.c === this.color)));
        this.root.querySelectorAll<HTMLElement>('[data-l]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.l) === this.level)));
    }

    private status(t: string, k: 'good' | 'bad' | 'info' | '' = ''): void {
        const s = this.$('.status');
        s.className = 'status' + (k ? ' ' + k : '');
        s.textContent = t;
    }

    private showMoves(): void {
        const h = this.g.history();
        let out = '';
        for (let i = 0; i < h.length; i += 2) out += '<li><span>' + h[i] + '</span><span>' + (h[i + 1] || '') + '</span></li>';
        const ol = this.$('.moves ol');
        ol.innerHTML = out;
        ol.scrollTop = ol.scrollHeight;
        this.$('.turn').textContent = this.over ? '' : this.g.turn() === this.user ? 'Giliran awak' : 'Pak Kuda sedang berfikir…';
    }

    private start(): void {
        this.closeDialog?.();
        this.clearTimers();
        this.gameId++;
        this.user = this.color === 'r' ? (Math.random() < 0.5 ? 'w' : 'b') : this.color;
        try {
            localStorage.setItem(PREF_KEY, JSON.stringify({ color: this.color, level: this.level }));
        } catch {
            // ignore
        }
        this.g = new Chess();
        this.board = createBoard(this.$('.gbw'), this.user);
        this.board.setFen(this.g.fen());
        this.board.on((i) => this.onTap(i));
        this.board.draggable((i) => !this.over && !this.busy && this.g.turn() === this.user && this.g.get(nm(i) as Square)?.color === this.user);
        this.over = false;
        this.busy = false;
        this.sel = null;
        this.reported = false;
        this.drawRefusedAt = null;
        this.hideAnalysis();
        this.root.querySelector('.new-badges')?.remove();
        this.status(
            'Awak main ' +
                (this.user === 'w' ? 'Putih' : 'Hitam') +
                ' · tahap ' +
                LEVELS[this.level].n +
                '.' +
                (this.level > 0 && !this.engine.available ? ' (Enjin kuat tidak dapat dimuatkan, Pak Kuda guna otak sendiri.)' : ''),
            'info',
        );
        this.showMoves();
        if (this.user === 'b') this.botMove();
    }

    private report(result: GameOutcome): void {
        if (this.reported) return;
        this.reported = true;
        this.handlers.finished?.({
            userColor: this.user,
            level: this.level,
            result,
            pgn: this.g.pgn(),
            moveCount: this.g.history().length,
        });
    }

    private end(): boolean {
        const g = this.g;
        if (!g.isGameOver()) return false;
        this.over = true;
        if (g.isCheckmate()) {
            if (g.turn() !== this.user) {
                this.status('Sah mati! Awak menang lawan Pak Kuda (' + LEVELS[this.level].n + ').', 'good');
                SFX.win();
                this.report('win');
            } else {
                this.status('Sah mati. Pak Kuda menang kali ini. Cuba lagi!', 'bad');
                SFX.bad();
                this.report('loss');
            }
        } else {
            this.status(
                g.isStalemate()
                    ? 'Stalemate. Permainan seri.'
                    : g.isThreefoldRepetition()
                      ? 'Ulangan tiga kali. Seri.'
                      : g.isInsufficientMaterial()
                        ? 'Buah tak cukup untuk sah mati. Seri.'
                        : 'Seri (peraturan 50 langkah).',
                'info',
            );
            this.report('draw');
        }
        this.showMoves();
        this.offerAnalysis();
        return true;
    }

    private apply(m: { from: string; to: string; promotion?: string }): Move {
        const r = this.g.move(m);
        this.board.applyMove(r);
        this.board.mark({ sel: null, dots: [] });
        this.board.arrows([]);
        const fen = this.g.fen();
        this.later(() => this.board.quiet(fen), 420);
        this.showMoves();
        return r;
    }

    private botMove(): void {
        if (this.over) return;
        this.busy = true;
        this.showMoves();
        const L = LEVELS[this.level];
        const id = this.gameId;
        const fallback = () => bot(this.g, 'defend', this.user, this.level === 0 ? 260 : 40);
        const go = (mv: { from: string; to: string; promotion?: string } | null) => {
            this.later(
                () => {
                    if (this.over || id !== this.gameId) return;
                    const choice = mv ?? fallback();
                    if (!choice) return;
                    this.apply(choice);
                    SFX.move();
                    this.busy = false;
                    if (!this.end()) this.status(this.g.inCheck() ? 'Sah! Selamatkan Raja awak.' : '', this.g.inCheck() ? 'bad' : '');
                    this.showMoves();
                },
                this.level === 0 ? 500 : 150,
            );
        };

        if (L.skill === undefined || !this.engine.available) {
            go(null);
            return;
        }
        this.engine.bestMove({ fen: this.g.fen(), skill: L.skill, depth: L.depth ?? 2 }).then((u) => {
            if (id !== this.gameId) return;
            go(u ? this.legalUci(u) : null);
        });
    }

    /** Engine answers are checked against the current position before use. */
    private legalUci(u: string): { from: string; to: string; promotion?: string } | null {
        const m = { from: u.slice(0, 2), to: u.slice(2, 4), promotion: u[4] };
        return this.g.moves({ verbose: true }).some((x) => x.from === m.from && x.to === m.to) ? m : null;
    }

    private onTap(i: number): void {
        if (this.over || this.busy || this.g.turn() !== this.user) return;
        const s = nm(i) as Square;
        const p = this.g.get(s);
        if (p && p.color === this.user) {
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
            const id = this.gameId;
            void this.board.choosePromotion(this.user).then((piece) => {
                if (id !== this.gameId) return;
                this.busy = false;
                if (this.over) return; // resigned while choosing
                if (piece) this.playerMove(from, s, piece);
                else this.board.mark({ sel: null, dots: [] });
            });
            return;
        }
        this.playerMove(from, s);
    }

    private playerMove(from: Square, to: Square, promotion?: string): void {
        this.apply({ from, to, promotion });
        SFX.move();
        this.status('', '');
        if (!this.end()) this.botMove();
    }

    private undo(): void {
        if (this.busy || !this.g.history().length) return;
        this.g.undo();
        if (this.g.turn() !== this.user) this.g.undo();
        this.over = false;
        this.board.quiet(this.g.fen());
        this.board.mark({ hl: [], last: [], check: [], sel: null, dots: [] });
        this.board.arrows([]);
        this.sel = null;
        this.hideAnalysis();
        this.status('Langkah diundur.', 'info');
        this.showMoves();
    }

    private hint(): void {
        if (this.busy || this.over || this.g.turn() !== this.user) return;
        this.busy = true;
        this.status('Pak Kuda sedang fikir petunjuk…', 'info');
        const id = this.gameId;
        const show = (mv: { from: string; to: string } | null) => {
            if (id !== this.gameId) return;
            this.busy = false;
            if (!mv) return this.status('Tiada petunjuk.', 'info');
            this.board.arrows([{ from: sq(mv.from), to: sq(mv.to), kind: 'path' }]);
            this.status('Cuba langkah anak panah ini.', 'info');
        };
        const local = () => bot(this.g, 'defend', this.g.turn() === 'w' ? 'b' : 'w', 1);
        if (!this.engine.available) return show(local());
        this.engine.bestMove({ fen: this.g.fen(), skill: 20, depth: 10 }).then((u) => show((u && this.legalUci(u)) || local()));
    }

    private offerDraw(): void {
        if (this.over || this.busy || this.g.turn() !== this.user) return;
        const v = judgeDrawOffer(this.g, this.user, this.drawRefusedAt);
        if (!v.accept) {
            if (v.reason === 'material') this.drawRefusedAt = this.g.history().length;
            this.status(v.message, 'info');
            return;
        }
        this.over = true;
        this.board.arrows([]);
        this.status(v.message, 'info');
        this.report('draw');
        this.showMoves();
        this.offerAnalysis();
    }

    /** A tap on "Mengaku kalah" ends the game, so ask first; "Teruskan main" is the default. */
    private confirmResign(): void {
        if (this.over || this.closeDialog) return;
        const opener = this.$('[data-act="resign"]');
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML =
            '<div class="card" role="alertdialog" aria-modal="true" aria-labelledby="resign-t" aria-describedby="resign-d">' +
            '<span class="qico big resign-ico">' + ICON_RESIGN + '</span>' +
            '<h2 id="resign-t">Mengaku kalah?</h2>' +
            '<p id="resign-d">Permainan ini akan tamat dan dikira kalah. Tak apa, pemain hebat pun pernah kalah. Atau awak boleh teruskan dan cuba bertahan!</p>' +
            '<div class="actions confirm-actions"><button class="ghost danger" type="button" data-r="yes">Ya, mengaku kalah</button><button class="cta" type="button" data-r="no">Teruskan main</button></div>' +
            '</div>';
        const yes = modal.querySelector('[data-r="yes"]') as HTMLButtonElement;
        const no = modal.querySelector('[data-r="no"]') as HTMLButtonElement;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') close();
            if (e.key === 'Tab') {
                // Keep focus inside the dialog: only two buttons to cycle through.
                e.preventDefault();
                (document.activeElement === no ? yes : no).focus();
            }
        };
        const close = () => {
            modal.remove();
            document.removeEventListener('keydown', onKey);
            this.closeDialog = null;
            if (opener.isConnected) opener.focus();
        };
        yes.onclick = () => {
            close();
            this.resign();
        };
        no.onclick = close;
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', onKey);
        this.closeDialog = close;
        this.root.appendChild(modal);
        no.focus();
    }

    private resign(): void {
        if (this.over) return;
        this.over = true;
        this.status('Awak mengaku kalah. Tekan "Permainan baru" untuk cuba lagi.', 'bad');
        this.report('resign');
        this.showMoves();
        this.offerAnalysis();
    }

    // ---- post-game analysis ----

    private hideAnalysis(): void {
        const box = this.$('.analysis');
        box.hidden = true;
        box.innerHTML = '';
    }

    /** After the game: offer a review once the player has made a few moves. */
    private offerAnalysis(): void {
        const mine = this.g.history({ verbose: true }).filter((m) => m.color === this.user).length;
        if (mine < 3) return;
        const box = this.$('.analysis');
        box.hidden = false;
        box.innerHTML =
            '<button class="cta" type="button" data-act="analyse">Semak permainan dengan Pak Kuda</button>' +
            '<small>Pak Kuda tunjuk langkah yang paling penting untuk dipelajari.</small>';
        (box.querySelector('[data-act="analyse"]') as HTMLElement).onclick = () => void this.runAnalysis();
    }

    private async runAnalysis(): Promise<void> {
        const box = this.$('.analysis');
        if (!this.engine.available) {
            box.innerHTML = '<p class="analysis-note">Analisis perlukan enjin catur, tetapi enjin tidak dapat dimuatkan pada peranti ini.</p>';
            return;
        }
        const id = this.gameId;
        box.innerHTML =
            '<p class="analysis-note">Pak Kuda sedang menyemak permainan… <b class="pct">0%</b></p><div class="meter analysis-meter"><span style="width:0%"></span></div>';
        const bar = box.querySelector('.analysis-meter span') as HTMLElement;
        const pct = box.querySelector('.pct') as HTMLElement;
        const result = await analyseGame(
            this.g.history({ verbose: true }),
            this.user,
            (fen) => this.engine.analyse({ fen, depth: 12, timeoutMs: 4000 }),
            (done) => {
                const v = Math.round(done * 100) + '%';
                bar.style.width = v;
                pct.textContent = v;
            },
            () => id !== this.gameId,
        );
        if (result === null || id !== this.gameId) return;
        this.showMistakes(result);
    }

    private showMistakes(list: Mistake[]): void {
        const box = this.$('.analysis');
        if (!list.length) {
            box.innerHTML = '<p class="analysis-good">Tiada kesilapan besar dalam permainan ini. Syabas!</p>';
            return;
        }
        box.innerHTML =
            '<small>Langkah untuk dipelajari</small><ol class="mistakes">' +
            list
                .map(
                    (m, k) =>
                        '<li><button type="button" class="mistake" data-k="' +
                        k +
                        '" aria-pressed="false"><span class="tag ' +
                        m.severity +
                        '">' +
                        (m.severity === 'besar' ? 'Kesilapan besar' : 'Silap kecil') +
                        '</span><b>Langkah ' +
                        m.moveNo +
                        ': ' +
                        m.san +
                        '</b><span>' +
                        m.text +
                        '</span></button></li>',
                )
                .join('') +
            '</ol><p class="analysis-legend" hidden>Anak panah merah: langkah awak. Hijau: cadangan Pak Kuda.</p>' +
            '<button class="ghost" type="button" data-act="final" hidden>Kembali ke kedudukan akhir</button>';
        box.querySelectorAll<HTMLElement>('.mistake').forEach((b) => (b.onclick = () => this.showMistake(list, Number(b.dataset.k))));
        (box.querySelector('[data-act="final"]') as HTMLElement).onclick = () => this.showFinalPosition();
    }

    /** Put the position before the mistake on the board, with the move played and the better one. */
    private showMistake(list: Mistake[], k: number): void {
        const m = list[k];
        this.board.setFen(m.fenBefore);
        const arrows: ArrowDrawSpec[] = [{ from: sq(m.from), to: sq(m.to), kind: 'coral' }];
        if (m.best) arrows.push({ from: sq(m.best.from), to: sq(m.best.to), kind: 'path' });
        this.board.arrows(arrows);
        const box = this.$('.analysis');
        box.querySelectorAll<HTMLElement>('.mistake').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.k) === k)));
        (box.querySelector('.analysis-legend') as HTMLElement).hidden = false;
        (box.querySelector('[data-act="final"]') as HTMLElement).hidden = false;
    }

    private showFinalPosition(): void {
        this.board.setFen(this.g.fen());
        this.board.arrows([]);
        const box = this.$('.analysis');
        box.querySelectorAll<HTMLElement>('.mistake').forEach((b) => b.setAttribute('aria-pressed', 'false'));
        (box.querySelector('.analysis-legend') as HTMLElement).hidden = true;
        (box.querySelector('[data-act="final"]') as HTMLElement).hidden = true;
    }
}
