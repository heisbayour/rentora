/**
 * PWA support (spec 8.14): registers the service worker on pages that link a
 * manifest, and keeps the Android install prompt in an Alpine store so the
 * portal can show an "Install app" card (iOS gets instructions instead).
 */
const DISMISS_KEY = 'rentora.install.dismissed';

function dismissed() {
    try {
        return window.localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

export function registerPwa(Alpine) {
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ios = /iphone|ipad|ipod/i.test(window.navigator.userAgent);

    Alpine.store('install', {
        deferred: null,
        canPrompt: false,
        showIos: ios && !standalone && !dismissed(),
        hidden: standalone || dismissed(),
        async prompt() {
            if (!this.deferred) {
                return;
            }
            this.deferred.prompt();
            await this.deferred.userChoice;
            this.deferred = null;
            this.canPrompt = false;
        },
        dismiss() {
            this.hidden = true;
            this.showIos = false;
            try {
                window.localStorage.setItem(DISMISS_KEY, '1');
            } catch {
                // Ignore unavailable storage.
            }
        },
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        const store = Alpine.store('install');
        store.deferred = event;
        store.canPrompt = !store.hidden;
    });

    if ('serviceWorker' in navigator && document.querySelector('link[rel="manifest"]')) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // The site works without the service worker.
            });
        });
    }
}
