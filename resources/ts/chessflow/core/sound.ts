const STORAGE_KEY = 'chessflow-snd';

let soundOn = true;
try {
    if (typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '0') soundOn = false;
} catch {
    // localStorage unavailable (SSR/tests) — keep sound on by default.
}

let ac: AudioContext | null = null;

function tone(seq: [number, number][]): void {
    if (!soundOn || typeof window === 'undefined') return;
    try {
        ac = ac || new (window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();
        let t = ac.currentTime;
        for (const [f, d] of seq) {
            const o = ac.createOscillator();
            const g = ac.createGain();
            o.type = 'triangle';
            o.frequency.value = f;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.16, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + d);
            o.connect(g).connect(ac.destination);
            o.start(t);
            o.stop(t + d + 0.02);
            t += d * 0.8;
        }
    } catch {
        // Audio unsupported/blocked — fail silently, same as the prototype.
    }
}

export function isSoundOn(): boolean {
    return soundOn;
}

export function setSoundOn(on: boolean): void {
    soundOn = on;
    try {
        localStorage.setItem(STORAGE_KEY, on ? '1' : '0');
    } catch {
        // ignore
    }
}

export const SFX = {
    good: () => tone([[660, 0.12], [880, 0.18]]),
    bad: () => tone([[220, 0.22]]),
    star: () => tone([[988, 0.1], [1318, 0.16]]),
    win: () => tone([[523, 0.12], [659, 0.12], [784, 0.12], [1046, 0.3]]),
    move: () => tone([[420, 0.06]]),
};
