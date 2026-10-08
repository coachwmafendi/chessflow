import { LessonRunner } from './runner/lesson-runner';
import type { Lesson } from './types';

declare global {
    interface Window {
        Livewire?: { dispatch(event: string, payload?: unknown): void };
    }
}

const runners = new Map<HTMLElement, LessonRunner>();

function mountOne(el: HTMLElement): void {
    if (runners.has(el)) return;
    const raw = el.dataset.lesson;
    if (!raw) return;
    const lesson = JSON.parse(raw) as Lesson;
    const runner = new LessonRunner(el, lesson, {
        complete(result) {
            window.Livewire?.dispatch('lesson-completed', result);
        },
    });
    runners.set(el, runner);
}

function unmountAll(): void {
    runners.forEach((runner) => runner.destroy());
    runners.clear();
}

function mountAll(): void {
    document.querySelectorAll<HTMLElement>('[data-chessflow]').forEach(mountOne);
}

mountAll();
document.addEventListener('livewire:navigated', mountAll);
document.addEventListener('livewire:navigating', unmountAll);
