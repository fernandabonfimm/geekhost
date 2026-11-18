(() => {
    const root = document.querySelector('[data-wallet]');
    if (!root) return;

    const numberInput = root.querySelector('[data-card-number]');
    const nameInput = root.querySelector('[data-card-name]');
    const monthInput = root.querySelector('[data-card-month]');
    const yearInput = root.querySelector('[data-card-year]');
    const preview = root.querySelector('[data-card-preview]');
    const numberDisplay = root.querySelector('[data-card-number-display]');
    const nameDisplay = root.querySelector('[data-card-name-display]');
    const expiryDisplay = root.querySelector('[data-card-expiry-display]');
    const brandLabel = root.querySelector('[data-card-brand-label]');
    const brandPill = root.querySelector('[data-card-brand-pill]');

    const detectBrand = (digits) => {
        if (/^4/.test(digits)) return 'visa';
        if (/^(5[1-5]|2[2-7])/.test(digits)) return 'mastercard';
        if (/^3[47]/.test(digits)) return 'amex';
        if (/^(636368|438935|504175|451416|636297|5067|4576|4011|506699)/.test(digits)) return 'elo';
        if (/^606282|^3841/.test(digits)) return 'hipercard';
        return '';
    };

    const formatNumber = (digits, brand) => {
        if (brand === 'amex') {
            return digits.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, (_, a, b, c) => [a, b, c].filter(Boolean).join(' ')).trim();
        }
        return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
    };

    const sync = () => {
        const digits = String(numberInput?.value || '').replace(/\D+/g, '').slice(0, 19);
        const brand = detectBrand(digits);
        const pretty = formatNumber(digits, brand);
        if (numberInput && numberInput.value !== pretty) {
            const pos = numberInput.selectionStart;
            numberInput.value = pretty;
            if (typeof pos === 'number') numberInput.setSelectionRange(pos, pos);
        }

        const masked = pretty
            ? pretty.replace(/\d(?=[\d\s]{4})/g, '•')
            : '•••• •••• •••• ••••';
        if (numberDisplay) numberDisplay.textContent = masked || '•••• •••• •••• ••••';

        const name = String(nameInput?.value || '').trim().toUpperCase() || 'SEU NOME';
        if (nameDisplay) nameDisplay.textContent = name;

        const mm = String(monthInput?.value || '').replace(/\D+/g, '').slice(0, 2);
        const yyRaw = String(yearInput?.value || '').replace(/\D+/g, '');
        const yy = yyRaw.length >= 4 ? yyRaw.slice(-2) : yyRaw.slice(0, 2);
        if (expiryDisplay) expiryDisplay.textContent = (mm || 'MM') + '/' + (yy || 'AA');

        if (preview) preview.setAttribute('data-brand', brand);
        const label = brand ? brand.toUpperCase() : 'CARTÃO';
        if (brandLabel) brandLabel.textContent = label;
        if (brandPill) brandPill.textContent = brand ? label : '——';
    };

    [numberInput, nameInput, monthInput, yearInput].forEach((el) => {
        el?.addEventListener('input', sync);
    });
    sync();
})();
