import { LessonRunner } from './runner/lesson-runner';
import { GameRunner } from './game/game';
import { destroySharedEngine } from './engine/stockfish';
import { isSoundOn, setSoundOn, SFX } from './core/sound';
import type { Lesson, ServerLessonResult } from './types';

declare global {
    interface Window {
        Livewire?: { dispatch(event: string, payload?: unknown): void };
    }
}

type Island = LessonRunner | GameRunner;

const islands = new Map<HTMLElement, Island>();

function mountOne(el: HTMLElement): void {
    if (islands.has(el)) return;

    if (el.dataset.chessflow === 'game') {
        islands.set(
            el,
            new GameRunner(el, {
                finished(report) {
                    window.Livewire?.dispatch('game-finished', report);
                },
            }),
        );
        return;
    }

    const raw = el.dataset.lesson;
    if (!raw) return;
    const lesson = JSON.parse(raw) as Lesson;
    islands.set(
        el,
        new LessonRunner(el, lesson, {
            complete(result) {
                window.Livewire?.dispatch('lesson-completed', result);
            },
        }),
    );
}

function unmountAll(): void {
    islands.forEach((island) => island.destroy());
    islands.clear();
    destroySharedEngine();
}

function mountAll(): void {
    document.querySelectorAll<HTMLElement>('[data-chessflow]').forEach(mountOne);
}

function syncSoundButtons(): void {
    document.querySelectorAll<HTMLElement>('[data-sound-toggle]').forEach((b) => {
        b.setAttribute('aria-pressed', String(isSoundOn()));
        b.textContent = 'Bunyi: ' + (isSoundOn() ? 'Hidup' : 'Tutup');
    });
}

// Server replies arrive as Livewire browser events (bubbling to window).
window.addEventListener('lesson-result', (e) => {
    const detail = (e as CustomEvent<ServerLessonResult>).detail;
    islands.forEach((island) => {
        if (island instanceof LessonRunner) island.showResult(detail);
    });
});

window.addEventListener('game-result', (e) => {
    const detail = (e as CustomEvent<{ xp: number; totalXp?: number }>).detail;
    islands.forEach((island) => {
        if (island instanceof GameRunner) island.showResult(detail);
    });
});

document.addEventListener('click', (e) => {
    const btn = (e.target as HTMLElement).closest('[data-sound-toggle]');
    if (!btn) return;
    setSoundOn(!isSoundOn());
    syncSoundButtons();
    SFX.good();
});

mountAll();
syncSoundButtons();
document.addEventListener('livewire:navigated', () => {
    mountAll();
    syncSoundButtons();
});
document.addEventListener('livewire:navigating', unmountAll);
