const SIDEBAR_KEY = 'rentora.sidebar.collapsed';

function readCollapsed() {
    try {
        return window.localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch {
        return false;
    }
}

export function registerStores(Alpine) {
    Alpine.store('sidebar', {
        collapsed: readCollapsed(),
        mobileOpen: false,
        toggle() {
            this.collapsed = !this.collapsed;
            try {
                window.localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
            } catch {
                // Storage can be unavailable (private mode); the toggle still works for this page.
            }
        },
    });

    Alpine.store('toasts', {
        items: [],
        nextId: 1,
        push(message, type = 'success', timeout = 5000) {
            const id = this.nextId++;
            this.items.push({ id, message, type });
            if (timeout > 0) {
                setTimeout(() => this.dismiss(id), timeout);
            }
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    // window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }))
    window.addEventListener('toast', (event) => {
        const { message, type } = event.detail || {};
        if (message) {
            Alpine.store('toasts').push(message, type);
        }
    });
}
