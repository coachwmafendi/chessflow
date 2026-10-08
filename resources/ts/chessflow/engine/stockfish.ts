// Stockfish 10 in a Web Worker (public/stockfish/, same origin). One worker is shared by the
// page; requests are queued so a hint and a bot move never talk over each other. Any failure
// resolves with null so callers can fall back to simple-bot.

export interface WorkerLike {
    onmessage: ((e: { data: unknown }) => void) | null;
    onerror: ((e: unknown) => void) | null;
    postMessage(msg: string): void;
    terminate(): void;
}

export interface EngineRequest {
    fen: string;
    skill: number;
    depth: number;
    timeoutMs?: number;
}

interface Job {
    req: EngineRequest;
    resolve: (uci: string | null) => void;
    timer?: ReturnType<typeof setTimeout>;
}

const WORKER_URL = '/stockfish/stockfish.js';

export class StockfishEngine {
    private worker: WorkerLike | null = null;
    private failed = false;
    private queue: Job[] = [];
    private current: Job | null = null;
    // bestmove lines still to come from searches we gave up on.
    private discard = 0;

    constructor(
        private readonly factory: () => WorkerLike = () => new Worker(WORKER_URL) as unknown as WorkerLike,
        private readonly timeoutMs = 9000,
    ) {}

    get available(): boolean {
        return !this.failed;
    }

    bestMove(req: EngineRequest): Promise<string | null> {
        if (!this.ensure()) return Promise.resolve(null);
        return new Promise((resolve) => {
            this.queue.push({ req, resolve });
            this.pump();
        });
    }

    /** Stop the worker and drop pending requests. A later bestMove() starts a fresh worker. */
    destroy(): void {
        this.worker?.terminate();
        this.worker = null;
        this.discard = 0;
        this.flush();
    }

    private ensure(): boolean {
        if (this.worker) return true;
        if (this.failed) return false;
        try {
            const w = this.factory();
            w.onmessage = (e) => this.onLine(typeof e.data === 'string' ? e.data : '');
            w.onerror = () => this.fail();
            this.worker = w;
            w.postMessage('uci');
            w.postMessage('isready');
            return true;
        } catch {
            this.fail();
            return false;
        }
    }

    private pump(): void {
        if (this.current || !this.worker) return;
        const job = this.queue.shift();
        if (!job) return;
        this.current = job;
        const { fen, skill, depth, timeoutMs } = job.req;
        this.worker.postMessage('setoption name Skill Level value ' + skill);
        this.worker.postMessage('position fen ' + fen);
        this.worker.postMessage('go depth ' + depth);
        job.timer = setTimeout(() => {
            if (this.current !== job) return;
            this.discard++;
            this.worker?.postMessage('stop');
            this.current = null;
            job.resolve(null);
            this.pump();
        }, timeoutMs ?? this.timeoutMs);
    }

    private onLine(line: string): void {
        if (!line.startsWith('bestmove')) return;
        if (this.discard > 0) {
            this.discard--;
            return;
        }
        const job = this.current;
        if (!job) return;
        clearTimeout(job.timer);
        this.current = null;
        const move = line.split(' ')[1];
        job.resolve(move && move !== '(none)' ? move : null);
        this.pump();
    }

    private fail(): void {
        this.failed = true;
        this.worker?.terminate();
        this.worker = null;
        this.flush();
    }

    private flush(): void {
        const pending = this.current ? [this.current, ...this.queue] : this.queue;
        this.current = null;
        this.queue = [];
        pending.forEach((job) => {
            clearTimeout(job.timer);
            job.resolve(null);
        });
    }
}

let shared: StockfishEngine | null = null;

export function sharedEngine(): StockfishEngine {
    return (shared ??= new StockfishEngine());
}

export function destroySharedEngine(): void {
    shared?.destroy();
    shared = null;
}
