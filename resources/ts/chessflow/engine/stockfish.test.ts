import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { StockfishEngine, type WorkerLike } from './stockfish';

class FakeWorker implements WorkerLike {
    onmessage: ((e: { data: unknown }) => void) | null = null;
    onerror: ((e: unknown) => void) | null = null;
    sent: string[] = [];
    terminated = false;
    postMessage(msg: string): void {
        this.sent.push(msg);
    }
    terminate(): void {
        this.terminated = true;
    }
    emit(line: string): void {
        this.onmessage?.({ data: line });
    }
}

describe('StockfishEngine', () => {
    let fake: FakeWorker;
    let engine: StockfishEngine;

    beforeEach(() => {
        vi.useFakeTimers();
        fake = new FakeWorker();
        engine = new StockfishEngine(() => fake, 1000);
    });
    afterEach(() => vi.useRealTimers());

    it('sends the position and resolves with the best move', async () => {
        const p = engine.bestMove({ fen: 'startpos-fen', skill: 6, depth: 8 });
        expect(fake.sent).toEqual(['uci', 'isready', 'setoption name Skill Level value 6', 'position fen startpos-fen', 'go depth 8']);
        fake.emit('info depth 8 score cp 20');
        fake.emit('bestmove e2e4 ponder e7e5');
        await expect(p).resolves.toBe('e2e4');
    });

    it('queues requests and runs them one at a time', async () => {
        const a = engine.bestMove({ fen: 'A', skill: 0, depth: 2 });
        const b = engine.bestMove({ fen: 'B', skill: 0, depth: 2 });
        expect(fake.sent.filter((m) => m.startsWith('position'))).toEqual(['position fen A']);
        fake.emit('bestmove a2a3');
        await expect(a).resolves.toBe('a2a3');
        expect(fake.sent.filter((m) => m.startsWith('position'))).toEqual(['position fen A', 'position fen B']);
        fake.emit('bestmove b2b3');
        await expect(b).resolves.toBe('b2b3');
    });

    it('times out with null, stops the search and ignores its late answer', async () => {
        const slow = engine.bestMove({ fen: 'A', skill: 0, depth: 20 });
        const next = engine.bestMove({ fen: 'B', skill: 0, depth: 2 });
        vi.advanceTimersByTime(1000);
        await expect(slow).resolves.toBeNull();
        expect(fake.sent).toContain('stop');
        fake.emit('bestmove h2h3'); // late answer for A
        fake.emit('bestmove b2b3');
        await expect(next).resolves.toBe('b2b3');
    });

    it('resolves null for every pending request when the worker fails', async () => {
        const a = engine.bestMove({ fen: 'A', skill: 0, depth: 2 });
        const b = engine.bestMove({ fen: 'B', skill: 0, depth: 2 });
        fake.onerror?.(new Error('wasm failed'));
        await expect(a).resolves.toBeNull();
        await expect(b).resolves.toBeNull();
        expect(engine.available).toBe(false);
        await expect(engine.bestMove({ fen: 'C', skill: 0, depth: 2 })).resolves.toBeNull();
    });

    it('treats a worker that cannot be created as unavailable', async () => {
        const broken = new StockfishEngine(() => {
            throw new Error('no Worker');
        });
        await expect(broken.bestMove({ fen: 'A', skill: 0, depth: 2 })).resolves.toBeNull();
        expect(broken.available).toBe(false);
    });

    it('maps "(none)" to null and terminates on destroy', async () => {
        const p = engine.bestMove({ fen: 'mate', skill: 0, depth: 2 });
        fake.emit('bestmove (none)');
        await expect(p).resolves.toBeNull();
        const pending = engine.bestMove({ fen: 'A', skill: 0, depth: 2 });
        engine.destroy();
        await expect(pending).resolves.toBeNull();
        expect(fake.terminated).toBe(true);
    });
});
