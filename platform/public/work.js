const draftForm = document.querySelector('form[data-autosave]');
const submitForm = document.querySelector('form[data-submit-work]');
if (draftForm && submitForm) {
    const submit = submitForm.querySelector('button');
    if (draftForm.dataset.dirty === 'true') submit.disabled = true;
    submitForm.addEventListener('submit', event => {
        if (draftForm.dataset.dirty === 'true') event.preventDefault();
    });
    draftForm.addEventListener('input', () => { submit.disabled = true; });
    draftForm.addEventListener('draft:saved', event => {
        submitForm.elements.expected_version.value = event.detail.version;
        submit.disabled = event.detail.dirty;
    });
}
