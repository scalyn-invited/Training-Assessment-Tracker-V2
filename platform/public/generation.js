(() => {
    const panel = document.querySelector('[data-generation-url]');
    if (!panel || panel.dataset.active !== 'true') return;
    const status = document.getElementById('generation-status');
    async function poll() {
        try {
            const response = await fetch(panel.dataset.generationUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) {
                status.textContent = 'Progress is unavailable. Refresh to check your session and access.';
                return;
            }
            const result = await response.json();
            status.textContent = result.message;
            document.getElementById('generation-progress').textContent = `${result.complete} of ${result.total} blocks complete · ${result.status}`;
            document.getElementById('generation-cost').textContent = `Reserved: ${result.reserved} micro-USD · Accounted usage: ${result.spent} micro-USD`;
            const attempts = document.getElementById('generation-attempts');
            attempts.replaceChildren(...result.attempts.map(attempt => {
                const item = document.createElement('li');
                item.textContent = `${attempt.provider} · ${attempt.model} · ${attempt.status} · ${attempt.input_tokens ?? 'unknown'} input / ${attempt.output_tokens ?? 'unknown'} output tokens · Request ${attempt.external_id ?? 'not supplied'}`;
                return item;
            }));
            if (result.result_url) {
                const link = document.getElementById('generation-result');
                link.href = result.result_url;
                link.hidden = false;
            }
            if (['queued', 'running'].includes(result.status)) setTimeout(poll, 2500);
        } catch {
            status.textContent = 'Connection interrupted. Your queued request is retained; reconnect and refresh progress.';
        }
    }
    setTimeout(poll, 1500);
})();
