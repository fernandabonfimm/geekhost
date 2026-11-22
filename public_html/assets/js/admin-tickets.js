(() => {
    const root = document.querySelector('[data-admin-tickets]');
    if (!root) return;

    const api = root.dataset.api;
    const attachApi = root.dataset.attachApi;
    const csrf = root.dataset.csrf;
    const state = {
        items: [],
        total: 0,
        selectedId: '',
        replyFiles: [],
        sending: false,
        stickBottom: true,
        filters: { q: '', status: '', category: '', priority: '' },
        categories: {},
        pollTimer: null,
    };

    const toast = (msg, type = 'ok') => {
        if (typeof window.ghToast === 'function') window.ghToast(msg, type);
    };
    const icons = () => window.lucide?.createIcons?.();
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const fmtDate = (iso) => {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };
    const statusClass = (status) => ({
        open: 'is-open', in_progress: 'is-progress', awaiting_client: 'is-wait', resolved: 'is-resolved', closed: 'is-closed',
    }[status] || 'is-open');

    const listEl = root.querySelector('[data-ticket-list]');
    const messagesEl = root.querySelector('[data-messages]');
    const chatEl = root.querySelector('[data-chat]');
    const emptyEl = root.querySelector('[data-chat-empty]');

    const apiGet = async (action, params = {}) => {
        const url = new URL(api, window.location.origin);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([k, v]) => { if (v) url.searchParams.set(k, String(v)); });
        const res = await window.ghApi(url.toString());
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Falha na requisição.');
        return data;
    };

    const apiPost = async (fields, files = []) => {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        fd.append('csrfToken', csrf);
        files.forEach((f) => fd.append('attachments[]', f));
        const res = await window.ghApi(api, { method: 'POST', body: fd });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Falha na requisição.');
        return data;
    };

    const renderKpis = (counts = {}) => {
        root.querySelectorAll('[data-kpi]').forEach((el) => {
            el.textContent = String(counts[el.dataset.kpi] ?? 0);
        });
    };

    const renderList = () => {
        root.querySelector('[data-ticket-count]').textContent = String(state.total);
        listEl.innerHTML = state.items.length === 0
            ? '<div class="support-empty"><strong>Nenhum ticket na fila</strong></div>'
            : state.items.map((t) => `
                <button type="button" class="support-ticket-item ${t.id === state.selectedId ? 'is-active' : ''} ${statusClass(t.status)}" data-select="${esc(t.id)}">
                    <div class="support-ticket-item-top">
                        <span class="support-id-badge">${esc(t.numberLabel)}</span>
                        <span class="support-status-badge ${statusClass(t.status)}">${esc(t.statusLabel)}</span>
                    </div>
                    <strong>${esc(t.subject)}</strong>
                    <p>${esc(t.userEmail || '')} · ${esc(t.priorityLabel)} · ${esc(t.categoryLabel)}</p>
                    ${t.unread > 0 ? `<span class="support-unread">${t.unread}</span>` : ''}
                </button>
            `).join('');
        icons();
    };

    const renderMessages = (messages) => {
        const nearBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 80;
        messagesEl.innerHTML = (messages || []).map((m) => `
            <article class="support-msg ${m.role === 'staff' ? 'is-staff' : 'is-client'} ${m.internal ? 'is-internal' : ''}">
                <span class="support-avatar ${m.role === 'staff' ? 'is-staff' : ''}" aria-hidden="true">${m.role === 'staff' ? 'GH' : 'CL'}</span>
                <div class="support-msg-bubble">
                    <header>
                        <strong>${esc(m.authorName)}${m.internal ? ' · interno' : ''}</strong>
                        <span>${esc(fmtDate(m.createdAt))}</span>
                    </header>
                    <p>${esc(m.body).replace(/\n/g, '<br>')}</p>
                    ${(m.attachments || []).map((a) => `
                        <a class="support-msg-attach" href="${esc(attachApi)}?id=${encodeURIComponent(a.id)}" target="_blank" rel="noopener">${esc(a.name)}</a>
                    `).join('')}
                </div>
            </article>
        `).join('');
        if (state.stickBottom || nearBottom) messagesEl.scrollTop = messagesEl.scrollHeight;
        icons();
    };

    const showTicket = (ticket) => {
        chatEl.hidden = false;
        emptyEl.hidden = true;
        root.classList.add('is-chat-open');
        root.querySelector('[data-chat-number]').textContent = ticket.numberLabel;
        root.querySelector('[data-chat-subject]').textContent = ticket.subject;
        root.querySelector('[data-chat-client]').textContent = ticket.userName || ticket.userEmail || 'Cliente';
        root.querySelector('[data-chat-category]').textContent = ticket.categoryLabel;
        root.querySelector('[data-chat-priority]').textContent = ticket.priorityLabel;
        root.querySelector('[data-chat-status]').textContent = ticket.statusLabel;
        root.querySelector('[data-set-status]').value = ticket.status;
        renderMessages(ticket.messages);
    };

    const loadList = async () => {
        const data = await apiGet('list', state.filters);
        state.items = data.items || [];
        state.total = data.total || 0;
        renderKpis(data.counts || {});
        renderList();
    };

    const openTicket = async (id) => {
        state.selectedId = id;
        renderList();
        const data = await apiGet('get', { id });
        showTicket(data.ticket);
        clearInterval(state.pollTimer);
        state.pollTimer = setInterval(async () => {
            if (document.hidden || !state.selectedId) return;
            try {
                const polled = await apiGet('poll', { id: state.selectedId });
                showTicket(polled.ticket);
                await loadList();
            } catch (_) {}
        }, 8000);
    };

    // categories from first load labels hardcoded fallback
    const catSelect = root.querySelector('[data-filter-category]');
    const defaultCats = {
        hosting: 'Hospedagem', domains: 'Domínios e DNS', wordpress: 'WordPress', email: 'E-mail profissional',
        ssl: 'SSL', billing: 'Faturamento e pagamentos', migration: 'Migração de site', technical: 'Problemas técnicos', other: 'Outros',
    };
    Object.entries(defaultCats).forEach(([k, label]) => {
        catSelect.insertAdjacentHTML('beforeend', `<option value="${k}">${label}</option>`);
    });

    root.addEventListener('click', (e) => {
        const item = e.target.closest('[data-select]');
        if (item) openTicket(item.dataset.select).catch((err) => toast(err.message, 'danger'));
        const remove = e.target.closest('[data-remove-file]');
        if (remove) {
            state.replyFiles.splice(Number(remove.dataset.removeFile), 1);
            root.querySelector('[data-reply-files]').innerHTML = state.replyFiles.map((f, i) => `
                <div class="support-file-chip"><span>${esc(f.name)}</span><button type="button" data-remove-file="${i}">×</button></div>
            `).join('');
        }
    });
    root.querySelector('[data-back-list]')?.addEventListener('click', () => root.classList.remove('is-chat-open'));
    root.querySelector('[data-filter-q]')?.addEventListener('input', (e) => {
        clearTimeout(state._t);
        state._t = setTimeout(() => { state.filters.q = e.target.value.trim(); loadList().catch(() => {}); }, 280);
    });
    ['status', 'priority', 'category'].forEach((key) => {
        root.querySelector(`[data-filter-${key}]`)?.addEventListener('change', (e) => {
            state.filters[key] = e.target.value;
            loadList().catch(() => {});
        });
    });
    messagesEl?.addEventListener('scroll', () => {
        state.stickBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 80;
    });
    root.querySelector('[data-reply-attach]')?.addEventListener('change', (e) => {
        state.replyFiles = [...state.replyFiles, ...Array.from(e.target.files || [])].slice(0, 5);
        root.querySelector('[data-reply-files]').innerHTML = state.replyFiles.map((f, i) => `
            <div class="support-file-chip"><span>${esc(f.name)}</span><button type="button" data-remove-file="${i}">×</button></div>
        `).join('');
        e.target.value = '';
    });
    root.querySelector('[data-reply-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (state.sending || !state.selectedId) return;
        state.sending = true;
        try {
            const data = await apiPost({
                action: 'reply',
                id: state.selectedId,
                body: root.querySelector('[data-reply-body]').value.trim(),
                internal: root.querySelector('[data-internal]').checked ? '1' : '',
            }, state.replyFiles);
            root.querySelector('[data-reply-body]').value = '';
            root.querySelector('[data-internal]').checked = false;
            state.replyFiles = [];
            root.querySelector('[data-reply-files]').innerHTML = '';
            showTicket(data.ticket);
            await loadList();
            toast('Mensagem enviada.');
        } catch (err) {
            toast(err.message || 'Falha ao enviar.', 'danger');
        } finally {
            state.sending = false;
        }
    });
    root.querySelector('[data-set-status]')?.addEventListener('change', async (e) => {
        if (!state.selectedId) return;
        try {
            const data = await apiPost({ action: 'status', id: state.selectedId, status: e.target.value });
            showTicket(data.ticket);
            await loadList();
            toast('Status atualizado.');
        } catch (err) {
            toast(err.message || 'Falha ao alterar status.', 'danger');
        }
    });
    root.querySelector('[data-assign-me]')?.addEventListener('click', async () => {
        if (!state.selectedId) return;
        try {
            const data = await apiPost({ action: 'assign', id: state.selectedId });
            showTicket(data.ticket);
            toast('Ticket atribuído a você.');
        } catch (err) {
            toast(err.message || 'Falha ao atribuir.', 'danger');
        }
    });

    loadList().catch((err) => toast(err.message || 'Erro ao carregar fila.', 'danger'));
})();
