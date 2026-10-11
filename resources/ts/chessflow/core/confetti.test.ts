import { describe, expect, it } from 'vitest';
import { confetti, makeParticles, step } from './confetti';

describe('confetti', () => {
    it('fires from both bottom corners, upwards and towards the middle', () => {
        const ps = makeParticles(1000, 800, 10);
        expect(ps).toHaveLength(10);
        for (const p of ps) {
            expect(p.y).toBe(800);
            expect(p.vy).toBeLessThan(0);
            expect(p.x === 0 ? p.vx > 0 : p.vx < 0).toBe(true);
        }
        expect(ps.some((p) => p.x === 0)).toBe(true);
        expect(ps.some((p) => p.x === 1000)).toBe(true);
    });

    it('rises, then falls off the bottom of the screen', () => {
        const ps = makeParticles(1000, 800, 20);
        step(ps, 800);
        expect(ps.every((p) => p.y < 800)).toBe(true);
        let frames = 0;
        while (step(ps, 800) > 0) {
            frames++;
            expect(frames).toBeLessThan(600); // ~10 s at 60 fps; the real loop also stops after 4.5 s
        }
    });

    it('does nothing without a DOM and still returns a stop function', () => {
        const stop = confetti();
        expect(typeof stop).toBe('function');
        expect(() => stop()).not.toThrow();
    });
});
