/* Progressive enhancement: ordinary forms retain server authorization and validation. */
(() => {
    const main = document.getElementById('main');
    if (!main) return;
    let sequence = 0;
    main.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(field => {
        if (field.closest('[wire\\:id]')) return;
        if (!field.id) field.id = 'workspace-field-' + (++sequence);
        if (field.required && field.closest('label')) {
            field.closest('label').classList.add('required-field');
        }
        if (field.name === 'reason') {
            const help = document.createElement('small'); help.className = 'field-help'; help.id = field.id + '-help';
            help.textContent = 'Required: explain the change in at least 8 characters.'; (field.closest('label') || field).after(help); field.setAttribute('aria-describedby', help.id);
        }
    });
    main.querySelectorAll('select[name=account_mode]').forEach(mode => {
        const form = mode.form; const name = form.elements.namedItem('name');
        const updateMode = () => { if (!name) return; const existing = mode.value === 'existing'; name.required = !existing; name.closest('label').hidden = existing; };
        mode.addEventListener('change', updateMode); updateMode();
    });
    const summary = main.querySelector('.error-summary');
    // Preserve edits in this document only. A separate sign-in tab never receives drafts.
    if (location.pathname.startsWith('/platform')) {
        let checking = false, approved = false, dirty = false;
        main.querySelectorAll('[data-discard]').forEach(link => link.addEventListener('click', () => { dirty = false; }));
        main.addEventListener('input', event => { if (event.target.closest('form[method=post]')) dirty = true; });
        addEventListener('beforeunload', event => { if (dirty && !approved) { event.preventDefault(); event.returnValue = ''; } });
        const recovery = document.createElement('dialog'); recovery.className = 'workspace-dialog'; recovery.setAttribute('aria-labelledby','recovery-title');
        recovery.innerHTML = '<h2 id="recovery-title">Sign in to continue</h2><p role="status" id="recovery-message"></p><p>Edits remain only in this tab. Reloading loses them. Passwords must be entered again.</p><div class="dialog-actions"><a class="button" href="/login" target="_blank" rel="noopener">Open sign-in tab</a><button type="button">Return to edits</button></div>';
        document.body.append(recovery); recovery.querySelector('button').onclick = () => recovery.close();
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || !main.contains(form) || approved) return;
            event.preventDefault(); event.stopImmediatePropagation();
            if (checking) return; checking = true;
            try {
                const response = await fetch(location.href, {credentials:'same-origin',cache:'no-store'});
                const page = new DOMParser().parseFromString(await response.text(),'text/html');
                const identity = page.querySelector('meta[name=workspace-account]')?.content;
                const current = document.querySelector('meta[name=workspace-account]')?.content;
                const version = page.querySelector('meta[name=workspace-version]')?.content;
                if (!response.ok || !identity || identity !== current || version !== document.querySelector('meta[name=workspace-version]')?.content) {
                    form.querySelectorAll('input[type=password]').forEach(field => field.value = '');
                    recovery.querySelector('#recovery-message').textContent = !identity ? 'Your session ended or access is unavailable. Sign in separately, then return here and submit again.' : 'The account or its permissions changed. These edits cannot be submitted. Reload to review your current access.';
                    recovery.showModal(); return;
                }
                const token = page.querySelector('meta[name=csrf-token]')?.content;
                if (token && form.elements.namedItem('_token')) form.elements.namedItem('_token').value = token;
                approved = true; form.requestSubmit(event.submitter); approved = false;
                if (form.dataset.submitting) dirty = false;
            } catch {
                recovery.querySelector('#recovery-message').textContent = 'Connection failed. Your edits remain here. Check your connection and try again.'; recovery.showModal();
            } finally { checking = false; }
        }, true);
    }
    summary?.querySelectorAll('[data-error-field]').forEach(item => {
        const candidates = [...main.querySelectorAll('input,select,textarea')].filter(f => f.name === item.dataset.errorField);
        const failedKey = document.querySelector('meta[name=failed-form]')?.content;
        const failedGrant = document.querySelector('meta[name=failed-grant]')?.content;
        const failedClient = document.querySelector('meta[name=failed-client]')?.content;
        const field = candidates.find(f => failedKey && f.form?.elements.namedItem('_form')?.value === failedKey) || candidates.find(f => failedGrant && f.form?.elements.namedItem('grant')?.value === failedGrant) || candidates.find(f => failedClient && f.form?.elements.namedItem('client')?.value === failedClient) || candidates.find(f => f.closest('details[open]')) || candidates[0];
        if (!field) return;
        if (!field.id) field.id = 'workspace-field-' + (++sequence);
        const message = item.textContent;
        const error = document.createElement('p'); error.className = 'field-error'; error.id = field.id + '-error'; error.textContent = message;
        (field.closest('label') || field).after(error); field.setAttribute('aria-invalid','true'); field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'),error.id].filter(Boolean).join(' '));
        const link = document.createElement('a'); link.href = '#' + field.id; link.textContent = message;
        link.onclick = () => { let node = field.parentElement; while (node) { if (node.tagName === 'DETAILS') node.open = true; if (node.tagName === 'DIALOG' && !node.open) node.showModal(); node = node.parentElement; } field.focus(); };
        item.replaceChildren(link);
    });
    summary?.focus();
    main.querySelectorAll('table').forEach(table => { if (table.closest('[wire\\:id]') || table.parentElement.classList.contains('table-scroll')) return; const wrapper = document.createElement('div'); wrapper.className = 'table-scroll'; wrapper.tabIndex = 0; wrapper.setAttribute('role','region'); wrapper.setAttribute('aria-label','Scrollable data table'); table.before(wrapper); wrapper.append(table); });
    const dialog = document.createElement('dialog'); dialog.className = 'workspace-dialog'; dialog.setAttribute('aria-labelledby','confirmation-title');
    dialog.innerHTML = '<h2 id="confirmation-title">Confirm access change</h2><p id="confirmation-description"></p><label><input type="checkbox" id="confirm-ack"> I understand the impact of this change.</label><div class="dialog-actions"><button type="button" class="danger" id="confirm-apply" disabled>Confirm change</button><button type="button" id="confirm-cancel">Cancel</button></div>';
    document.body.append(dialog);
    let pending, opener;
    const ack = dialog.querySelector('#confirm-ack'); const apply = dialog.querySelector('#confirm-apply');
    ack.onchange = () => { apply.disabled = !ack.checked; };
    dialog.querySelector('#confirm-cancel').onclick = () => dialog.close();
    dialog.addEventListener('close', () => opener?.focus());
    apply.onclick = () => { if (!ack.checked || !pending) return; const {form,submitter} = pending; form.dataset.confirmed = '1'; dialog.close(); form.requestSubmit(submitter); };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || form.hasAttribute('data-autosave') || [...form.attributes].some(a => a.name.startsWith('wire:'))) return;
        const submitter = event.submitter;
        const action = submitter?.name === 'action' ? submitter.value : form.elements.namedItem('action')?.value;
        const accountCreate = action === 'create' && !!form.elements.namedItem('account_mode');
        if ((['suspend','revoke','remove'].includes(action) || accountCreate) && form.dataset.confirmed !== '1') {
            event.preventDefault(); pending = {form,submitter}; opener = submitter; ack.checked = false; apply.disabled = true;
            dialog.querySelector('#confirmation-description').textContent = action === 'suspend' ? 'Suspending this client blocks access and cancels pending work. Reactivation does not restore sensitive grants.' : 'Revoking this access removes the selected administrative privileges. The affected account may need to sign in again.';
            dialog.querySelector('#confirmation-title').textContent = accountCreate ? 'Review account operation' : 'Confirm access change';
            if (accountCreate) {
                const client = form.elements.namedItem('client');
                const clientName = client?.selectedOptions?.[0]?.textContent || 'the current client';
                dialog.querySelector('#confirmation-description').textContent = `${form.elements.account_mode.value === 'new' ? 'Create account setup' : 'Invite existing account'} for ${form.elements.email.value} in ${clientName}. Initial access is Member only and starts after acceptance. Review the delivery option before confirming.`;
            }
            dialog.showModal(); dialog.querySelector('#confirm-cancel').focus(); return;
        }
        delete form.dataset.confirmed;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        // Do not disable the submitter: its name/value is part of the server request.
        window.ActionLoading?.begin(form, submitter);
    });
    const appearance = document.getElementById('platform-appearance-form');
    if (appearance) {
        const preview = document.createElement('iframe'); preview.title = 'Selected platform theme preview'; preview.style.cssText = 'width:100%;height:320px;border:1px solid var(--line);border-radius:12px';
        preview.srcdoc = '<!doctype html><html><head><link rel="stylesheet" href="/app.css"></head><body style="padding:24px"><img alt="" style="max-width:100px;max-height:50px"><h2></h2><p></p><section class="panel"><h3>Workspace preview</h3><p>Readable content and clear actions.</p><button class="primary" type="button">Primary action</button></section></body></html>';
        appearance.elements.default_theme.closest('label').after(preview);
        const renderSelected = () => {
            const doc = preview.contentDocument; if (!doc?.querySelector('h2')) return;
            doc.documentElement.dataset.theme = appearance.elements.default_theme.value;
            const palettes = ClientPalette.generate(appearance.elements.accent.value);
            for (const [variant,tokens] of Object.entries(palettes)) for (const [key,value] of Object.entries(tokens)) doc.documentElement.style.setProperty('--platform-'+variant+'-'+key,value);
            doc.querySelector('h2').textContent = appearance.elements.display_name.value;
            doc.querySelector('body>p').textContent = appearance.elements.app_name.value;
            const source = appearance.querySelector('.platform-preview-logo'); if (source) doc.querySelector('img').src = source.src;
            doc.querySelector('img').hidden = !!appearance.elements.remove_logo?.checked;
        };
        preview.onload = renderSelected; appearance.addEventListener('input', renderSelected); appearance.addEventListener('change', () => requestAnimationFrame(renderSelected));
        let objectUrl;
        const logos = [...appearance.querySelectorAll('.platform-preview-logo')]; const originals = logos.map(img => img.src);
        const update = () => {
            appearance.querySelectorAll('[data-preview-display]').forEach(el => el.textContent = appearance.elements.display_name.value);
            appearance.querySelectorAll('[data-preview-app]').forEach(el => el.textContent = appearance.elements.app_name.value);
        };
        appearance.addEventListener('input', update); update();
        appearance.elements.logo.addEventListener('change', () => {
            const field = appearance.elements.logo; const file = field.files[0];
            field.setCustomValidity(file && (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 2097152) ? 'Choose PNG, JPEG or WebP up to 2 MB.' : '');
            if (!field.reportValidity()) return;
            if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = file ? URL.createObjectURL(file) : null;
            logos.forEach((img,index) => img.src = objectUrl || originals[index]);
        });
    }
})();
