(() => {
    const root = document.querySelector('[data-checkout]');
    if (!root) return;

    const form = root.querySelector('[data-checkout-form]');
    const cardFields = root.querySelector('[data-card-fields]');
    const errorEl = root.querySelector('[data-error]');
    const submitBtn = root.querySelector('[data-submit]');
    const result = root.querySelector('[data-result]');

    const onlyDigits = (value) => String(value || '').replace(/\D+/g, '');

    form.querySelectorAll('input[name="billingType"]').forEach((input) => {
        input.addEventListener('change', () => {
            const isCard = form.billingType.value === 'CREDIT_CARD';
            cardFields.hidden = !isCard;
        });
    });

    form.querySelectorAll('[data-mask]').forEach((input) => {
        input.addEventListener('input', () => {
            const kind = input.getAttribute('data-mask');
            let digits = onlyDigits(input.value);
            if (kind === 'cep') {
                digits = digits.slice(0, 8);
                input.value = digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
            } else if (kind === 'phone') {
                digits = digits.slice(0, 11);
                if (digits.length > 6) {
                    input.value = `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
                } else if (digits.length > 2) {
                    input.value = `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
                } else {
                    input.value = digits;
                }
            } else if (kind === 'doc') {
                digits = digits.slice(0, 14);
                if (digits.length <= 11) {
                    input.value = digits
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                } else {
                    input.value = digits
                        .replace(/^(\d{2})(\d)/, '$1.$2')
                        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                        .replace(/\.(\d{3})(\d)/, '.$1/$2')
                        .replace(/(\d{4})(\d)/, '$1-$2');
                }
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
        result.querySelector('[data-result-status]').textContent = `Status: ${order.status}`;

        const pixBox = result.querySelector('[data-result-pix]');
        const boletoBox = result.querySelector('[data-result-boleto]');
        const cardBox = result.querySelector('[data-result-card]');
        pixBox.hidden = true;
        boletoBox.hidden = true;
        cardBox.hidden = true;

        if (order.billingType === 'PIX') {
            pixBox.hidden = false;
            const img = result.querySelector('[data-pix-image]');
            if (order.pix?.qrCodeBase64) {
                img.src = `data:image/png;base64,${order.pix.qrCodeBase64}`;
                img.hidden = false;
            } else {
                img.hidden = true;
            }
            result.querySelector('[data-pix-copy]').value = order.pix?.copyPaste || '';
            if (!order.pix?.copyPaste && order.invoiceUrl) {
                const note = document.createElement('p');
                note.innerHTML = `QR ainda não disponível. Pague pela fatura Asaas: <a href="${order.invoiceUrl}" target="_blank" rel="noopener noreferrer">abrir fatura</a>`;
                pixBox.appendChild(note);
            }
        }

        if (order.billingType === 'BOLETO') {
            boletoBox.hidden = false;
            const link = result.querySelector('[data-boleto-link]');
            link.href = order.boleto?.url || order.invoiceUrl || '#';
            result.querySelector('[data-boleto-line]').textContent = order.boleto?.identificationField || '';
        }

        if (order.billingType === 'CREDIT_CARD') {
            cardBox.hidden = false;
            const msg = order.status === 'paid_pending_provisioning'
                ? 'Pagamento confirmado. Sua hospedagem entrou na fila de provisionamento (ainda não está ativa).'
                : 'Pagamento em análise ou aguardando confirmação. Acompanhe o status do pedido.';
            result.querySelector('[data-card-msg]').textContent = msg;
            if (order.card?.last4) {
                result.querySelector('[data-card-msg]').textContent += ` Cartão final ${order.card.last4}.`;
            }
        }

        if (order.status === 'paid_pending_provisioning') {
            result.querySelector('[data-result-title]').textContent = 'Pagamento confirmado';
        }
    };

    result.querySelector('[data-copy-pix]')?.addEventListener('click', async () => {
        const value = result.querySelector('[data-pix-copy]').value;
        try {
            await navigator.clipboard.writeText(value);
        } catch (_) {
            result.querySelector('[data-pix-copy]').select();
            document.execCommand('copy');
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');
        submitBtn.disabled = true;

        const billingType = form.billingType.value;
        const payload = {
            csrfToken: root.dataset.csrf,
            plan: root.dataset.plan,
            period: root.dataset.period,
            billingType,
            name: form.name.value.trim(),
            email: form.email.value.trim(),
            cpfCnpj: onlyDigits(form.cpfCnpj.value),
            phone: onlyDigits(form.mobilePhone.value),
            mobilePhone: onlyDigits(form.mobilePhone.value),
            postalCode: onlyDigits(form.postalCode.value),
            address: form.address.value.trim(),
            addressNumber: form.addressNumber.value.trim(),
            complement: form.complement.value.trim(),
            city: form.city.value.trim(),
            province: form.province.value.trim().toUpperCase(),
            idempotencyKey: (window.crypto?.randomUUID?.() || `gh-${Date.now()}-${Math.random()}`),
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
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Não foi possível concluir o checkout.');
            }
            // Clear card fields from DOM after send
            form.cardNumber.value = '';
            form.cardCcv.value = '';
            renderResult(data.order);

            if (data.order.billingType === 'PIX' || data.order.billingType === 'BOLETO') {
                const poll = window.setInterval(async () => {
                    try {
                        const st = await fetch(`${root.dataset.statusUrl}?id=${encodeURIComponent(data.order.id)}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const body = await st.json();
                        if (body.ok && body.order?.status === 'paid_pending_provisioning') {
                            window.clearInterval(poll);
                            renderResult(body.order);
                        }
                    } catch (_) {
                        /* ignore transient poll errors */
                    }
                }, 5000);
                window.setTimeout(() => window.clearInterval(poll), 15 * 60 * 1000);
            }
        } catch (error) {
            showError(error.message || 'Erro inesperado.');
            submitBtn.disabled = false;
        }
    });
})();
