(() => {
    const body = document.body;
    if (!body || !body.hasAttribute('data-panel')) return;

    const refreshIcons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshIcons);
    } else {
        refreshIcons();
    }
    window.addEventListener('load', refreshIcons);

    const toastHost = document.querySelector('[data-toast-host]');
    const toastIcons = {
        ok: 'check-circle-2',
        warn: 'circle-alert',
        danger: 'triangle-alert',
    };
    const toast = (message, type = 'ok', options = {}) => {
        if (!toastHost) return;
        const tone = ['ok', 'warn', 'danger'].includes(type) ? type : 'ok';
        const el = document.createElement('div');
        el.className = `gh-toast is-${tone}${options.href ? ' is-clickable' : ''}`;
        el.setAttribute('role', 'status');
        const title = options.title ? `<strong>${options.title}</strong>` : '';
        el.innerHTML = `<i data-lucide="${toastIcons[tone]}" aria-hidden="true"></i><div>${title}<span>${message}</span></div>`;
        if (options.href) {
            el.addEventListener('click', () => { window.location.href = options.href; });
        }
        toastHost.appendChild(el);
        refreshIcons();
        setTimeout(() => el.remove(), options.duration || 5200);
    };
    window.ghToast = toast;

    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-close]');
    const storageKey = 'gh_panel_sidebar_collapsed';

    const setCollapsed = (collapsed) => {
        body.classList.toggle('is-collapsed', collapsed);
        const toggle = document.querySelector('[data-sidebar-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (_) { /* ignore */ }
    };

    const openMobile = () => {
        body.classList.add('is-sidebar-open');
        if (backdrop) backdrop.hidden = false;
    };
    const closeMobile = () => {
        body.classList.remove('is-sidebar-open');
        if (backdrop) backdrop.hidden = true;
    };

    if (sidebar) {
        try {
            if (localStorage.getItem(storageKey) === '1' && window.matchMedia('(min-width: 861px)').matches) {
                setCollapsed(true);
            }
        } catch (_) { /* ignore */ }

        document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
            setCollapsed(!body.classList.contains('is-collapsed'));
        });
        document.querySelector('[data-sidebar-open]')?.addEventListener('click', openMobile);
        backdrop?.addEventListener('click', closeMobile);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMobile();
        });
    }

    const account = document.querySelector('[data-account-menu]');
    if (account) {
        const btn = account.querySelector('[data-account-toggle]');
        const menu = account.querySelector('[data-account-dropdown]');
        btn?.addEventListener('click', () => {
            const open = menu?.hidden === false;
            if (menu) menu.hidden = open;
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
        document.addEventListener('click', (e) => {
            if (!account.contains(e.target) && menu) {
                menu.hidden = true;
                btn?.setAttribute('aria-expanded', 'false');
            }
        });
    }

    const searchForm = document.querySelector('[data-panel-search]');
    if (searchForm) {
        const input = searchForm.querySelector('input');
        const results = searchForm.querySelector('[data-search-results]');
        const links = Array.from(document.querySelectorAll('.panel-sidebar-nav a')).map((a) => ({
            href: a.getAttribute('href'),
            label: a.getAttribute('data-nav-label') || a.textContent.trim(),
        }));
        const render = (q) => {
            if (!results) return;
            const query = q.trim().toLowerCase();
            if (!query) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            const hits = links.filter((l) => l.label.toLowerCase().includes(query));
            results.innerHTML = hits.length
                ? hits.map((h) => `<a href="${h.href}">${h.label}</a>`).join('')
                : '<a href="#">Nenhum resultado</a>';
            results.hidden = false;
            refreshIcons();
        };
        input?.addEventListener('input', () => render(input.value));
        input?.addEventListener('focus', () => render(input.value));
        document.addEventListener('click', (e) => {
            if (!searchForm.contains(e.target) && results) results.hidden = true;
        });
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const first = results?.querySelector('a[href]:not([href="#"])');
            if (first) window.location.href = first.getAttribute('href');
        });
    }

    const notifyBtn = document.querySelector('[data-notify-toggle]');
    const notifyMenu = document.querySelector('[data-notify-menu]');
    const notifyList = document.querySelector('[data-notify-list]');
    const notifyDot = document.querySelector('[data-notify-dot]');
    const csrfToken = body.dataset.csrf || document.querySelector('[data-csrf]')?.dataset?.csrf || '';

    const loadNotifications = async () => {
        if (!notifyList) return;
        try {
            const res = await window.ghApi('/api/portal/notifications');
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.error || 'Falha');
            if (notifyDot) notifyDot.hidden = !(data.unread > 0);
            if (!data.items?.length) {
                notifyList.innerHTML = '<div class="panel-notify-item"><span>Sem novas notificações no momento.</span></div>';
                return;
            }
            notifyList.innerHTML = data.items.map((n) => `
                <a class="panel-notify-item ${n.read ? '' : 'is-unread'}" href="${n.link || (n.ticketId ? `/portal/suporte?ticket=${encodeURIComponent(n.ticketId)}` : '/portal/')}">
                    <strong>${n.title}</strong>
                    <span>${n.body}</span>
                </a>
            `).join('');
        } catch (_) {
            notifyList.innerHTML = '<div class="panel-notify-item"><span>Não foi possível carregar notificações.</span></div>';
        }
    };

    if (notifyBtn && notifyMenu) {
        notifyBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const open = notifyMenu.hidden === false;
            notifyMenu.hidden = open;
            notifyBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (!open) await loadNotifications();
        });
        document.addEventListener('click', (e) => {
            if (!notifyMenu.contains(e.target) && e.target !== notifyBtn && !notifyBtn.contains(e.target)) {
                notifyMenu.hidden = true;
                notifyBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.querySelector('[data-notify-read-all]')?.addEventListener('click', async () => {
            try {
                await window.ghApi('/api/portal/notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrfToken }),
                });
                if (notifyDot) notifyDot.hidden = true;
                await loadNotifications();
            } catch (_) {
                toast('Não foi possível marcar como lidas.', 'danger');
            }
        });
        loadNotifications();
        setInterval(loadNotifications, 30000);
    }

    // Unpaid hosting reminder every 30 minutes
    if (body.dataset.unpaidHosting === '1') {
        const payUrl = body.dataset.payUrl || '/portal/faturas?status=em_aberto';
        const remindKey = 'gh_unpaid_toast_at';
        const remind = () => {
            toast('Há uma fatura em aberto. Toque para concluir o pagamento e liberar sua hospedagem.', 'warn', {
                title: 'Pagamento pendente',
                href: payUrl,
                duration: 9000,
            });
            try { localStorage.setItem(remindKey, String(Date.now())); } catch (_) { /* ignore */ }
        };
        let last = 0;
        try { last = Number(localStorage.getItem(remindKey) || 0); } catch (_) { last = 0; }
        if (!last || (Date.now() - last) >= 30 * 60 * 1000) {
            setTimeout(remind, 1200);
        }
        setInterval(() => {
            let stamp = 0;
            try { stamp = Number(localStorage.getItem(remindKey) || 0); } catch (_) { stamp = 0; }
            if (!stamp || (Date.now() - stamp) >= 30 * 60 * 1000) remind();
        }, 60 * 1000);
    }

    // Domains / sites gated actions
    const domainsPage = document.querySelector('[data-domains-page]');
    if (domainsPage) {
        const flash = domainsPage.dataset.flashMessage;
        if (flash) toast(flash, domainsPage.dataset.flashType || 'ok');
        document.querySelectorAll('[data-add-domain-btn]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                if (domainsPage.dataset.hasPaid !== '1') {
                    e.preventDefault();
                    toast('Conclua o pagamento do plano antes de cadastrar um domínio.', 'warn', {
                        title: 'Pagamento necessário',
                        href: '/portal/faturas?status=em_aberto',
                    });
                    return;
                }
                const modal = document.querySelector('[data-modal="add-domain"]');
                if (modal) modal.hidden = false;
            });
        });
    }

    const sitesPage = document.querySelector('[data-sites-page]');
    if (sitesPage) {
        document.querySelectorAll('[data-new-site-btn]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (sitesPage.dataset.hasDomain !== '1') {
                    toast('Cadastre um domínio e aguarde o DNS antes de criar um site.', 'warn', {
                        title: 'Domínio necessário',
                        href: '/portal/dominios',
                    });
                    return;
                }
                window.location.href = '/portal/dominios';
            });
        });
    }

    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const value = btn.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(value);
                toast(`Copiado: ${value}`);
            } catch (_) {
                toast('Não foi possível copiar.', 'danger');
            }
        });
    });

    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-modal-open');
            const modal = document.querySelector(`[data-modal="${id}"]`);
            if (modal) modal.hidden = false;
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach((el) => {
        el.addEventListener('click', () => {
            const modal = el.closest('[data-modal]');
            if (modal) modal.hidden = true;
        });
    });

    document.querySelectorAll('[data-profile-tabs]').forEach((root) => {
        const tabs = root.querySelectorAll('[data-profile-tab]');
        const panels = root.querySelectorAll('[data-profile-panel]');
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-profile-tab');
                tabs.forEach((t) => t.classList.toggle('is-active', t === tab));
                panels.forEach((p) => {
                    p.hidden = p.getAttribute('data-profile-panel') !== target;
                });
            });
        });
    });

    const domainFilter = document.querySelector('[data-domain-filter]');
    if (domainFilter) {
        const input = domainFilter.querySelector('input[type="search"]');
        const status = domainFilter.querySelector('select');
        const rows = Array.from(document.querySelectorAll('[data-domain-row]'));
        const apply = () => {
            const q = (input?.value || '').trim().toLowerCase();
            const st = status?.value || '';
            rows.forEach((row) => {
                const domain = (row.getAttribute('data-domain') || '').toLowerCase();
                const dns = row.getAttribute('data-dns') || '';
                const matchQ = !q || domain.includes(q);
                const matchS = !st || dns === st;
                row.hidden = !(matchQ && matchS);
            });
        };
        input?.addEventListener('input', apply);
        status?.addEventListener('change', apply);
    }

    const root = document.querySelector('[data-invoices]');
    const drawer = document.querySelector('[data-invoice-drawer]');
    if (!root || !drawer) return;

    const bodyEl = drawer.querySelector('[data-drawer-body]');
    const titleEl = drawer.querySelector('#invoice-drawer-title');

    const open = (invoice) => {
        if (titleEl) titleEl.textContent = `Fatura #${invoice.invoiceNumber ?? '—'}`;
        bodyEl.innerHTML = `
            <section class="invoice-section">
                <h3>Resumo</h3>
                <dl>
                    <div><dt>Status</dt><dd>${invoice.statusLabel || invoice.status || '—'}</dd></div>
                    <div><dt>Total</dt><dd>${invoice.amountFormatted || '—'}</dd></div>
                    <div><dt>Período</dt><dd>${invoice.periodLabel || invoice.billingPeriod || '—'}</dd></div>
                </dl>
            </section>
            <section class="invoice-section">
                <h3>O que foi comprado</h3>
                <dl>
                    <div><dt>Plano</dt><dd>${invoice.planName || '—'}</dd></div>
                    <div><dt>Domínio</dt><dd>${invoice.domain || '—'}</dd></div>
                </dl>
            </section>
            <section class="invoice-section">
                <h3>Pagamento</h3>
                <dl>
                    <div><dt>Forma</dt><dd>${invoice.billingTypeLabel || invoice.billingType || '—'}</dd></div>
                    <div><dt>Emissão</dt><dd>${invoice.createdAtLabel || invoice.createdAt || '—'}</dd></div>
                    <div><dt>Vencimento</dt><dd>${invoice.dueDateLabel || invoice.dueDate || '—'}</dd></div>
                    <div><dt>Pago em</dt><dd>${invoice.paidAtLabel || invoice.paidAt || '—'}</dd></div>
                </dl>
            </section>
            ${invoice.invoiceUrl ? `<section class="invoice-section"><a class="gh-btn gh-btn-primary" href="${invoice.invoiceUrl}" target="_blank" rel="noopener noreferrer">Abrir fatura</a></section>` : ''}
            ${invoice.boleto?.url ? `<section class="invoice-section"><a class="gh-btn gh-btn-secondary" href="${invoice.boleto.url}" target="_blank" rel="noopener noreferrer">Abrir boleto</a></section>` : ''}
            ${invoice.pix?.copyPaste ? `<section class="invoice-section"><h3>Pix copia e cola</h3><textarea readonly rows="4" style="width:100%;border:1px solid #e5e9ef;border-radius:10px;padding:12px">${invoice.pix.copyPaste}</textarea></section>` : ''}
        `;
        drawer.hidden = false;
        document.body.style.overflow = 'hidden';
        refreshIcons();
    };

    const close = () => {
        drawer.hidden = true;
        document.body.style.overflow = '';
    };

    root.querySelectorAll('[data-view-invoice]').forEach((btn) => {
        btn.addEventListener('click', () => {
            try {
                open(JSON.parse(btn.getAttribute('data-view-invoice') || '{}'));
            } catch (_) { /* ignore */ }
        });
    });
    drawer.querySelectorAll('[data-drawer-close]').forEach((el) => el.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
