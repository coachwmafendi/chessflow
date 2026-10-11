// Confetti burst for the end of a game: two cannons from the bottom corners, drawn on a
// full-screen canvas that ignores the pointer and removes itself when the pieces have fallen.

export interface Particle {
    x: number;
    y: number;
    vx: number;
    vy: number;
    rot: number;
    spin: number;
    w: number;
    h: number;
    color: string;
    /** Phase for the paper "flip" (width wobble). */
    flip: number;
}

const COLORS = ['#087466', '#34C2B0', '#FFC94D', '#FF7A59', '#7C5CFF', '#F4F1E8'];
const GRAVITY = 0.32;
const DRAG = 0.988;
const MAX_MS = 4500;

/** Pieces fired from both bottom corners, angled up and towards the middle. */
export function makeParticles(width: number, height: number, count = 160, rand: () => number = Math.random): Particle[] {
    const out: Particle[] = [];
    for (let i = 0; i < count; i++) {
        const left = i % 2 === 0;
        // Up-and-inwards: 55–80° above the horizontal.
        const angle = ((55 + rand() * 25) * Math.PI) / 180;
        const speed = (0.024 + rand() * 0.014) * height;
        out.push({
            x: left ? 0 : width,
            y: height,
            vx: Math.cos(angle) * speed * (left ? 1 : -1),
            vy: -Math.sin(angle) * speed,
            rot: rand() * Math.PI * 2,
            spin: (rand() - 0.5) * 0.3,
            w: 7 + rand() * 6,
            h: 10 + rand() * 8,
            color: COLORS[Math.floor(rand() * COLORS.length)],
            flip: rand() * Math.PI * 2,
        });
    }
    return out;
}

/** One animation frame of physics. Returns how many pieces are still on screen. */
export function step(ps: Particle[], height: number): number {
    let alive = 0;
    for (const p of ps) {
        p.vx *= DRAG;
        p.vy = p.vy * DRAG + GRAVITY;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.spin;
        p.flip += 0.12;
        if (p.y < height + 40) alive++;
    }
    return alive;
}

function reducedMotion(): boolean {
    try {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch {
        return false;
    }
}

/** Fires a burst. Returns a function that stops it early and removes the canvas. */
export function confetti(): () => void {
    const noop = (): void => {};
    if (typeof window === 'undefined' || typeof document === 'undefined' || reducedMotion()) return noop;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return noop;

    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = window.innerWidth;
    const h = window.innerHeight;
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    canvas.className = 'confetti';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = `position:fixed;inset:0;width:${w}px;height:${h}px;pointer-events:none;z-index:9999`;
    // In full screen only the full-screen element is drawn, so the canvas has to live inside it.
    (document.fullscreenElement ?? document.body).appendChild(canvas);
    ctx.scale(dpr, dpr);

    const ps = makeParticles(w, h, w < 600 ? 110 : 160);
    const t0 = performance.now();
    let raf = 0;
    let stopped = false;

    const stop = (): void => {
        if (stopped) return;
        stopped = true;
        cancelAnimationFrame(raf);
        canvas.remove();
    };

    const frame = (now: number): void => {
        const alive = step(ps, h);
        ctx.clearRect(0, 0, w, h);
        for (const p of ps) {
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = p.color;
            ctx.fillRect((-p.w / 2) * Math.cos(p.flip), -p.h / 2, p.w * Math.cos(p.flip), p.h);
            ctx.restore();
        }
        if (alive === 0 || now - t0 > MAX_MS) stop();
        else raf = requestAnimationFrame(frame);
    };
    raf = requestAnimationFrame(frame);

    return stop;
}
