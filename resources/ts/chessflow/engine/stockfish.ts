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

/** Engine verdict for a position, from the side to move: centipawns, or moves to mate (negative = being mated). */
export interface Evaluation {
    best: string | null;
    cp: number | null;
    mate: number | null;
}

interface Job {
    req: EngineRequest;
    resolve: (r: Evaluation | null) => void;
    timer?: ReturnType<typeof setTimeout>;
    cp: number | null;
    mate: number | null;
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
        return this.run(req).then((r) => r?.best ?? null);
    }

    /** Full-strength search that also returns the score (post-game analysis). */
    analyse(req: { fen: string; depth: number; timeoutMs?: number }): Promise<Evaluation | null> {
        return this.run({ ...req, skill: 20 });
    }

    private run(req: EngineRequest): Promise<Evaluation | null> {
        if (!this.ensure()) return Promise.resolve(null);
        return new Promise((resolve) => {
            this.queue.push({ req, resolve, cp: null, mate: null });
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
        if (line.startsWith('info') && this.current && this.discard === 0) {
            // Keep the deepest score seen; "score cp 34" / "score mate -2" (side to move).
            const m = / score (cp|mate) (-?\d+)/.exec(line);
            if (m && !/ (lowerbound|upperbound)/.test(line)) {
                this.current.cp = m[1] === 'cp' ? Number(m[2]) : null;
                this.current.mate = m[1] === 'mate' ? Number(m[2]) : null;
            }
            return;
        }
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
        job.resolve({ best: move && move !== '(none)' ? move : null, cp: job.cp, mate: job.mate });
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
