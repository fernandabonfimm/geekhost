(() => {
    const root = document.querySelector('[data-checkout]');
    if (!root) return;

    const form = root.querySelector('[data-checkout-form]');
    const cardFields = root.querySelector('[data-card-fields]');
    const errorEl = root.querySelector('[data-error]');
    const submitBtn = root.querySelector('[data-submit]');
    const result = root.querySelector('[data-result]');
    const periods = JSON.parse(root.dataset.periods || '{}');
    let step = 1;

    const onlyDigits = (value) => String(value || '').replace(/\D+/g, '');
    const formatBRL = (n) => 'R$ ' + Number(n).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    const showStep = (n) => {
        step = n;
        root.querySelectorAll('[data-step]').forEach((el) => {
            el.hidden = Number(el.getAttribute('data-step')) !== n;
        });
        root.querySelectorAll('[data-step-indicator]').forEach((el) => {
            el.classList.toggle('is-active', Number(el.getAttribute('data-step-indicator')) <= n);
        });
    };

    const syncSummary = () => {
        const period = form.period?.value || root.dataset.period;
        const info = periods[period] || {};
        root.querySelector('[data-summary-period]').textContent = info.label || period;
        root.querySelector('[data-summary-total]').textContent = formatBRL(info.amount || root.dataset.amount);
        const renew = info.renew_amount || root.dataset.renew;
        const months = info.interval_months || 1;
        root.querySelector('[data-summary-renew]').textContent = info.promo
            ? `Renova depois por ${formatBRL(renew)}/mês via Assinatura Asaas.`
            : `Renova em ${months} meses por ${formatBRL(renew)}.`;
        const domain = form.domain?.value?.trim();
        root.querySelector('[data-summary-domain]').textContent = domain ? `Domínio: ${domain}` : 'Domínio: —';
        root.dataset.period = period;
        root.dataset.amount = String(info.amount || root.dataset.amount);
    };

    root.querySelectorAll('[data-next-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = Number(btn.getAttribute('data-next-step'));
            if (next === 2) {
                const domain = form.domain.value.trim().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '');
                form.domain.value = domain;
                if (!/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i.test(domain)) {
                    showError('Informe um domínio válido, ex: meusite.com.br');
                    return;
                }
                showError('');
                syncSummary();
            }
            if (next === 3) syncSummary();
            showStep(next);
        });
    });
    root.querySelectorAll('[data-prev-step]').forEach((btn) => {
        btn.addEventListener('click', () => showStep(Number(btn.getAttribute('data-prev-step'))));
    });

    form.querySelectorAll('input[name="period"]').forEach((input) => {
        input.addEventListener('change', () => {
            form.querySelectorAll('.checkout-period-card').forEach((card) => card.classList.remove('is-selected'));
            input.closest('.checkout-period-card')?.classList.add('is-selected');
            syncSummary();
        });
    });

    form.querySelectorAll('input[name="billingType"]').forEach((input) => {
        input.addEventListener('change', () => {
            cardFields.hidden = form.billingType.value !== 'CREDIT_CARD';
        });
    });

    form.domain?.addEventListener('input', syncSummary);

    form.querySelectorAll('[data-mask]').forEach((input) => {
        input.addEventListener('input', () => {
            const kind = input.getAttribute('data-mask');
            let digits = onlyDigits(input.value);
            if (kind === 'cep') {
                digits = digits.slice(0, 8);
                input.value = digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
            } else if (kind === 'phone') {
                digits = digits.slice(0, 11);
                input.value = digits.length > 6
                    ? `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
                    : digits.length > 2 ? `(${digits.slice(0, 2)}) ${digits.slice(2)}` : digits;
            } else if (kind === 'doc') {
                digits = digits.slice(0, 14);
                input.value = digits.length <= 11
                    ? digits.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                    : digits.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
            } else if (kind === 'card') {
                digits = digits.slice(0, 16);
                input.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
            }
        });
    });

    const showError = (message) => {
        errorEl.hidden = !message;
        errorEl.textContent = message || '';
    };

    const renderResult = (order) => {
        form.hidden = true;
        root.querySelector('.checkout-summary').hidden = true;
        result.hidden = false;
        result.querySelector('[data-order-id]').textContent = order.id;
        result.querySelector('[data-result-status]').textContent = `Status: ${order.statusLabel || order.status}`;
        const pixBox = result.querySelector('[data-result-pix]');
        const boletoBox = result.querySelector('[data-result-boleto]');
        const cardBox = result.querySelector('[data-result-card]');
        pixBox.hidden = true; boletoBox.hidden = true; cardBox.hidden = true;

        if (order.billingType === 'PIX') {
            pixBox.hidden = false;
            const img = result.querySelector('[data-pix-image]');
            if (order.pix?.qrCodeBase64) {
                img.src = `data:image/png;base64,${order.pix.qrCodeBase64}`;
                img.hidden = false;
            } else img.hidden = true;
            result.querySelector('[data-pix-copy]').value = order.pix?.copyPaste || '';
            if (!order.pix?.copyPaste && order.invoiceUrl) {
                const note = document.createElement('p');
                note.innerHTML = `QR ainda não disponível. <a href="${order.invoiceUrl}" target="_blank" rel="noopener noreferrer">Abrir fatura Asaas</a>`;
                pixBox.appendChild(note);
            }
        }
        if (order.billingType === 'BOLETO') {
            boletoBox.hidden = false;
            result.querySelector('[data-boleto-link]').href = order.boleto?.url || order.invoiceUrl || '#';
            result.querySelector('[data-boleto-line]').textContent = order.boleto?.identificationField || '';
        }
        if (order.billingType === 'CREDIT_CARD') {
            cardBox.hidden = false;
            result.querySelector('[data-card-msg]').textContent = order.status === 'paid_pending_provisioning'
                ? 'Pagamento confirmado. Conta do painel criada; hospedagem em fila de provisionamento.'
                : 'Pagamento em análise. Acompanhe em Minhas faturas.';
        }
        if (order.status === 'paid_pending_provisioning') {
            result.querySelector('[data-result-title]').textContent = 'Pagamento confirmado';
        }
    };

    result.querySelector('[data-copy-pix]')?.addEventListener('click', async () => {
        const value = result.querySelector('[data-pix-copy]').value;
        try { await navigator.clipboard.writeText(value); } catch (_) {
            result.querySelector('[data-pix-copy]').select();
            document.execCommand('copy');
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');
        if (!form.terms?.checked) {
            showError('Aceite os Termos de Uso para continuar.');
            return;
        }
        submitBtn.disabled = true;
        const billingType = form.billingType.value;
        const payload = {
            csrfToken: root.dataset.csrf,
            plan: root.dataset.plan,
            period: form.period.value,
            domain: form.domain.value.trim(),
            billingType,
            name: form.name.value.trim(),
            email: form.email.value.trim(),
            password: form.password.value,
            cpfCnpj: onlyDigits(form.cpfCnpj.value),
            phone: onlyDigits(form.mobilePhone.value),
            mobilePhone: onlyDigits(form.mobilePhone.value),
            postalCode: onlyDigits(form.postalCode.value),
            address: form.address.value.trim(),
            addressNumber: form.addressNumber.value.trim(),
            complement: form.complement.value.trim(),
            city: form.city.value.trim(),
            province: form.province.value.trim().toUpperCase(),
            idempotencyKey: (window.crypto?.randomUUID?.() || `gh-${Date.now()}`),
        };
        if (billingType === 'CREDIT_CARD') {
            payload.creditCard = {
                holderName: form.cardHolderName.value.trim(),
                number: onlyDigits(form.cardNumber.value),
                expiryMonth: form.cardExpiryMonth.value.trim(),
                expiryYear: form.cardExpiryYear.value.trim(),
                ccv: form.cardCcv.value.trim(),
            };
            payload.creditCardHolderInfo = {
                name: form.cardHolderName.value.trim() || payload.name,
                email: payload.email,
                cpfCnpj: payload.cpfCnpj,
                postalCode: payload.postalCode,
                addressNumber: payload.addressNumber,
                phone: payload.phone,
                mobilePhone: payload.mobilePhone,
            };
        }
        try {
            const response = await fetch(root.dataset.createUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Não foi possível concluir o checkout.');
            form.cardNumber.value = '';
            form.cardCcv.value = '';
            form.password.value = '';
            renderResult(data.order);
            if (data.order.billingType === 'PIX' || data.order.billingType === 'BOLETO') {
                const poll = window.setInterval(async () => {
                    try {
                        const st = await fetch(`${root.dataset.statusUrl}?id=${encodeURIComponent(data.order.id)}`, { credentials: 'same-origin' });
                        const body = await st.json();
                        if (body.ok && body.order?.status === 'paid_pending_provisioning') {
                            window.clearInterval(poll);
                            renderResult(body.order);
                        }
                    } catch (_) { /* ignore */ }
                }, 5000);
                window.setTimeout(() => window.clearInterval(poll), 15 * 60 * 1000);
            }
        } catch (error) {
            showError(error.message || 'Erro inesperado.');
            submitBtn.disabled = false;
        }
    });

    syncSummary();
    showStep(1);
})();
