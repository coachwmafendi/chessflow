import type { ArrowDrawSpec, ArrowSpec, BoardApi, MarkPatch, Piece } from '../types';
import { expandSq, fenList, nm, sq } from './squares';

const SVG_NS = 'http://www.w3.org/2000/svg';

type TrackedPiece = Piece & { id: number; el: HTMLElement };

/** DOM board controller: renders an 8x8 grid, pieces layer and SVG arrow layer into `root`. */
export function createBoard(root: HTMLElement, orient: 'w' | 'b' = 'w'): BoardApi {
    const o = orient;
    root.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'board';
    const grid = document.createElement('div');
    grid.className = 'grid';
    grid.setAttribute('aria-label', 'Papan catur');

    const pos = (i: number): [number, number] => {
        const f = i % 8;
        const r = i >> 3;
        return o === 'w' ? [f, 7 - r] : [7 - f, r];
    };

    const els: HTMLButtonElement[] = [];
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            const i = o === 'w' ? (7 - row) * 8 + col : row * 8 + (7 - col);
            const f = i % 8;
            const r = i >> 3;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'sq ' + ((f + r) % 2 ? 'light' : 'dark');
            b.dataset.sq = String(i);
            b.setAttribute('aria-label', 'Petak ' + nm(i));
            if (col === 0) b.insertAdjacentHTML('beforeend', '<span class="cr">' + (r + 1) + '</span>');
            if (row === 7) b.insertAdjacentHTML('beforeend', '<span class="cf">' + 'abcdefgh'[f] + '</span>');
            grid.appendChild(b);
            els[i] = b;
        }
    }

    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('class', 'arrows');
    svg.setAttribute('viewBox', '0 0 8 8');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML =
        '<defs><marker id="m-coral" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse"><path class="m-coral" d="M0 0L10 5L0 10z"/></marker>' +
        '<marker id="m-path" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse"><path class="m-path" d="M0 0L10 5L0 10z"/></marker></defs><g></g>';
    const svgGroup = svg.querySelector('g') as SVGGElement;
    const layer = document.createElement('div');
    layer.className = 'pieces';
    wrap.append(grid, svg, layer);
    root.appendChild(wrap);

    const pieces = new Map<number, TrackedPiece>();
    let uid = 0;
    let handler: ((sq: number) => void) | null = null;
    let mark: MarkPatch = {};

    grid.addEventListener('click', (e) => {
        const b = (e.target as HTMLElement).closest('.sq') as HTMLElement | null;
        if (b && handler) handler(+(b.dataset.sq as string));
    });

    const place = (el: HTMLElement, i: number) => {
        const [x, y] = pos(i);
        el.style.transform = 'translate(' + x * 100 + '%,' + y * 100 + '%)';
    };
    const ctr = (i: number): [number, number] => {
        const [x, y] = pos(i);
        return [x + 0.5, y + 0.5];
    };

    const api: BoardApi = {
        orient: o,
        set(list) {
            layer.innerHTML = '';
            pieces.clear();
            list.forEach((p) => api.add(p));
            mark = {};
            api.mark({});
            api.arrows([]);
        },
        setFen(fen) {
            api.set(fenList(fen));
        },
        quiet(fen) {
            const want = fenList(fen);
            const key = (p: Piece) => p.c + p.t + p.sq;
            const have = new Set(api.all().map(key));
            if (want.length === have.size && want.every((p) => have.has(key(p)))) return;
            layer.innerHTML = '';
            pieces.clear();
            want.forEach((p) => api.add(p));
        },
        add(p) {
            const id = ++uid;
            const el = document.createElement('div');
            el.className = 'piece pc ' + p.c + p.t;
            el.style.transition = 'none';
            place(el, p.sq);
            layer.appendChild(el);
            void el.offsetWidth;
            el.style.transition = '';
            pieces.set(id, { id, t: p.t, c: p.c, sq: p.sq, el });
            return id;
        },
        at(i) {
            for (const p of pieces.values()) if (p.sq === i) return p;
            return null;
        },
        all() {
            return [...pieces.values()];
        },
        remove(i) {
            const p = api.at(i);
            if (!p) return;
            pieces.delete(p.id);
            p.el.classList.add('captured');
            setTimeout(() => p.el.remove(), 350);
        },
        move(from, to) {
            const p = api.at(from);
            if (!p) return;
            const cap = api.at(to);
            if (cap && cap.id !== p.id) api.remove(to);
            p.sq = to;
            p.el.classList.remove('hop');
            void p.el.offsetWidth;
            if (p.t === 'N') p.el.classList.add('hop');
            place(p.el, to);
        },
        applyMove(m) {
            const from = sq(m.from);
            const to = sq(m.to);
            if (m.flags.includes('e')) api.remove((from >> 3) * 8 + (to % 8));
            api.move(from, to);
            if (m.flags.includes('k')) {
                const r = from >> 3;
                api.move(r * 8 + 7, r * 8 + 5);
            }
            if (m.flags.includes('q')) {
                const r = from >> 3;
                api.move(r * 8, r * 8 + 3);
            }
            if (m.promotion) {
                const p = api.at(to);
                if (p) {
                    const promoted = m.promotion.toUpperCase();
                    setTimeout(() => {
                        p.t = promoted;
                        p.el.className = 'piece pc ' + p.c + promoted;
                    }, 260);
                }
            }
            // A "+" or "#" in the SAN means the other side's king is now attacked.
            const mover = api.at(to);
            const king = mover && m.san && /[+#]$/.test(m.san) ? api.all().find((p) => p.t === 'K' && p.c !== mover.c) : undefined;
            api.mark({ last: [from, to], check: king ? [king.sq] : [] });
        },
        mark(patch) {
            mark = Object.assign({}, mark, patch);
            const set = (k: 'dots' | 'good' | 'ring' | 'stars' | 'hl' | 'last' | 'check') => new Set(expandSq(mark[k]));
            const dots = set('dots');
            const good = set('good');
            const ring = set('ring');
            const star = set('stars');
            const hl = set('hl');
            const last = set('last');
            const check = set('check');
            const sel = mark.sel == null ? -1 : typeof mark.sel === 'string' ? sq(mark.sel) : mark.sel;
            els.forEach((b, i) => {
                // A target with a piece on it is a capture: drawn as a ring around the piece instead of a dot.
                const cap = dots.has(i) && api.at(i) !== null;
                b.classList.toggle('dot', dots.has(i) && !cap);
                b.classList.toggle('cap', cap);
                b.classList.toggle('good', good.has(i));
                b.classList.toggle('ring', ring.has(i));
                b.classList.toggle('star', star.has(i));
                b.classList.toggle('hl', hl.has(i));
                b.classList.toggle('last', last.has(i));
                b.classList.toggle('check', check.has(i));
                b.classList.toggle('sel', sel === i);
            });
        },
        flash(i) {
            const b = els[i];
            b.classList.remove('bad');
            void b.offsetWidth;
            b.classList.add('bad');
        },
        arrows(list) {
            svgGroup.innerHTML = '';
            (list || []).forEach((raw) => {
                let a: ArrowDrawSpec;
                if (Array.isArray(raw)) {
                    const [from, to] = raw as ArrowSpec as [string, string];
                    a = { from: sq(from), to: sq(to) };
                } else if ('from' in raw && typeof raw.from === 'string') {
                    a = { from: sq(raw.from), to: sq((raw as { to: string }).to), kind: raw.kind };
                } else {
                    a = raw as ArrowDrawSpec;
                }
                const kind = a.kind || 'coral';
                let el: SVGPolylineElement | SVGLineElement;
                if (a.path) {
                    el = document.createElementNS(SVG_NS, 'polyline');
                    el.setAttribute('points', a.path.map(ctr).map((p) => p.join(',')).join(' '));
                } else {
                    const [x1, y1] = ctr(a.from as number);
                    const [x2, y2] = ctr(a.to as number);
                    const dx = x2 - x1;
                    const dy = y2 - y1;
                    const L = Math.hypot(dx, dy);
                    const k = (L - 0.38) / L;
                    // Start a little off-centre so the line does not show through the piece it starts from.
                    const s0 = Math.min(0.3, L / 3) / L;
                    el = document.createElementNS(SVG_NS, 'line');
                    el.setAttribute('x1', String(x1 + dx * s0));
                    el.setAttribute('y1', String(y1 + dy * s0));
                    el.setAttribute('x2', String(x1 + dx * k));
                    el.setAttribute('y2', String(y1 + dy * k));
                }
                el.setAttribute('class', 'a-' + kind + ' draw');
                el.setAttribute('stroke-width', '.16');
                el.setAttribute('pathLength', '20');
                el.setAttribute('marker-end', 'url(#m-' + kind + ')');
                svgGroup.appendChild(el);
            });
        },
        on(fn) {
            handler = fn;
        },
    };
    return api;
}
