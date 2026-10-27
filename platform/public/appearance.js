(() => {
    'use strict';
    const root = document.documentElement;
    const themes = { light: 'Light', dark: 'Dark', 'neon-cyan': 'Neon Cyan', 'neon-emerald': 'Neon Emerald', 'neon-amber': 'Neon Amber' };
    if (root.dataset.clientTheme === 'legacy') themes.client = 'Client theme';
    if (root.dataset.clientTheme === 'generated') Object.assign(themes, {'client-light': 'Client Light', 'client-dark': 'Client Dark'});
    const resolveTheme = value => value === 'client' && Object.hasOwn(themes, 'client-light') ? 'client-light' : value;
    const key = root.dataset.themeKey;
    const fallback = Object.hasOwn(themes, root.dataset.defaultTheme) ? root.dataset.defaultTheme : 'dark';
    const account = !!root.dataset.preferenceClient;
    let theme = account ? root.dataset.theme : fallback;
    if (!account) try { const saved = localStorage.getItem(key); const resolved = resolveTheme(saved); if (Object.hasOwn(themes, resolved)) { theme = resolved; if (saved !== resolved) localStorage.setItem(key, resolved); } } catch { /* Use server default when storage is unavailable. */ }
    root.dataset.theme = theme;
    function refresh() {
        document.querySelectorAll('[data-theme-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === root.dataset.theme)));
        document.querySelectorAll('[data-theme-label]').forEach(label => { label.textContent = themes[root.dataset.theme]; });
    }
    function announce(message, failed = false) { const status = document.getElementById('appearance-status'); if (status) { status.textContent = message; status.className = failed ? 'notice error' : 'sr-only'; } }
    let saving = false;
    async function apply(value, persist = true) {
        if (value !== null && !Object.hasOwn(themes, value)) return;
        if (account && persist) {
            if (saving) return;
            saving = true;
            announce('Saving theme…');
            try {
                if (!root.dataset.preferenceUrl) throw new Error('Theme preferences are not ready. Please reload or contact your administrator.');
                const response = await fetch(root.dataset.preferenceUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body:JSON.stringify({client_id:root.dataset.preferenceClient,version:Number(root.dataset.preferenceVersion),theme:value})});
                if (!response.ok) throw new Error(response.status === 409 ? 'Your theme changed elsewhere. Reload before choosing again.' : 'Theme could not be saved. Reload and try again.');
                const result = await response.json();
                root.dataset.preferenceVersion = result.version;
                root.dataset.preferenceDecided = '1';
                value = result.theme;
                document.getElementById('legacy-theme-choice')?.remove();
                try { localStorage.removeItem(key); } catch {}
            } catch (error) { announce(error.message, true); return; }
            finally { saving = false; }
        } else if (persist) {
            try { value === null ? localStorage.removeItem(key) : localStorage.setItem(key,value); } catch {}
        }
        root.dataset.theme = value ?? fallback;
        refresh();
        announce(themes[root.dataset.theme] + ' theme selected.');
    }
    // Delegate immediately so a visible choice works while deferred scripts load.
    document.addEventListener('click', event => {
        const button = event.target instanceof Element ? event.target.closest('[data-theme-choice]') : null;
        const reset = event.target instanceof Element ? event.target.closest('[data-theme-reset]') : null;
        if (reset) {
            apply(null);
            return;
        }
        if (!button) return;
        apply(button.dataset.themeChoice);
        const menu = button.closest('details');
        if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
    });
    document.addEventListener('DOMContentLoaded', () => {
        refresh();
        if (account && root.dataset.preferenceDecided !== '1') {
            let old;
            try { old = resolveTheme(localStorage.getItem(key)); } catch {}
            if (Object.hasOwn(themes, old)) {
                const notice = document.createElement('section');
                notice.id = 'legacy-theme-choice'; notice.className = 'notice'; notice.setAttribute('aria-label','Previous browser theme');
                const text = document.createElement('p'); text.textContent = 'This browser previously used ' + themes[old] + '. Keep it for your account in this client, or use the client default?';
                const keep = document.createElement('button'); keep.type='button'; keep.textContent='Keep previous theme'; keep.addEventListener('click',()=>apply(old));
                const reset = document.createElement('button'); reset.type='button'; reset.textContent='Use client default'; reset.addEventListener('click',()=>apply(null));
                notice.append(text,keep,reset); document.getElementById('main')?.prepend(notice);
            }
        }


        document.addEventListener('click', event => document.querySelectorAll('.theme-picker[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || event.defaultPrevented) return;
            const menu = document.querySelector('.theme-picker[open]');
            if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
        });
        const form = document.getElementById('brand-preview-form');
        if (form) {
            const previews = [...document.querySelectorAll('[data-palette-preview]')];
            const picker = document.getElementById('preview-accent');
            const hex = form.elements.namedItem('preview_accent');
            const status = document.getElementById('brand-colour-status');
            function renderBrand() {
                const mode = form.elements.namedItem('preview_mode').value;
                const client = form.elements.namedItem('preview_client').value.trim() || 'Your organisation';
                const name = form.elements.namedItem('preview_name').value.trim() || 'Assessment Training Tracker';
                for (const preview of previews) {
                    preview.querySelector('[data-preview-brand]').textContent = mode === 'cerebru' ? 'Cerebru' : client;
                    preview.querySelector('[data-preview-name]').textContent = name;
                    preview.querySelector('[data-preview-parent]').hidden = mode === 'white-label';
                }
                if (!/^#[0-9a-f]{6}$/i.test(hex.value)) {
                    status.textContent = 'Enter a colour as #RRGGBB to update the previews.';
                    return;
                }
                picker.value = hex.value;
                const palettes = ClientPalette.generate(hex.value);
                for (const preview of previews) {
                    for (const [token, value] of Object.entries(palettes[preview.dataset.palettePreview])) preview.style.setProperty('--preview-' + token, value);
                }
                status.textContent = 'Light and dark previews updated. Your chosen colour stays the source; shades adjust for readability.';
            }
            picker.addEventListener('input', () => { hex.value = picker.value; renderBrand(); });
            picker.addEventListener('change', () => { hex.value = picker.value; renderBrand(); });
            form.addEventListener('input', renderBrand);
            form.addEventListener('change', renderBrand);
            if (!form.hasAttribute('data-save-branding')) form.addEventListener('submit', event => event.preventDefault());
            const upload = form.elements.namedItem('logo');
            const originalLogos = previews.map(preview => preview.querySelector('img').src);
            let objectUrl;
            upload?.addEventListener('change', () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                const file = upload.files[0];
                objectUrl = file && ['image/png', 'image/jpeg', 'image/webp'].includes(file.type) ? URL.createObjectURL(file) : null;
                previews.forEach((preview, i) => { preview.querySelector('img').src = objectUrl || originalLogos[i]; });
            });
            form.addEventListener('reset', () => requestAnimationFrame(renderBrand));
            renderBrand();
        }
        // A broken client logo must never leave an empty brand slot.
        document.querySelectorAll('.brand-logo').forEach(img => img.addEventListener('error', () => { if (!img.src.endsWith('/images/training-mark.svg')) img.src = '/images/training-mark.svg'; }));
    });
    addEventListener('storage', event => { if (!account && event.key === key) apply(Object.hasOwn(themes, resolveTheme(event.newValue)) ? resolveTheme(event.newValue) : fallback, false); });
})();
