// Remembers which map levels (<details data-level>) a student opened or closed, per device and per
// account (students may share a school tablet). The server picks the defaults; a saved choice wins.

type Store = Pick<Storage, 'getItem' | 'setItem'>;

function defaultStore(): Store | null {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function initMapLevels(root: ParentNode = document, store: Store | null = defaultStore()): void {
    const holder = root.querySelector<HTMLElement>('[data-map-store]');
    const key = holder?.dataset.mapStore;
    if (!holder || !key) return;

    let saved: Record<string, boolean> = {};
    try {
        const raw = JSON.parse(store?.getItem(key) ?? '{}');
        if (raw && typeof raw === 'object') saved = raw as Record<string, boolean>;
    } catch {
        // Unreadable or blocked storage: keep the server's defaults.
    }

    holder.querySelectorAll<HTMLDetailsElement>('details[data-level]').forEach((d) => {
        const level = d.dataset.level as string;
        if (typeof saved[level] === 'boolean') d.open = saved[level];
        d.addEventListener('toggle', () => {
            saved[level] = d.open;
            try {
                store?.setItem(key, JSON.stringify(saved));
            } catch {
                // Private mode: the choice simply is not remembered.
            }
        });
    });
}
