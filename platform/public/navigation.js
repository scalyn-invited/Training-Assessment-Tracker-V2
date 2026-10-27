(() => {
    const sidebar = document.querySelector('#app-sidebar');
    if (!sidebar) return;
    const toggle = document.querySelector('.menu-toggle');
    const close = sidebar.querySelector('.menu-close');
    const backdrop = document.querySelector('.menu-backdrop');
    const content = document.querySelector('#app-content');
    const mobile = window.matchMedia('(max-width: 800px)');
    let opened = false;
    document.body.classList.add('navigation-ready');

    function setOpen(value, restore = true) {
        opened = value && mobile.matches;
        sidebar.classList.toggle('is-open', opened);
        toggle.setAttribute('aria-expanded', String(opened));
        backdrop.hidden = !opened;
        content.inert = opened;
        sidebar.inert = mobile.matches && !opened;
        document.body.classList.toggle('nav-open', opened);
        if (opened) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            close.focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (restore) toggle.focus();
        }
    }
    function resize() {
        const previousFocus = document.activeElement;
        const focusWasInside = sidebar.contains(previousFocus);
        toggle.hidden = !mobile.matches;
        close.hidden = !mobile.matches;
        setOpen(false, mobile.matches && focusWasInside);
        if (!mobile.matches && previousFocus === close) sidebar.querySelector('nav a')?.focus();
    }
    toggle.addEventListener('click', () => setOpen(!opened));
    close.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => {
        if (mobile.matches) setOpen(false, false);
    }));
    document.addEventListener('keydown', event => {
        if (!opened) return;
        const themeMenu = sidebar.querySelector('.theme-picker[open]');
        if (event.key === 'Escape' && themeMenu) { event.preventDefault(); themeMenu.open = false; themeMenu.querySelector('summary').focus(); return; }
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key !== 'Tab') return;
        const items = [...sidebar.querySelectorAll('a[href], button:not([hidden]), summary')];
        const visible = items.filter(item => {
            const closedDetails = item.closest('details:not([open])');
            return item.getClientRects().length && !item.disabled
                && (!closedDetails || item === closedDetails.querySelector(':scope > summary'));
        });
        const first = visible[0], last = visible.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    mobile.addEventListener('change', resize);
    resize();
})();
