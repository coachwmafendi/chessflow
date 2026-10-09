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

    it('analyse() searches at full strength and returns the last exact score', async () => {
        const p = engine.analyse({ fen: 'F', depth: 10 });
        expect(fake.sent).toContain('setoption name Skill Level value 20');
        fake.emit('info depth 9 seldepth 12 score cp 40 nodes 100 pv e2e4');
        fake.emit('info depth 10 seldepth 14 score cp 95 lowerbound nodes 200 pv d2d4');
        fake.emit('info depth 10 seldepth 14 score cp 61 nodes 300 pv d2d4');
        fake.emit('bestmove d2d4 ponder d7d5');
        await expect(p).resolves.toEqual({ best: 'd2d4', cp: 61, mate: null });
    });

    it('analyse() reports mate scores and finished positions', async () => {
        const a = engine.analyse({ fen: 'A', depth: 10 });
        fake.emit('info depth 5 score mate -2 pv e8f8');
        fake.emit('bestmove e8f8');
        await expect(a).resolves.toEqual({ best: 'e8f8', cp: null, mate: -2 });
        const b = engine.analyse({ fen: 'B', depth: 10 });
        fake.emit('info depth 0 score mate 0');
        fake.emit('bestmove (none)');
        await expect(b).resolves.toEqual({ best: null, cp: null, mate: 0 });
    });

    it('does not mix the score of a timed-out search into the next one', async () => {
        const slow = engine.analyse({ fen: 'A', depth: 20, timeoutMs: 500 });
        const next = engine.analyse({ fen: 'B', depth: 10 });
        vi.advanceTimersByTime(500);
        await expect(slow).resolves.toBeNull();
        fake.emit('info depth 18 score cp 900'); // still the old search
        fake.emit('bestmove a2a3');
        fake.emit('info depth 10 score cp -15');
        fake.emit('bestmove b7b6');
        await expect(next).resolves.toEqual({ best: 'b7b6', cp: -15, mate: null });
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
