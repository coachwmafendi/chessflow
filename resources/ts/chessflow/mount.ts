import { LessonRunner } from './runner/lesson-runner';
import { GameRunner } from './game/game';
import { destroySharedEngine } from './engine/stockfish';
import { isSoundOn, setSoundOn, SFX } from './core/sound';
import { THEME_KEY, THEME_LABEL, applyTheme, getTheme, nextTheme, setTheme } from './core/theme';
import { ICONS } from './core/toolbar-icons';
import { initMapLevels } from './core/map-levels';
import type { Lesson, ServerLessonResult } from './types';
import type { NewBadge } from './core/badges';
import { t } from './i18n';

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
    const on = isSoundOn();
    document.querySelectorAll<HTMLElement>('[data-sound-toggle]').forEach((b) => {
        b.setAttribute('aria-pressed', String(on));
        b.innerHTML = on ? ICONS.soundOn : ICONS.soundOff;
        b.setAttribute('aria-label', on ? t('Bunyi: Hidup') : t('Bunyi: Tutup'));
        b.dataset.tip = on ? t('Bunyi: Hidup · tekan untuk tutup') : t('Bunyi: Tutup · tekan untuk hidupkan');
    });
}

function syncThemeButtons(): void {
    const theme = getTheme();
    document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((b) => {
        b.innerHTML = ICONS[theme];
        b.setAttribute('aria-label', t('Tema: :name', { name: t(THEME_LABEL[theme]) }));
        b.dataset.tip = t('Tema: :name · tekan untuk tukar', { name: t(THEME_LABEL[theme]) });
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
    const detail = (e as CustomEvent<{ xp: number; totalXp?: number; badges?: NewBadge[] }>).detail;
    islands.forEach((island) => {
        if (island instanceof GameRunner) island.showResult(detail);
    });
});

document.addEventListener('click', (e) => {
    if (!(e.target as HTMLElement).closest('[data-theme-toggle]')) return;
    setTheme(nextTheme(getTheme()));
    syncThemeButtons();
});

// Another tab (or the Flux settings page) changed the theme.
window.addEventListener('storage', (e) => {
    if (e.key !== THEME_KEY && e.key !== null) return;
    applyTheme(getTheme());
    syncThemeButtons();
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
syncThemeButtons();
initMapLevels();
document.addEventListener('livewire:navigated', () => {
    applyTheme(getTheme());
    mountAll();
    syncSoundButtons();
    syncThemeButtons();
    initMapLevels();
});
document.addEventListener('livewire:navigating', unmountAll);
