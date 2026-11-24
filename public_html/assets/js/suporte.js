(() => {
    const root = document.querySelector('[data-support]');
    if (!root) return;

    const api = root.dataset.api;
    const attachApi = root.dataset.attachApi;
    const csrf = root.dataset.csrf;
    const userName = root.dataset.userName || 'Cliente';
    const userPhoto = root.dataset.userPhoto || '';

    const state = {
        tab: 'list',
        items: [],
        total: 0,
        page: 1,
        selectedId: root.dataset.openTicket || '',
        meta: { categories: {}, services: [] },
        replyFiles: [],
        createFiles: [],
        sending: false,
        pollTimer: null,
        stickBottom: true,
        filters: { q: '', status: '', category: '' },
    };

    const els = {
        ticketLists: () => Array.from(root.querySelectorAll('[data-ticket-list]')),
        counts: () => Array.from(root.querySelectorAll('[data-ticket-count]')),
        messages: root.querySelector('[data-messages]'),
        chat: root.querySelector('[data-chat]'),
        chatEmpty: root.querySelector('[data-chat-empty]'),
        workspace: root.querySelector('[data-workspace]'),
        categorySelect: root.querySelector('[data-category-select]'),
        serviceSelect: root.querySelector('[data-service-select]'),
        filterCategory: root.querySelector('[data-filter-category]'),
        createError: root.querySelector('[data-create-error]'),
        replyError: root.querySelector('[data-reply-error]'),
        closedNote: root.querySelector('[data-closed-note]'),
        resolveBtn: root.querySelector('[data-resolve]'),
        reopenBtn: root.querySelector('[data-reopen]'),
        replyBody: root.querySelector('[data-reply-body]'),
        replySubmit: root.querySelector('[data-reply-submit]'),
        createSubmit: root.querySelector('[data-create-submit]'),
        createFiles: root.querySelector('[data-create-files]'),
        replyFiles: root.querySelector('[data-reply-files]'),
        pagination: root.querySelector('[data-pagination]'),
    };

    const toast = (msg, type = 'ok') => {
        if (typeof window.ghToast === 'function') window.ghToast(msg, type);
    };

    const icons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const fmtDate = (iso) => {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const relative = (iso) => {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const diff = Date.now() - d.getTime();
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'agora';
        if (mins < 60) return `há ${mins} min`;
        const hours = Math.floor(mins / 60);
        if (hours < 24) return `há ${hours} h`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `há ${days} dia${days > 1 ? 's' : ''}`;
        return fmtDate(iso);
    };

    const statusClass = (status) => ({
        open: 'is-open',
        in_progress: 'is-progress',
        awaiting_client: 'is-wait',
        resolved: 'is-resolved',
        closed: 'is-closed',
    }[status] || 'is-open');

    const setTab = (tab) => {
        state.tab = tab;
        root.querySelectorAll('[data-tab]').forEach((btn) => {
            const active = btn.dataset.tab === tab;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('[data-panel-tab]').forEach((panel) => {
            const active = panel.dataset.panelTab === tab;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
    };

    const renderFileList = (target, files, kind) => {
        if (!target) return;
        if (!files.length) {
            target.innerHTML = '';
            return;
        }
        target.innerHTML = files.map((f, i) => `
            <div class="support-file-chip">
                <span>${esc(f.name)} · ${(f.size / 1024).toFixed(0)} KB</span>
                <button type="button" data-remove-file="${kind}:${i}" aria-label="Remover">×</button>
            </div>
        `).join('');
    };

    const renderEmptyList = () => {
        const hasFilters = state.filters.q || state.filters.status || state.filters.category;
        return `
            <div class="support-empty">
                <strong>${hasFilters ? 'Nenhum resultado encontrado' : 'Você ainda não abriu nenhum chamado'}</strong>
                <p>${hasFilters ? 'Ajuste a busca ou os filtros e tente novamente.' : 'Nossa equipe está pronta para ajudar!'}</p>
                ${hasFilters ? '' : '<button type="button" class="gh-btn gh-btn-primary" data-open-create>Abrir meu primeiro ticket</button>'}
            </div>
        `;
    };

    const renderList = () => {
        const html = state.items.length === 0
            ? renderEmptyList()
            : `<table class="support-table"><thead><tr><th>Departamento</th><th>Assunto</th><th>Status</th><th>Última atualização</th></tr></thead><tbody>${
                state.items.map((t) => `
                <tr class="${t.id === state.selectedId ? 'is-active' : ''}" data-select-ticket="${esc(t.id)}" tabindex="0">
                    <td>${esc(t.categoryLabel)}</td>
                    <td><strong>${esc(t.numberLabel)}</strong><div>${esc(t.subject || 'Sem assunto')}</div></td>
                    <td><span class="support-status-badge ${statusClass(t.status)}">${esc(t.statusLabel)}</span></td>
                    <td>${esc(fmtDate(t.updatedAt))}</td>
                </tr>`).join('')
            }</tbody></table>`;

        els.ticketLists().forEach((list) => { list.innerHTML = html; });
        els.counts().forEach((c) => { c.textContent = String(state.total); });

        if (els.pagination) {
            const pages = Math.max(1, Math.ceil(state.total / 20));
            if (pages <= 1) {
                els.pagination.hidden = true;
            } else {
                els.pagination.hidden = false;
                els.pagination.innerHTML = `
                    <button type="button" class="gh-btn gh-btn-secondary" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>Anterior</button>
                    <span>Página ${state.page} de ${pages}</span>
                    <button type="button" class="gh-btn gh-btn-secondary" data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''}>Próxima</button>
                `;
            }
        }
        icons();
    };

    const avatarHtml = (msg) => {
        if (msg.role === 'staff') {
            return `<span class="support-avatar is-staff" aria-hidden="true">GH</span>`;
        }
        if (userPhoto && msg.role === 'client') {
            return `<img class="support-avatar" src="${esc(userPhoto)}" alt="">`;
        }
        return `<span class="support-avatar" aria-hidden="true">${esc((msg.authorName || userName).slice(0, 1).toUpperCase())}</span>`;
    };

    const renderMessages = (messages) => {
        if (!els.messages) return;
        const nearBottom = els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
        els.messages.innerHTML = (messages || []).map((m) => `
            <article class="support-msg ${m.role === 'staff' ? 'is-staff' : 'is-client'} ${m.internal ? 'is-internal' : ''}">
                ${avatarHtml(m)}
                <div class="support-msg-bubble">
                    <header>
                        <strong>${esc(m.authorName)}</strong>
                        <span>${esc(fmtDate(m.createdAt))}</span>
                    </header>
                    <p>${esc(m.body).replace(/\n/g, '<br>')}</p>
                    ${(m.attachments || []).map((a) => `
                        <a class="support-msg-attach" href="${esc(attachApi)}?id=${encodeURIComponent(a.id)}" target="_blank" rel="noopener">
                            <i data-lucide="paperclip"></i> ${esc(a.name)}
                        </a>
                    `).join('')}
                </div>
            </article>
        `).join('');
        icons();
        if (state.stickBottom || nearBottom) {
            els.messages.scrollTop = els.messages.scrollHeight;
            state.stickBottom = true;
        }
    };

    const showChat = (ticket) => {
        if (!ticket) {
            els.chat.hidden = true;
            els.chatEmpty.hidden = false;
            root.classList.remove('is-chat-open');
            return;
        }
        els.chat.hidden = false;
        els.chatEmpty.hidden = true;
        root.classList.add('is-chat-open');
        root.querySelector('[data-chat-number]').textContent = ticket.numberLabel;
        root.querySelector('[data-chat-subject]').textContent = ticket.subject;
        root.querySelector('[data-chat-category]').textContent = ticket.categoryLabel;
        root.querySelector('[data-chat-created]').textContent = fmtDate(ticket.createdAt);
        root.querySelector('[data-chat-status]').textContent = ticket.statusLabel;
        const wrap = root.querySelector('[data-chat-status-wrap]');
        if (wrap) wrap.className = `support-status-dot ${statusClass(ticket.status)}`;

        const closed = ticket.status === 'closed';
        const resolved = ticket.status === 'resolved';
        els.closedNote.hidden = !(closed || resolved);
        els.replyBody.disabled = closed;
        els.replySubmit.disabled = closed;
        els.resolveBtn.hidden = closed || resolved;
        els.reopenBtn.hidden = !(resolved || closed);
        renderMessages(ticket.messages || []);
    };

    const apiGet = async (action, params = {}) => {
        const url = new URL(api, window.location.origin);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v != null) url.searchParams.set(k, String(v));
        });
        const res = await window.ghApi(url.toString());
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Falha na requisição.');
        return data;
    };

    const apiPostForm = async (fields, files = []) => {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        fd.append('csrfToken', csrf);
        files.forEach((f) => fd.append('attachments[]', f));
        const res = await window.ghApi(api, { method: 'POST', body: fd });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Falha na requisição.');
        return data;
    };

    const loadMeta = async () => {
        const data = await apiGet('meta');
        state.meta = data;
        const cats = data.categories || {};
        const fill = (select, includeEmpty) => {
            if (!select) return;
            const current = select.value;
            const defaults = [
                ['technical', 'Suporte Técnico e Dúvidas'],
                ['billing', 'Assuntos Financeiros'],
                ['relationship', 'Solução e Relacionamento'],
            ];
            const entries = Object.keys(cats).length ? Object.entries(cats) : defaults;
            select.innerHTML = includeEmpty
                ? '<option value="">Todas as categorias</option>'
                : '';
            entries.forEach(([k, label]) => {
                if (!includeEmpty && !['technical', 'billing', 'relationship'].includes(k)) return;
                select.insertAdjacentHTML('beforeend', `<option value="${esc(k)}">${esc(label)}</option>`);
            });
            if (!includeEmpty && !select.value) select.value = 'technical';
            else select.value = current;
        };
        fill(els.categorySelect, false);
        fill(els.filterCategory, true);
        if (els.serviceSelect) {
            els.serviceSelect.innerHTML = '<option value="">Nenhum</option>';
            (data.services || []).forEach((s) => {
                els.serviceSelect.insertAdjacentHTML('beforeend', `<option value="${esc(s.id)}">${esc(s.label)}</option>`);
            });
        }
    };

    const loadList = async () => {
        const data = await apiGet('list', {
            q: state.filters.q,
            status: state.filters.status,
            category: state.filters.category,
            page: state.page,
            perPage: 20,
        });
        state.items = data.items || [];
        state.total = data.total || 0;
        renderList();
        if (state.selectedId) {
            const still = state.items.find((t) => t.id === state.selectedId);
            if (!still && state.items[0]) {
                // keep selected even if page filter hides it
            }
        }
    };

    const openTicket = async (id) => {
        state.selectedId = id;
        renderList();
        try {
            const data = await apiGet('get', { id });
            showChat(data.ticket);
            startPoll();
        } catch (err) {
            toast(err.message || 'Não foi possível abrir o ticket.', 'danger');
        }
    };

    const startPoll = () => {
        clearInterval(state.pollTimer);
        if (!state.selectedId) return;
        state.pollTimer = setInterval(async () => {
            if (document.hidden || !state.selectedId) return;
            try {
                const data = await apiGet('poll', { id: state.selectedId });
                const prevLen = els.messages?.querySelectorAll('.support-msg').length || 0;
                renderMessages(data.messages || []);
                root.querySelector('[data-chat-status]').textContent = data.statusLabel;
                if ((data.messages || []).length > prevLen) {
                    toast('Nova resposta recebida.');
                }
                await loadList();
            } catch (_) {
                // keep polling
            }
        }, 8000);
    };

    // Events
    root.querySelectorAll('[data-tab]').forEach((btn) => {
        btn.addEventListener('click', () => setTab(btn.dataset.tab));
    });
    root.querySelectorAll('[data-open-create]').forEach((btn) => {
        btn.addEventListener('click', () => setTab('create'));
    });
    root.addEventListener('click', (e) => {
        const openCreate = e.target.closest('[data-open-create]');
        if (openCreate) setTab('create');
        const select = e.target.closest('[data-select-ticket]');
        if (select) openTicket(select.dataset.selectTicket);
        const pageBtn = e.target.closest('[data-page]');
        if (pageBtn && !pageBtn.disabled) {
            state.page = Number(pageBtn.dataset.page) || 1;
            loadList();
        }
        const remove = e.target.closest('[data-remove-file]');
        if (remove) {
            const [kind, idx] = remove.dataset.removeFile.split(':');
            if (kind === 'create') {
                state.createFiles.splice(Number(idx), 1);
                renderFileList(els.createFiles, state.createFiles, 'create');
            } else {
                state.replyFiles.splice(Number(idx), 1);
                renderFileList(els.replyFiles, state.replyFiles, 'reply');
            }
        }
    });

    root.querySelector('[data-back-list]')?.addEventListener('click', () => {
        root.classList.remove('is-chat-open');
    });

    root.querySelector('[data-filter-q]')?.addEventListener('input', (e) => {
        clearTimeout(state._qTimer);
        state._qTimer = setTimeout(() => {
            state.filters.q = e.target.value.trim();
            state.page = 1;
            loadList();
        }, 280);
    });
    root.querySelector('[data-filter-status]')?.addEventListener('change', (e) => {
        state.filters.status = e.target.value;
        state.page = 1;
        loadList();
    });
    els.filterCategory?.addEventListener('change', (e) => {
        state.filters.category = e.target.value;
        state.page = 1;
        loadList();
    });

    els.messages?.addEventListener('scroll', () => {
        const nearBottom = els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
        state.stickBottom = nearBottom;
    });

    root.querySelector('[data-create-attach]')?.addEventListener('change', (e) => {
        const incoming = Array.from(e.target.files || []);
        state.createFiles = [...state.createFiles, ...incoming].slice(0, 5);
        renderFileList(els.createFiles, state.createFiles, 'create');
        e.target.value = '';
    });
    root.querySelector('[data-reply-attach]')?.addEventListener('change', (e) => {
        const incoming = Array.from(e.target.files || []);
        state.replyFiles = [...state.replyFiles, ...incoming].slice(0, 5);
        renderFileList(els.replyFiles, state.replyFiles, 'reply');
        e.target.value = '';
    });

    root.querySelector('[data-create-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (state.sending) return;
        const form = e.currentTarget;
        els.createError.hidden = true;
        state.sending = true;
        els.createSubmit.disabled = true;
        try {
            const data = await apiPostForm({
                action: 'create',
                subject: form.subject.value.trim(),
                category: form.category.value,
                priority: form.priority.value,
                serviceId: form.serviceId.value,
                body: form.body.value.trim(),
            }, state.createFiles);
            form.reset();
            state.createFiles = [];
            renderFileList(els.createFiles, [], 'create');
            toast(`Ticket ${data.ticket.numberLabel} aberto com sucesso.`);
            setTab('list');
            state.page = 1;
            await loadList();
            await openTicket(data.ticket.id);
        } catch (err) {
            els.createError.hidden = false;
            els.createError.textContent = err.message || 'Falha ao abrir ticket.';
            toast(err.message || 'Falha ao abrir ticket.', 'danger');
        } finally {
            state.sending = false;
            els.createSubmit.disabled = false;
        }
    });

    root.querySelector('[data-reply-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (state.sending || !state.selectedId) return;
        els.replyError.hidden = true;
        state.sending = true;
        els.replySubmit.disabled = true;
        state.stickBottom = true;
        try {
            const data = await apiPostForm({
                action: 'reply',
                id: state.selectedId,
                body: els.replyBody.value.trim(),
            }, state.replyFiles);
            els.replyBody.value = '';
            state.replyFiles = [];
            renderFileList(els.replyFiles, [], 'reply');
            showChat(data.ticket);
            await loadList();
            toast('Resposta enviada.');
        } catch (err) {
            els.replyError.hidden = false;
            els.replyError.textContent = err.message || 'Falha ao enviar.';
            toast(err.message || 'Falha ao enviar.', 'danger');
        } finally {
            state.sending = false;
            els.replySubmit.disabled = false;
        }
    });

    els.resolveBtn?.addEventListener('click', async () => {
        if (!state.selectedId || !window.confirm('Marcar este ticket como resolvido?')) return;
        try {
            const data = await apiPostForm({ action: 'status', id: state.selectedId, status: 'resolved' });
            showChat(data.ticket);
            await loadList();
            toast('Ticket marcado como resolvido.');
        } catch (err) {
            toast(err.message || 'Falha ao atualizar status.', 'danger');
        }
    });

    els.reopenBtn?.addEventListener('click', async () => {
        if (!state.selectedId || !window.confirm('Solicitar reabertura deste ticket?')) return;
        try {
            const data = await apiPostForm({ action: 'status', id: state.selectedId, status: 'open' });
            showChat(data.ticket);
            await loadList();
            toast('Ticket reaberto.');
        } catch (err) {
            toast(err.message || 'Falha ao reabrir.', 'danger');
        }
    });

    (async () => {
        try {
            await loadMeta();
            await loadList();
            if (state.selectedId) await openTicket(state.selectedId);
            icons();
        } catch (err) {
            toast(err.message || 'Erro ao carregar suporte.', 'danger');
        }
    })();
})();
