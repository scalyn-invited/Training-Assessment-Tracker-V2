// Draft text stays in the form on validation, network and conflict errors.
// No assessment or profile content is stored in browser storage.
document.querySelectorAll('form[data-autosave], form[data-preserve-input]').forEach(form => {
    const status = form.querySelector('.save-status');
    let timer, pending = false, dirty = false, conflicted = false;
    let generation = 0;
    async function save(submitter) {
        clearTimeout(timer);
        if (pending) {
            status.textContent = 'A save is already in progress. Please wait, then retry this action.';
            return;
        }
        if (conflicted) return;
        pending = true;
        const sentGeneration = generation;
        status.textContent = 'Saving…';
        const data = new FormData(form);
        if (submitter?.name) data.set(submitter.name, submitter.value);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
            if (response.redirected) throw new Error('Your session may have expired. Copy your unsaved input before signing in again.');
            const result = await response.json();
            if (!response.ok) {
                conflicted = response.status === 409;
                throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Save failed. Your input is still here.'));
            }
            if (result.redirect) {
                dirty = false;
                pending = false;
                location.assign(result.redirect);
                return;
            }
            form.elements.expected_version.value = result.version;
            dirty = generation !== sentGeneration;
            status.textContent = result.message + (dirty ? ' Newer edits are waiting to save.' : '');
            const confirmation = document.getElementById('assessment-confirmation');
            if (confirmation) confirmation.textContent = result.confirmed ? 'Confirmed by coordinator for the saved assessment.' : 'Assessment confirmation pending.';
        } catch (error) {
            dirty = true;
            status.textContent = error.message + ' Your input has not been discarded.';
            return;
        } finally {
            pending = false;
        }
        if (dirty && !conflicted && form.hasAttribute('data-autosave')) timer = setTimeout(() => save(), 800);
    }
    form.addEventListener('input', () => {
        dirty = true;
        generation++;
        status.textContent = 'Unsaved changes.';
        if (form.hasAttribute('data-autosave') && !conflicted) {
            clearTimeout(timer);
            timer = setTimeout(() => save(), 800);
        }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        save(event.submitter);
    });
    window.addEventListener('beforeunload', event => {
        if (dirty || pending) { event.preventDefault(); event.returnValue = ''; }
    });
    // A person can type before a deferred script finishes downloading.
    // Capture those edits as well as input events after enhancement.
    const editedBeforeLoad = [...form.elements].some(control => {
        if (control.type === 'checkbox' || control.type === 'radio') return control.checked !== control.defaultChecked;
        if (control.tagName === 'SELECT') {
            const defaults = [...control.options].filter(option => option.defaultSelected);
            const initial = (defaults.length ? defaults : [...control.options].slice(0, 1)).map(option => option.value);
            return JSON.stringify([...control.selectedOptions].map(option => option.value)) !== JSON.stringify(initial);
        }
        return ['INPUT', 'TEXTAREA'].includes(control.tagName) && control.value !== control.defaultValue;
    });
    form.dataset.ready = 'true';
    if (editedBeforeLoad) form.dispatchEvent(new Event('input'));
});
