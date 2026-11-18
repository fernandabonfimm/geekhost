(() => {
    const root = document.querySelector('[data-invoices]');
    const drawer = document.querySelector('[data-invoice-drawer]');
    if (!root || !drawer) return;

    const body = drawer.querySelector('[data-drawer-body]');
    const open = (invoice) => {
        body.innerHTML = `
            <dl>
                <div><dt>Fatura</dt><dd>#${invoice.invoiceNumber ?? '—'}</dd></div>
                <div><dt>Status</dt><dd>${invoice.statusLabel || invoice.status}</dd></div>
                <div><dt>Plano</dt><dd>${invoice.planName || ''}</dd></div>
                <div><dt>Período</dt><dd>${invoice.periodLabel || invoice.billingPeriod || ''}</dd></div>
                <div><dt>Domínio</dt><dd>${invoice.domain || '—'}</dd></div>
                <div><dt>Pagamento</dt><dd>${invoice.billingType || '—'}</dd></div>
                <div><dt>Total</dt><dd>${invoice.amountFormatted || ''}</dd></div>
                <div><dt>Vencimento</dt><dd>${invoice.dueDate || '—'}</dd></div>
                <div><dt>Pago em</dt><dd>${invoice.paidAt || '—'}</dd></div>
            </dl>
            ${invoice.invoiceUrl ? `<a class="button button-lime" href="${invoice.invoiceUrl}" target="_blank" rel="noopener noreferrer">Abrir fatura Asaas</a>` : ''}
            ${invoice.boleto?.url ? `<p><a href="${invoice.boleto.url}" target="_blank" rel="noopener noreferrer">Boleto</a></p>` : ''}
            ${invoice.pix?.copyPaste ? `<p>Pix copia e cola</p><textarea readonly rows="4" style="width:100%">${invoice.pix.copyPaste}</textarea>` : ''}
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
