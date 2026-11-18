(() => {
    const body = document.body;
    if (!body || !body.hasAttribute('data-panel')) return;

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
            ${invoice.invoiceUrl ? `<section class="invoice-section"><a class="button button-lime" href="${invoice.invoiceUrl}" target="_blank" rel="noopener noreferrer">Abrir fatura Asaas</a></section>` : ''}
            ${invoice.boleto?.url ? `<section class="invoice-section"><a class="button button-outline-dark" href="${invoice.boleto.url}" target="_blank" rel="noopener noreferrer">Abrir boleto</a></section>` : ''}
            ${invoice.pix?.copyPaste ? `<section class="invoice-section"><h3>Pix copia e cola</h3><textarea readonly rows="4" style="width:100%;border:1px solid #dfe2e5;padding:12px">${invoice.pix.copyPaste}</textarea></section>` : ''}
        `;
        drawer.hidden = false;
        document.body.style.overflow = 'hidden';
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
