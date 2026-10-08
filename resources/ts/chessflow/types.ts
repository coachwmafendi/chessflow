// Step/Lesson shapes mirror data/lessons.json (see MIGRATION_PLAN.md §6).
// A square spec is either plain algebraic ("e4") or a line spec ("file:e" / "rank:4").
export type SquareSpec = string;

export type ArrowSpec =
    | [string, string]
    | { from: string; to: string; kind?: 'coral' | 'path' }
    | { path: number[]; kind?: 'coral' | 'path' };

export interface DemoNote {
    arrows?: ArrowSpec[];
    ring?: SquareSpec[];
    status?: string;
}

export interface QuizOption {
    t: string;
    ok?: boolean;
    why?: string;
}

export interface TapItem {
    ask: string;
    sq: string[];
    all?: boolean;
    ok?: string;
    wrong?: string;
    arrows?: ArrowSpec[];
    dotsOf?: string[];
}

export interface PuzzleAccept {
    piece?: string;
    notPiece?: string;
    to?: string[];
    toFile?: string[];
    capture?: boolean;
    noMateInOne?: boolean;
}

interface StepBase {
    title: string;
    say?: string;
    task?: string;
    fen?: string;
    pcs?: string;
    orient?: 'w' | 'b';
    hl?: SquareSpec[];
    ring?: SquareSpec[];
    dots?: SquareSpec[];
    arrows?: ArrowSpec[];
    counter?: string;
}

export interface ExplainStep extends StepBase {
    type: 'explain';
    showMoves?: string;
    initPath?: string;
    demo?: string[];
    demoNotes?: Record<number, DemoNote>;
    demoEnd?: string;
}

export interface FindStep extends StepBase {
    type: 'find';
    piece: string;
    done?: string;
}

export interface CollectStep extends StepBase {
    type: 'collect';
    piece: string;
    stars: string[];
}

export interface TapStep extends StepBase {
    type: 'tap';
    seq: TapItem[];
    wrong?: string;
    wrongMap?: Record<string, string>;
}

export interface QuizStep extends StepBase {
    type: 'quiz';
    options: QuizOption[];
}

export interface PuzzleStep extends StepBase {
    type: 'puzzle';
    line?: string[];
    alts?: Record<number, string[]>;
    acceptSan?: string[];
    acceptMate?: boolean;
    accept?: PuzzleAccept;
    after1?: { arrows?: ArrowSpec[]; status?: string };
    mids?: string[];
    mid?: string;
    hintRing?: SquareSpec[];
    wrongMsg?: string;
    win?: string;
}

export type BotKind = 'flee' | 'chase' | 'defend';
export type PlayGoal = 'mate' | 'promote' | 'captureQ';

export interface PlayStep extends StepBase {
    type: 'play';
    goal: PlayGoal;
    bot: BotKind;
    maxMoves: number;
    win: string;
}

export type Step =
    | ExplainStep
    | FindStep
    | CollectStep
    | TapStep
    | QuizStep
    | PuzzleStep
    | PlayStep;

export interface Lesson {
    id: string;
    tahap: number;
    title: string;
    icon: string;
    exam?: boolean;
    daily?: boolean;
    tip?: string | null;
    steps: Step[];
    position?: number;
    xp?: number;
}

export interface LessonsFile {
    levels: { n: number; name: string; note: string }[];
    lessons: Lesson[];
}

// Flat piece as used by board rendering and move generation (not chess.js internals).
export interface Piece {
    c: 'w' | 'b';
    t: string;
    sq: number;
}

export interface LessonResult {
    slug: string;
    mistakes: number;
    failedSteps: number[];
    durationMs: number;
}

// What the server sends back (Livewire `lesson-result`) after it has recorded a finished lesson.
export interface ServerLessonResult {
    xp: number;
    passed: boolean;
    stars?: number;
    streak?: number;
    totalXp?: number;
    totalStars?: number;
    mapUrl?: string;
    next?: { url: string; title: string } | null;
    certificateUrl?: string | null;
    retryUrl?: string | null;
    // Set when the server refused to record the result (rate limit / too fast).
    message?: string;
}

// Context handed to each step-type runner by LessonRunner.
export interface StepContext {
    newBoard(orient?: 'w' | 'b'): BoardApi;
    board(): BoardApi | null;
    later(fn: () => void, ms: number): void;
    clear(): void;
    status(text: string, kind?: 'good' | 'bad' | 'info' | ''): void;
    statusHtml(html: string): void;
    counter(text: string): void;
    task(html: string): void;
    opts(): HTMLElement;
    mistake(n?: number): void;
    actions(list: { label: string; fn: () => void }[]): void;
    done(): void;
}

export type SquareSpecOrIndex = SquareSpec | number;

export interface MarkPatch {
    dots?: SquareSpecOrIndex[];
    good?: SquareSpecOrIndex[];
    ring?: SquareSpecOrIndex[];
    stars?: SquareSpecOrIndex[];
    hl?: SquareSpecOrIndex[];
    sel?: SquareSpecOrIndex | null;
}

export interface ArrowDrawSpec {
    from?: number;
    to?: number;
    path?: number[];
    kind?: 'coral' | 'path';
}

// Shape of the Board DOM controller (see core/board.ts).
export interface BoardApi {
    orient: 'w' | 'b';
    set(list: Piece[]): void;
    setFen(fen: string): void;
    quiet(fen: string): void;
    add(p: Piece): number;
    at(i: number): (Piece & { id: number; el: HTMLElement }) | null;
    all(): (Piece & { id: number; el: HTMLElement })[];
    remove(i: number): void;
    move(from: number, to: number): void;
    applyMove(m: { from: string; to: string; flags: string; promotion?: string }): void;
    mark(patch: MarkPatch): void;
    flash(i: number): void;
    arrows(list: (ArrowSpec | ArrowDrawSpec)[]): void;
    on(fn: (sq: number) => void): void;
}
