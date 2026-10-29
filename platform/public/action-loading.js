/* Explicit action copy; no form values or private content are inserted into popups. */
(() => {
    const messages = {
    "Create person": "Creating person...",
    "Create internal team": "Creating internal team...",
    "Update assignment": "Updating assignment...",
    "End client membership": "Ending client membership...",
    "Prepare promotion": "Preparing promotion...",
    "Authorise history export": "Authorising history export...",
    "Create enrolment": "Creating enrolment...",
    "Assign independent review": "Assigning independent review...",
    "Grant sensitive access": "Granting sensitive access...",
    "Revoke sensitive access": "Revoking sensitive access...",
    "Save client branding": "Saving client branding...",
    "Queue foundation check": "Queuing foundation check...",
    "Store privately": "Storing privately...",
    "Save preference": "Saving preference...",
    "Create client": "Creating client...",
    "Save platform appearance": "Saving platform appearance...",
    "Record reconciliation": "Recording reconciliation...",
    "Publish assessment": "Publishing assessment...",
    "Assign assessment": "Assigning assessment...",
    "Assign alternate reviewer": "Assigning alternate reviewer...",
    "Save response": "Saving response...",
    "Upload evidence": "Uploading evidence...",
    "Create development plan": "Creating development plan...",
    "Request text extraction": "Requesting text extraction...",
    "Confirm file evidence": "Confirming file evidence...",
    "Approve reviewed result": "Approving reviewed result...",
    "Reject and allow resubmission": "Rejecting and allow resubmission...",
    "Uphold previous decision": "Upholding previous decision...",
    "Request synthetic provisional analysis": "Requesting synthetic provisional analysis...",
    "Request alternate review": "Requesting alternate review...",
    "Save work": "Saving work...",
    "Submit saved work for review": "Submitting saved work for review...",
    "Publish reviewed quiz": "Publishing reviewed quiz...",
    "Refresh evidence snapshot": "Refreshing evidence snapshot...",
    "Record evidence": "Recording evidence...",
    "Save future definition": "Saving future definition...",
    "Suggest actions with AI": "Requesting actions with AI...",
    "Propose adaptation with AI": "Proposing adaptation with AI...",
    "Approve this future version": "Approving this future version...",
    "Approve grade": "Approving grade...",
    "Request changes": "Requesting changes...",
    "Request provisional grading": "Requesting provisional grading...",
    "Record human decision": "Recording human decision...",
    "Request an appeal": "Requesting an appeal...",
    "Withdraw pending attempt": "Withdrawing pending attempt...",
    "Uphold existing decision": "Upholding existing decision...",
    "Activate programme": "Activating programme...",
    "Authorise and lock block": "Authorising and lock block...",
    "Pause programme": "Pausing programme...",
    "Confirm programme completion": "Confirming programme completion...",
    "Preview resume schedule": "Preparing resume schedule...",
    "Approve this schedule and resume": "Approving this schedule and resume...",
    "Save review draft": "Saving review draft...",
    "Confirm reviewed findings": "Confirming reviewed findings...",
    "Switch client": "Switching client...",
    "Save draft": "Saving draft...",
    "Confirm assessment findings": "Confirming assessment findings...",
    "Confirm shared calendar": "Confirming shared calendar...",
    "Queue curriculum generation": "Queuing curriculum generation...",
    "Save new draft version": "Saving new draft version...",
    "Validate all blocks and request review": "Validating all blocks and request review...",
    "Save directory name": "Saving directory name...",
    "Accept invitation": "Accepting invitation...",
    "Issue private link": "Issuing private link...",
    "Cancel link": "Cancelling link...",
    "Save password": "Saving password...",
    "Sign in": "Logging in...",
    "Sign out": "Signing out...",
    "Submit assessment": "Submitting assessment...",
    "Revoke Global Admin": "Revoking Global Admin access...",
    "Reactivate client": "Reactivating client...",
    "Suspend client": "Suspending client...",
    "Grant Global Admin": "Granting Global Admin access...",
    "Apply client access change": "Updating client access...",
    "Create revised draft": "Creating revised draft...",
    "Create refreshed draft": "Creating refreshed draft...",
    "Create curriculum draft": "Creating curriculum draft...",
    "Reconcile original key": "Reconciling request...",
    "Send prepared request": "Sending request...",
    "Retry extraction": "Retrying extraction...",
    "Request extraction": "Requesting extraction..."
};
    const locks = new Map();
    let visible;
    const host = document.createElement('dialog');
    host.className = 'loading-host';
    host.setAttribute('aria-label', 'Action progress');
    host.addEventListener('cancel', event => event.preventDefault());
    document.body.append(host);
    function message(form, button) {
        if (button?.dataset.loadingText) return button.dataset.loadingText;
        if (form.dataset.loadingText) return form.dataset.loadingText;
        const path = new URL(form.getAttribute('action') || location.href, location.href).pathname;
        if (path === '/login') return 'Logging in...';
        if (path === '/logout') return 'Signing out...';
        if (path === '/clients') return 'Switching client...';
        const action = button?.name === 'action' ? button.value : form.elements.namedItem('action')?.value;
        if (path === '/platform/actions') {
            const copy = {grant:'Granting Client Admin access...',revoke:'Revoking Client Admin access...',appoint:'Granting Global Admin access...',remove:'Revoking Global Admin access...',suspend:'Suspending client...',reactivate:'Reactivating client...',join:'Joining client...'};
            if (copy[action]) return copy[action];
        }
        if (path === '/accounts/actions') {
            if (action === 'create') return form.elements.namedItem('account_mode')?.value === 'existing' ? 'Preparing invitation...' : 'Creating account...';
            const copy = {reset:'Issuing recovery link...',setup:'Issuing setup link...',cancel:'Cancelling link...'};
            if (copy[action]) return copy[action];
        }
        if (form.querySelector('input[type=file]')?.files?.length) return 'Uploading ' + (path.includes('appearance') || path.includes('branding') ? 'logo...' : 'document...');
        const label = (button?.getAttribute('aria-label') || button?.textContent || '').trim().replace(/\s+/g,' ');
        if (label.startsWith('Approve exact version')) return 'Approving plan version...';
        if (label.startsWith('Save ') && label.endsWith(' route')) return 'Saving provider route...';
        return messages[label] || 'Processing...';
    }
    function hide(token) {
        if (visible !== token) return;
        clearTimeout(token.timer);
        visible = undefined;
        window.Swal?.close();
        if (host.open) host.close();
        if (token.opener?.isConnected) token.opener.focus();
    }
    function begin(form, button) {
        if (locks.has(form)) return null;
        const token = {form,button,opener:document.activeElement,busy:form.getAttribute('aria-busy'),disabled:button?.getAttribute('aria-disabled')};
        locks.set(form, token);
        form.setAttribute('aria-busy','true');
        button?.setAttribute('aria-disabled','true');
        if (!window.Swal) return token; // Ordinary server submission still works if assets fail.
        if (visible) hide(visible);
        visible = token;
        host.showModal();
        Swal.fire({target:host,titleText:message(form,button),text:'Please wait.',showConfirmButton:false,
            allowOutsideClick:false,allowEscapeKey:false,heightAuto:false,returnFocus:false,
            showClass:{popup:''},hideClass:{popup:''},
            didOpen:()=>{ if(visible === token && host.open) Swal.showLoading(); }
        }).then(()=>hide(token));
        token.timer = setTimeout(()=>{
            if (visible !== token) return;
            Swal.hideLoading();
            Swal.update({titleText:'This is taking longer than usual',text:'The request may still be processing. Hiding this popup does not cancel it. Please do not submit it again.',showConfirmButton:true,confirmButtonText:'Hide progress'});
        },30000);
        return token;
    }
    function end(token) {
        if (!token || locks.get(token.form) !== token) return;
        hide(token);
        locks.delete(token.form);
        token.busy === null ? token.form.removeAttribute('aria-busy') : token.form.setAttribute('aria-busy',token.busy);
        if (token.button) token.disabled === null ? token.button.removeAttribute('aria-disabled') : token.button.setAttribute('aria-disabled',token.disabled);
    }
    document.addEventListener('submit',event=>{
        if (locks.has(event.target)) { event.preventDefault(); event.stopImmediatePropagation(); }
    },true);
    addEventListener('pageshow',()=>{
        [...locks.values()].forEach(end);
        document.querySelectorAll('form[data-submitting]').forEach(form=>{
            delete form.dataset.submitting; form.removeAttribute('aria-busy');
        });
    });
    window.ActionLoading = {begin,end,message};
})();
