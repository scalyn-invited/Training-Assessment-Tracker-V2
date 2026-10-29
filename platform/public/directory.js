(() => {
    const openers = new WeakMap();
    function open(dialog, opener) {
        if (!dialog || dialog.open) return;
        openers.set(dialog, opener || document.activeElement);
        dialog.showModal();
        (dialog.querySelector('[aria-invalid="true"]') || dialog.querySelector('input:not([type=hidden]),button'))?.focus();
    }
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-directory]');
        if (trigger) open(document.getElementById(trigger.dataset.openDirectory), trigger);
        const close = event.target.closest('[data-close-directory]');
        if (close) close.closest('dialog').close();
    });
    document.addEventListener('close', event => {
        const opener = openers.get(event.target);
        if (opener?.isConnected) opener.focus();
    }, true);
    function enhance() {
        document.querySelectorAll('.directory-table').forEach(table => {
            const labels = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
            table.querySelectorAll('tbody tr').forEach(row => [...row.children].forEach((cell, i) => {
                if (i && !cell.hasAttribute('colspan') && !cell.dataset.label) cell.dataset.label = labels[i] || '';
            }));
        });
        document.querySelectorAll('dialog[data-reopen="1"]').forEach(dialog => { dialog.dataset.reopen = '0'; open(dialog); });
    }
    enhance();
    document.addEventListener('livewire:navigated', enhance);
    document.addEventListener('livewire:init', () => Livewire.hook('morphed', enhance));
})();
