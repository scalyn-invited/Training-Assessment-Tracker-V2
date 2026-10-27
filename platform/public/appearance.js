(() => {
    'use strict';
    const root = document.documentElement;
    const themes = { light: 'Light', dark: 'Dark', 'neon-cyan': 'Neon Cyan', 'neon-emerald': 'Neon Emerald', 'neon-amber': 'Neon Amber' };
    const key = root.dataset.themeKey;
    const fallback = Object.hasOwn(themes, root.dataset.defaultTheme) ? root.dataset.defaultTheme : 'dark';
    let theme = fallback;
    try { const saved = localStorage.getItem(key); if (Object.hasOwn(themes, saved)) theme = saved; } catch { /* Use server default when storage is unavailable. */ }
    root.dataset.theme = theme;
    function refresh() {
        document.querySelectorAll('[data-theme-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === root.dataset.theme)));
        document.querySelectorAll('[data-theme-label]').forEach(label => { label.textContent = themes[root.dataset.theme]; });
    }
    function announce(message) { const status = document.getElementById('appearance-status'); if (status) status.textContent = message; }
    function apply(value, persist = true) {
        if (!Object.hasOwn(themes, value)) return;
        root.dataset.theme = value;
        let saved = true;
        if (persist) { try { localStorage.setItem(key, value); } catch { saved = false; } }
        refresh();
        announce(themes[value] + ' theme selected.' + (saved ? '' : ' Browser storage is unavailable; this choice applies to this page only.'));
    }
    // Delegate immediately so a visible choice works while deferred scripts load.
    document.addEventListener('click', event => {
        const button = event.target instanceof Element ? event.target.closest('[data-theme-choice]') : null;
        const reset = event.target instanceof Element ? event.target.closest('[data-theme-reset]') : null;
        if (reset) {
            try { localStorage.removeItem(key); } catch { /* The page can still reset. */ }
            apply(fallback, false);
            return;
        }
        if (!button) return;
        apply(button.dataset.themeChoice);
        const menu = button.closest('details');
        if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
    });
    document.addEventListener('DOMContentLoaded', () => {
        refresh();


        document.addEventListener('click', event => document.querySelectorAll('.theme-picker[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || event.defaultPrevented) return;
            const menu = document.querySelector('.theme-picker[open]');
            if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
        });
        const form = document.getElementById('brand-preview-form');
        if (form) {
            const preview = document.getElementById('brand-preview');
            function renderBrand() {
                const mode = form.elements.namedItem('preview_mode').value;
                const client = form.elements.namedItem('preview_client').value.trim() || 'Your organisation';
                const name = form.elements.namedItem('preview_name').value.trim() || 'Assessment Training Tracker';
                preview.querySelector('[data-preview-brand]').textContent = mode === 'cerebru' ? 'Cerebru' : client;
                preview.querySelector('[data-preview-name]').textContent = name;
                preview.querySelector('[data-preview-parent]').hidden = mode === 'white-label';
                preview.style.setProperty('--brand', form.elements.namedItem('preview_accent').value);
            }
            form.addEventListener('input', renderBrand);
            form.addEventListener('change', renderBrand);
            form.addEventListener('submit', event => event.preventDefault());
            form.addEventListener('reset', () => requestAnimationFrame(renderBrand));
            renderBrand();
        }
        // A broken client logo must never leave an empty brand slot.
        document.querySelectorAll('.brand-logo').forEach(img => img.addEventListener('error', () => { if (!img.src.endsWith('/images/training-mark.svg')) img.src = '/images/training-mark.svg'; }));
    });
    addEventListener('storage', event => { if (event.key === key) apply(Object.hasOwn(themes, event.newValue) ? event.newValue : fallback, false); });
})();
