(() => {
    const gh = window.GH || { base: '', apiToken: '' };

    window.ghApi = (input, options = {}) => {
        const opts = { credentials: 'same-origin', ...options };
        const headers = new Headers(opts.headers || {});
        if (gh.apiToken && !headers.has('Authorization')) {
            headers.set('Authorization', 'Bearer ' + gh.apiToken);
        }
        opts.headers = headers;
        let url = input;
        if (typeof input === 'string' && input.startsWith('/api/') && gh.base) {
            url = gh.base + input;
        }
        return fetch(url, opts);
    };

    document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element ? event.target.closest('a.support-msg-attach') : null;
        if (!link || !gh.apiToken) return;
        event.preventDefault();
        try {
            const res = await window.ghApi(link.href);
            if (!res.ok) throw new Error('Não foi possível baixar o anexo.');
            const blob = await res.blob();
            const objectUrl = URL.createObjectURL(blob);
            const save = document.createElement('a');
            save.href = objectUrl;
            save.download = (link.textContent || 'anexo').trim() || 'anexo';
            document.body.appendChild(save);
            save.click();
            save.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1500);
        } catch (err) {
            window.alert(err && err.message ? err.message : 'Falha no download.');
        }
    });
})();
