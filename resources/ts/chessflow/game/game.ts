import { Chess, type Move, type Square } from 'chess.js';
import type { BoardApi } from '../types';
import { createBoard } from '../core/board';
import { nm, sq } from '../core/squares';
import { SFX } from '../core/sound';
import { bot } from '../engine/simple-bot';
import { sharedEngine, type StockfishEngine } from '../engine/stockfish';
import { judgeDrawOffer } from './draw';

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
        this.clearTimers();
        this.gameId++;
    }

    /** Server reply to a reported game (Livewire `game-result`). */
    showResult(r: { xp: number; totalXp?: number }): void {
        if (r.xp > 0) {
            const s = this.$('.status');
            s.textContent = s.textContent + ' +' + r.xp + ' XP';
        }
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
        this.$('[data-act="resign"]').onclick = () => this.resign();
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
        this.over = false;
        this.busy = false;
        this.sel = null;
        this.reported = false;
        this.drawRefusedAt = null;
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
        this.apply({ from: this.sel, to: s, promotion: legal[0].promotion ? 'q' : undefined });
        this.sel = null;
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
    }

    private resign(): void {
        if (this.over) return;
        this.over = true;
        this.status('Awak mengaku kalah. Tekan "Permainan baru" untuk cuba lagi.', 'bad');
        this.report('resign');
        this.showMoves();
    }
}
