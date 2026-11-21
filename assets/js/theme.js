/*
 * Loaded synchronously from <head> (no defer) so data-theme is on <html> before first paint.
 * Modes: "system" (no attribute, follows prefers-color-scheme), "light", "dark".
 */
(function () {
    'use strict';

    const KEY = 'shepherd-theme';
    const MODES = ['system', 'light', 'dark'];
    const LABELS = { system: 'System', light: 'Light', dark: 'Dark' };
    const root = document.documentElement;

    root.classList.add('js');

    function load() {
        try {
            const stored = window.localStorage.getItem(KEY);
            return MODES.indexOf(stored) === -1 ? 'system' : stored;
        } catch (e) {
            return 'system';
        }
    }

    function store(mode) {
        try {
            if (mode === 'system') {
                window.localStorage.removeItem(KEY);
            } else {
                window.localStorage.setItem(KEY, mode);
            }
        } catch (e) {
            // Storage blocked: the choice still applies for this page view.
        }
    }

    // The two <meta name="theme-color" media="..."> tags follow the OS; an explicit choice overrides them.
    function syncThemeColor(mode) {
        document.querySelectorAll('meta[name="theme-color"]').forEach(function (meta) {
            const own = meta.getAttribute('data-media') || meta.getAttribute('media') || '';
            meta.setAttribute('data-media', own);
            const isDarkTag = own.indexOf('dark') !== -1;
            if (mode === 'system') {
                meta.setAttribute('media', own);
            } else {
                meta.setAttribute('media', (mode === 'dark') === isDarkTag ? 'all' : 'not all');
            }
        });
    }

    function apply(mode) {
        if (mode === 'system') {
            root.removeAttribute('data-theme');
        } else {
            root.setAttribute('data-theme', mode);
        }
        syncThemeColor(mode);
        document.dispatchEvent(new CustomEvent('shepherd:themechange', { detail: { mode: mode } }));
    }

    let current = load();
    apply(current);

    function describe(button, announce) {
        const next = MODES[(MODES.indexOf(current) + 1) % MODES.length];
        const label = button.querySelector('[data-theme-label]');
        if (label) {
            label.textContent = LABELS[current];
        }
        button.setAttribute('aria-label', 'Theme: ' + LABELS[current] + '. Switch to ' + LABELS[next] + '.');
        if (announce) {
            const status = document.querySelector('[data-theme-status]');
            if (status) {
                status.textContent = 'Theme: ' + LABELS[current];
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const button = document.querySelector('[data-theme-toggle]');
        if (!button) {
            return;
        }
        describe(button, false);
        button.addEventListener('click', function () {
            current = MODES[(MODES.indexOf(current) + 1) % MODES.length];
            store(current);
            apply(current);
            describe(button, true);
        });
        // Keep several open tabs in sync.
        window.addEventListener('storage', function (event) {
            if (event.key === KEY || event.key === null) {
                current = load();
                apply(current);
                describe(button, false);
            }
        });
    });
}());
