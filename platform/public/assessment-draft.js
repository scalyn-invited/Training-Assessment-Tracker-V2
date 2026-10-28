(() => {
    const form = document.querySelector('[data-assessment-draft]');
    if (!form) return;
    const status = form.querySelector('[role="status"]');
    let timer, pending = false, changed = false, conflict = false;
    async function save() {
        if (pending || conflict) return;
        pending = true; changed = false;
        status.textContent = 'Saving response…';
        try {
            const response = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok) {
                conflict = response.status === 409;
                throw new Error(result.message || 'Save failed.');
            }
            document.querySelectorAll('input[name="expected_version"]').forEach(input => input.value = result.version);
            const submit = document.querySelector('[data-submit-assessment]');
            if (submit) submit.disabled = changed;
            status.textContent = 'Response saved. It has not been submitted.';
        } catch (error) {
            status.textContent = (conflict ? 'This draft changed in another window. Copy your edits before reloading. ' : 'Response could not be saved. Retry when connected. ') + 'Your text remains on this page.';
            const submit = document.querySelector('[data-submit-assessment]');
            if (submit) submit.disabled = true;
        } finally {
            pending = false;
            if (changed && !conflict) timer = setTimeout(save, 900);
        }
    }
    form.addEventListener('input', () => {
        changed = true; clearTimeout(timer);
        const submit = document.querySelector('[data-submit-assessment]');
        if (submit) submit.disabled = true;
        timer = setTimeout(save, 900);
    });
    form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); save(); });
})();
