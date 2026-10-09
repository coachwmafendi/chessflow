import type { BoardApi, Lesson, LessonResult, ServerLessonResult, StepContext } from '../types';
import { createBoard } from '../core/board';
import { SFX } from '../core/sound';
import { runStep } from '../steps';

const PASS_RATIO = 0.7;

interface Els {
    title: HTMLElement;
    prog: HTMLElement;
    bw: HTMLElement;
    turn: HTMLElement;
    stepTitle: HTMLElement;
    say: HTMLElement;
    taskText: HTMLElement;
    counter: HTMLElement;
    opts: HTMLElement;
    status: HTMLElement;
    acts: HTMLElement;
    next: HTMLButtonElement;
    modal: HTMLElement;
}

export interface LessonRunnerHandlers {
    complete?: (result: LessonResult) => void;
}

const FLAME_SVG =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2s5 4.5 5 10a5 5 0 0 1-10 0c0-2.4 1.3-4.2 2.4-5.2.2 1.8 1.1 3 2.3 3.4C11 7.6 12 2 12 2zm0 12.5c-1.1.9-1.6 1.8-1.6 2.7a1.6 1.6 0 0 0 3.2 0c0-.9-.5-1.8-1.6-2.7z"/></svg>';

const escapeHtml = (s: string): string =>
    s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);

const link = (href: string, label: string, cls: string): string =>
    '<a class="' + cls + '" href="' + escapeHtml(href) + '">' + escapeHtml(label) + '</a>';

const starsHtml = (n: number): string =>
    [0, 1, 2].map((i) => '<i class="star-ico' + (i < n ? '' : ' off') + '"></i>').join('');

/**
 * Runs a single lesson inside `root`: renders the board + coaching panel,
 * drives the step loop via steps/index.ts, tracks mistakes/exam failures,
 * and reports the final result through `handlers.complete` — it does not
 * decide XP, stars persistence or unlocks itself (server-authoritative,
 * see CLAUDE.md); it only reports what happened.
 */
export class LessonRunner {
    private readonly root: HTMLElement;
    private readonly lesson: Lesson;
    private readonly handlers: LessonRunnerHandlers;
    private readonly els: Els;
    private readonly startedAt: number;

    private step = 0;
    private mistakes = 0;
    private timers: ReturnType<typeof setTimeout>[] = [];
    private board: BoardApi | null = null;
    private failed = new Set<number>();

    constructor(root: HTMLElement, lesson: Lesson, handlers: LessonRunnerHandlers = {}) {
        this.root = root;
        this.lesson = lesson;
        this.handlers = handlers;
        this.startedAt = Date.now();
        this.els = this.render();
        this.load();
    }

    /** Stop all pending timers. Call on livewire:navigating / component teardown. */
    destroy(): void {
        this.clearTimers();
    }

    private clearTimers(): void {
        this.timers.forEach(clearTimeout);
        this.timers = [];
    }

    private render(): Els {
        this.root.innerHTML =
            '<div class="lesson-bar"><h2 class="lesson-title"></h2><div class="prog"></div></div>' +
            '<div class="lesson"><div class="board-wrap"><div class="bw"></div><p class="turn"></p></div><div class="panel">' +
            (this.lesson.exam
                ? '<p class="exam-note">Ujian: setiap soalan ada <b>satu peluang</b>. Lulus jika betul sekurang-kurangnya 70%.</p>'
                : '') +
            '<h3 class="step-title"></h3>' +
            '<div class="coach"><div class="avatar sm"><i class="pc pk"></i></div><div class="bubble"><span class="who">Pak Kuda</span><p class="say"></p></div></div>' +
            '<div class="task"><small>Tugasan</small><div class="row"><span class="task-text"></span><span class="counter"></span></div><div class="opts"></div></div>' +
            '<div class="status" role="status" aria-live="polite"></div>' +
            '<div class="actions"><span class="acts"></span><span class="spacer"></span><button type="button" class="cta next-btn" disabled>Teruskan</button></div>' +
            '</div></div><div class="modal" hidden></div>';

        const els: Els = {
            title: this.root.querySelector('.lesson-title') as HTMLElement,
            prog: this.root.querySelector('.prog') as HTMLElement,
            bw: this.root.querySelector('.bw') as HTMLElement,
            turn: this.root.querySelector('.turn') as HTMLElement,
            stepTitle: this.root.querySelector('.step-title') as HTMLElement,
            say: this.root.querySelector('.say') as HTMLElement,
            taskText: this.root.querySelector('.task-text') as HTMLElement,
            counter: this.root.querySelector('.counter') as HTMLElement,
            opts: this.root.querySelector('.opts') as HTMLElement,
            status: this.root.querySelector('.status') as HTMLElement,
            acts: this.root.querySelector('.acts') as HTMLElement,
            next: this.root.querySelector('.next-btn') as HTMLButtonElement,
            modal: this.root.querySelector('.modal') as HTMLElement,
        };
        els.title.textContent = this.lesson.title;
        els.next.onclick = () => {
            if (this.step < this.lesson.steps.length - 1) {
                this.step++;
                this.load();
            } else {
                this.finish();
            }
        };
        return els;
    }

    private examNote(): string {
        return this.lesson.exam && this.failed.has(this.step) ? ' Soalan ini dikira salah. Tekan Teruskan.' : '';
    }

    private buildContext(): StepContext {
        return {
            newBoard: (orient) => {
                this.board = createBoard(this.els.bw, orient);
                const st = this.lesson.steps[this.step];
                this.els.turn.textContent = st.fen
                    ? st.fen.split(' ')[1] === 'w'
                        ? 'Putih untuk bergerak'
                        : 'Hitam untuk bergerak'
                    : '';
                return this.board;
            },
            board: () => this.board,
            later: (fn, ms) => {
                this.timers.push(setTimeout(fn, ms));
            },
            clear: () => this.clearTimers(),
            status: (t, k) => {
                this.els.status.className = 'status' + (k ? ' ' + k : '');
                this.els.status.textContent = t + (k === 'bad' && t ? this.examNote() : '');
            },
            statusHtml: (html) => {
                this.els.status.innerHTML = html + (this.els.status.classList.contains('bad') ? this.examNote() : '');
            },
            counter: (t) => {
                this.els.counter.textContent = t;
            },
            task: (html) => {
                this.els.taskText.innerHTML = html;
            },
            opts: () => this.els.opts,
            mistake: (n) => {
                this.mistakes += n || 1;
                // Reported for every lesson: the server queues these steps for "Latih semula".
                this.failed.add(this.step);
                if (this.lesson.exam) this.els.next.disabled = false;
            },
            actions: (list) => {
                this.els.acts.innerHTML = '';
                list.forEach((x) => {
                    if (this.lesson.exam && x.label === 'Petunjuk') return;
                    const bt = document.createElement('button');
                    bt.type = 'button';
                    bt.className = 'ghost';
                    bt.textContent = x.label;
                    bt.onclick = x.fn;
                    this.els.acts.appendChild(bt);
                });
            },
            done: () => {
                this.els.next.disabled = false;
            },
        };
    }

    private load(): void {
        this.clearTimers();
        const st = this.lesson.steps[this.step];
        this.els.prog.innerHTML = this.lesson.steps
            .map(
                (_, i) =>
                    '<span class="' +
                    (this.lesson.exam && this.failed.has(i) ? 'miss' : i < this.step ? 'on' : i === this.step ? 'cur' : '') +
                    '"></span>',
            )
            .join('');
        this.els.stepTitle.textContent = st.title;
        this.els.say.innerHTML = st.say || '';
        this.els.taskText.innerHTML = '';
        this.els.opts.innerHTML = '';
        this.els.counter.textContent = '';
        this.els.status.className = 'status';
        this.els.status.textContent = '';
        this.els.acts.innerHTML = '';
        this.els.next.disabled = true;
        this.els.next.textContent = this.step === this.lesson.steps.length - 1 ? 'Selesai' : 'Teruskan';
        runStep(st, this.buildContext());
    }

    private finish(): void {
        this.clearTimers();
        SFX.win();
        const durationMs = Date.now() - this.startedAt;

        if (this.lesson.daily) {
            this.showDailyModal();
            this.handlers.complete?.({ slug: this.lesson.id, mistakes: this.mistakes, failedSteps: [...this.failed], durationMs });
            return;
        }

        if (this.lesson.review) {
            this.showReviewModal();
            this.handlers.complete?.({ slug: this.lesson.id, mistakes: this.mistakes, failedSteps: [...this.failed], durationMs });
            return;
        }

        if (this.lesson.exam) {
            const q = this.lesson.steps.length;
            const score = q - this.failed.size;
            const pass = score >= Math.ceil(q * PASS_RATIO);
            this.showExamModal(score, q, pass);
            this.handlers.complete?.({
                slug: this.lesson.id,
                mistakes: this.mistakes,
                failedSteps: [...this.failed],
                durationMs,
            });
            return;
        }

        const stars = this.mistakes <= 1 ? 3 : this.mistakes <= 4 ? 2 : 1;
        this.showLessonModal(stars);
        this.handlers.complete?.({ slug: this.lesson.id, mistakes: this.mistakes, failedSteps: [...this.failed], durationMs });
    }

    /**
     * Adds the server's verdict (XP, streak, next lesson, certificate) to the finish modal.
     * Stars/score shown before this are the client's own count; XP only ever comes from here.
     */
    showResult(r: ServerLessonResult): void {
        const card = this.els.modal.querySelector('.card');
        if (!card) return;

        if (this.lesson.daily && r.streak) {
            const h = card.querySelector('h2');
            if (h) h.textContent = r.streak + ' hari berturut-turut!';
        }

        if (this.lesson.review && r.review) {
            const h = card.querySelector('h2');
            const p = card.querySelector('p');
            if (h) h.textContent = r.review.correct ? (r.review.mastered ? 'Dah mahir!' : 'Betul!') : 'Hampir!';
            if (p) {
                p.textContent = r.review.correct
                    ? r.review.mastered
                        ? 'Awak dah jawab soalan ini dengan betul beberapa kali. Pak Kuda tak akan tanya lagi.'
                        : 'Pak Kuda akan tanya soalan ini sekali lagi beberapa hari nanti, supaya awak tak lupa.'
                    : 'Tak apa. Pak Kuda akan bawa soalan ini semula esok.';
            }
            if (r.review.remaining > 0) card.insertAdjacentHTML('beforeend', '<p class="review-count">Lagi ' + r.review.remaining + ' latihan hari ini.</p>');
        }

        card.querySelector('.xp')?.remove();
        card.querySelector('.actions')?.remove();
        card.querySelector('.server-msg')?.remove();

        if (r.message) {
            card.insertAdjacentHTML('beforeend', '<p class="status bad server-msg">' + escapeHtml(r.message) + '</p>');
        }

        if (r.xp > 0) card.insertAdjacentHTML('beforeend', '<div class="xp">+' + r.xp + ' XP</div>');
        else if (this.lesson.daily && !r.message) card.insertAdjacentHTML('beforeend', '<p>XP teka-teki hari ini sudah dikutip.</p>');

        const links: string[] = [];
        if (r.certificateUrl) links.push(link(r.certificateUrl, 'Lihat sijil', 'cta'));
        if (r.retryUrl) links.push(link(r.retryUrl, 'Cuba lagi', 'cta'));
        if (r.next) links.push(link(r.next.url, this.lesson.review ? 'Latihan seterusnya' : 'Seterusnya: ' + r.next.title, r.certificateUrl ? 'ghost' : 'cta'));
        if (r.mapUrl) links.push(link(r.mapUrl, 'Ke peta', 'ghost'));
        if (links.length) {
            card.insertAdjacentHTML('beforeend', '<div class="actions" style="justify-content:center">' + links.join('') + '</div>');
            card.querySelector<HTMLElement>('.actions a')?.focus();
        }

        if (typeof r.totalXp === 'number') {
            document.querySelectorAll('[data-stat="xp"]').forEach((el) => (el.textContent = String(r.totalXp)));
        }
        if (typeof r.totalStars === 'number') {
            document.querySelectorAll('[data-stat="stars"]').forEach((el) => (el.textContent = String(r.totalStars)));
        }
    }

    private showReviewModal(): void {
        this.els.modal.hidden = false;
        this.els.modal.innerHTML =
            '<div class="card" role="dialog" aria-modal="true"><span class="qico big">' +
            '<i class="pc pk" style="width:52px;height:52px;display:block"></i>' +
            '</span><h2>' +
            (this.mistakes ? 'Hampir!' : 'Betul!') +
            '</h2><p>Pak Kuda sedang menyimpan keputusan awak…</p></div>';
    }

    private showDailyModal(): void {
        this.els.modal.hidden = false;
        this.els.modal.innerHTML =
            '<div class="card" role="dialog" aria-modal="true"><span class="qico flame big">' +
            FLAME_SVG +
            '</span><h2>Teka-teki selesai!</h2><p>Teka-teki hari ini selesai. Datang lagi esok untuk teka-teki baharu.</p></div>';
    }

    private showLessonModal(stars: number): void {
        this.els.modal.hidden = false;
        this.els.modal.innerHTML =
            '<div class="card" role="dialog" aria-modal="true"><div class="big-stars">' +
            starsHtml(stars) +
            '</div><h2>Tahniah!</h2><p>Awak habiskan <b>' +
            this.lesson.title +
            '</b>' +
            (this.mistakes ? ' dengan ' + this.mistakes + ' kesilapan kecil.' : ' tanpa sebarang kesilapan!') +
            '</p>' +
            (this.lesson.tip ? '<div class="tip">' + this.lesson.tip + '</div>' : '') +
            '</div>';
    }

    private showExamModal(score: number, q: number, pass: boolean): void {
        const need = Math.ceil(q * PASS_RATIO);
        this.els.modal.hidden = false;
        this.els.modal.innerHTML = pass
            ? '<div class="card" role="dialog" aria-modal="true"><div class="big-stars">' +
              starsHtml(score === q ? 3 : score >= q - 1 ? 2 : 1) +
              '</div><h2>Lulus!</h2><p>Markah awak <b>' +
              score +
              '/' +
              q +
              '</b>.</p></div>'
            : '<div class="card" role="dialog" aria-modal="true"><div class="big-stars">' +
              starsHtml(0) +
              '</div><h2>Hampir!</h2><p>Markah awak <b>' +
              score +
              '/' +
              q +
              '</b>. Perlu sekurang-kurangnya ' +
              need +
              ' untuk lulus. Ulang kaji pelajaran tahap ini dan cuba lagi.</p></div>';
    }
}
