document.querySelectorAll('.reveal').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.for);
        const show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        button.textContent = `${show ? 'Hide' : 'Show'} ${field.id === 'setup_token' ? 'token' : 'password'}`;
        button.setAttribute('aria-pressed', String(show));
    });
});
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.busy) { event.preventDefault(); return; }
        form.dataset.busy = 'true';
        document.getElementById('working').hidden = false;
        form.setAttribute('aria-busy', 'true');
        // Preserve the clicked button's value in the submitted form.
        if (event.submitter?.name) {
            form.querySelector('input[name="action"]').value = event.submitter.value;
        }
        form.querySelectorAll('button').forEach(button => { button.disabled = true; });
    });
});
document.getElementById('errors')?.focus();
const advance = document.querySelector('form[data-advance="true"]');
if (advance) window.setTimeout(() => advance.requestSubmit(), 700);
