(() => {
    const root = document.querySelector('[data-checkout]');
    if (!root) return;

    const refreshIcons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };
    window.addEventListener('load', refreshIcons);
    document.addEventListener('DOMContentLoaded', refreshIcons);

    const form = root.querySelector('[data-checkout-form]');
    const result = root.querySelector('[data-result]');
    const errorEl = root.querySelector('[data-error]');
    const submitBtn = root.querySelector('[data-submit]');
    const submitLabel = root.querySelector('[data-submit-label]');
    const toastHost = document.querySelector('[data-toast-host]');
    const periods = JSON.parse(root.dataset.periods || '{}');
    let step = 1;
    let setupUrl = '';
    let submitting = false;
    let maxReached = 1;

    const onlyDigits = (value) => String(value || '').replace(/\D+/g, '');
    const formatBRL = (n) => 'R$ ' + Number(n).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    const toast = (message, type = 'ok') => {
        if (!toastHost) return;
        const el = document.createElement('div');
        el.className = `co-toast is-${type === 'danger' ? 'danger' : 'ok'}`;
        el.textContent = message;
        toastHost.appendChild(el);
        setTimeout(() => el.remove(), 4200);
    };

    const setHero = (title, sub, eyebrow) => {
        const t = root.querySelector('[data-hero-title]');
        const s = root.querySelector('[data-hero-sub]');
        const e = root.querySelector('[data-hero-eyebrow]');
        if (t) t.textContent = title;
        if (s) s.textContent = sub;
        if (e && eyebrow) e.textContent = eyebrow;
    };

    const showStep = (n, { completedUntil = null } = {}) => {
        step = n;
        maxReached = Math.max(maxReached, n);
        root.querySelectorAll('[data-step]').forEach((el) => {
            el.hidden = Number(el.getAttribute('data-step')) !== n;
        });
        root.querySelectorAll('[data-step-indicator]').forEach((el) => {
            const i = Number(el.getAttribute('data-step-indicator'));
            const doneUntil = completedUntil !== null ? completedUntil : Math.max(0, n - 1);
            el.classList.toggle('is-active', i === n);
            el.classList.toggle('is-done', i <= doneUntil && i !== n);
            const dot = el.querySelector('[data-step-dot]');
            if (dot) {
                if (i <= doneUntil && i !== n) {
                    dot.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                } else {
                    dot.textContent = String(i);
                }
            }
        });
        refreshIcons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const syncSummary = () => {
        const period = form.period?.value || root.dataset.period;
        const info = periods[period] || {};
        const periodEl = root.querySelector('[data-summary-period]');
        const totalEl = root.querySelector('[data-summary-total]');
        const renewEl = root.querySelector('[data-summary-renew]');
        const domainEl = root.querySelector('[data-summary-domain]');
        if (periodEl) periodEl.textContent = info.label || period;
        if (totalEl) totalEl.textContent = formatBRL(info.amount || root.dataset.amount);
        const renew = info.renew_amount || root.dataset.renew;
        const months = info.interval_months || 1;
        if (renewEl) {
            renewEl.textContent = info.promo
                ? `Renova depois por ${formatBRL(renew)}/mês via Assinatura Asaas.`
                : `Renova em ${months} meses por ${formatBRL(renew)}.`;
        }
        const domain = form.domain?.value?.trim();
        if (domainEl) domainEl.textContent = domain ? `Domínio: ${domain}` : 'Domínio: —';
        root.dataset.period = period;
        root.dataset.amount = String(info.amount || root.dataset.amount);
    };

    const validateDomain = () => {
        const input = form.domain;
        const error = root.querySelector('[data-domain-error]');
        const ok = root.querySelector('[data-domain-ok]');
        let domain = input.value.trim().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '');
        input.value = domain;
        const valid = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i.test(domain);
        if (error) {
            error.hidden = valid;
            error.textContent = valid ? '' : 'Informe um domínio válido, ex: meusite.com.br';
        }
        if (ok) ok.hidden = !valid;
        return valid;
    };

    const showError = (message) => {
        if (!errorEl) return;
        errorEl.hidden = !message;
        errorEl.textContent = message || '';
    };

    root.querySelectorAll('[data-domain-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled || btn.getAttribute('data-domain-mode') === 'register') {
                toast('Registro de domínio em breve.', 'danger');
                return;
            }
            root.querySelectorAll('[data-domain-mode]').forEach((b) => {
                b.classList.toggle('is-selected', b === btn);
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
        });
    });

    root.querySelectorAll('[data-next-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = Number(btn.getAttribute('data-next-step'));
            if (next === 2) {
                if (!validateDomain()) {
                    toast('Corrija o domínio para continuar.', 'danger');
                    return;
                }
                syncSummary();
            }
            if (next === 3) syncSummary();
            showStep(next);
        });
    });
    root.querySelectorAll('[data-prev-step]').forEach((btn) => {
        btn.addEventListener('click', () => showStep(Number(btn.getAttribute('data-prev-step'))));
    });

    // Allow clicking completed/previous steps in stepper
    root.querySelectorAll('[data-step-indicator]').forEach((el) => {
        el.style.cursor = 'pointer';
        el.addEventListener('click', () => {
            const i = Number(el.getAttribute('data-step-indicator'));
            if (i < 4 && i <= maxReached && step !== 4) showStep(i);
        });
    });

    form.querySelectorAll('input[name="period"]').forEach((input) => {
        input.addEventListener('change', () => {
            form.querySelectorAll('.co-period').forEach((card) => card.classList.remove('is-selected'));
            input.closest('.co-period')?.classList.add('is-selected');
            syncSummary();
        });
    });

    const syncPayDetail = () => {
        const type = form.billingType.value;
        root.querySelectorAll('[data-pay-detail]').forEach((el) => {
            el.hidden = el.getAttribute('data-pay-detail') !== type;
        });
        form.querySelectorAll('.co-pay').forEach((card) => {
            card.classList.toggle('is-selected', card.querySelector('input')?.value === type);
        });
        const cardFields = root.querySelector('[data-card-fields]');
        if (cardFields) cardFields.hidden = type !== 'CREDIT_CARD';
    };
    form.querySelectorAll('input[name="billingType"]').forEach((input) => {
        input.addEventListener('change', syncPayDetail);
    });

    form.domain?.addEventListener('input', () => {
        validateDomain();
        syncSummary();
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

    const setConfirmVisual = (tone) => {
        const icon = result.querySelector('[data-result-icon]');
        if (!icon) return;
        icon.classList.remove('is-warn', 'is-danger');
        if (tone === 'warn') icon.classList.add('is-warn');
        if (tone === 'danger') icon.classList.add('is-danger');
    };

    const renderResult = (order) => {
        form.hidden = true;
        result.hidden = false;
        showStep(4, { completedUntil: order.status === 'paid_pending_provisioning' ? 4 : 3 });

        const paid = order.status === 'paid_pending_provisioning';
        const pending = ['awaiting_payment', 'overdue'].includes(order.status);
        const refused = ['canceled', 'refunded'].includes(order.status);

        setHero(
            paid ? 'Pedido concluído!' : pending ? 'Pedido recebido' : refused ? 'Pagamento não confirmado' : 'Pedido criado',
            paid ? 'Sua hospedagem foi contratada com sucesso.' : 'Acompanhe o status real do pagamento Asaas.',
            'Checkout GeekHost'
        );

        result.querySelector('[data-order-id]').textContent = order.id;
        const title = result.querySelector('[data-result-title]');
        const status = result.querySelector('[data-result-status]');
        const panelCta = result.querySelector('[data-panel-cta]');
        const setupLink = result.querySelector('[data-setup-link]');
        const setupHint = result.querySelector('[data-setup-hint]');
        const paidBox = root.querySelector('[data-summary-paid]');

        if (paid) {
            setConfirmVisual('ok');
            title.textContent = 'Pagamento aprovado!';
            status.textContent = 'Estamos preparando sua hospedagem. O provisionamento real pode levar alguns minutos — o painel não marca o serviço como ativo antes disso.';
            if (panelCta) {
                panelCta.href = root.dataset.panelUrl || root.dataset.loginUrl;
                panelCta.textContent = 'Acessar minha área do cliente';
            }
            if (paidBox) {
                paidBox.hidden = false;
                const amt = paidBox.querySelector('[data-paid-amount]');
                if (amt) amt.textContent = order.amountFormatted || formatBRL(order.amount || root.dataset.amount);
            }
        } else if (pending) {
            setConfirmVisual('warn');
            title.textContent = 'Aguardando confirmação do pagamento';
            status.textContent = 'Seu pedido foi recebido. Estamos aguardando a confirmação do pagamento no Asaas.';
            if (panelCta) {
                panelCta.href = root.dataset.loginUrl;
                panelCta.textContent = 'Ir para o login do painel';
            }
        } else if (refused) {
            setConfirmVisual('danger');
            title.textContent = 'Não foi possível confirmar seu pagamento';
            status.textContent = `Status: ${order.statusLabel || order.status}. Você pode iniciar um novo checkout com o mesmo plano.`;
            if (panelCta) {
                panelCta.href = window.location.pathname + window.location.search;
                panelCta.textContent = 'Tentar novamente';
            }
        } else {
            setConfirmVisual('warn');
            title.textContent = 'Pedido criado';
            status.textContent = `Status: ${order.statusLabel || order.status}`;
        }

        const pixBox = result.querySelector('[data-result-pix]');
        const boletoBox = result.querySelector('[data-result-boleto]');
        const cardBox = result.querySelector('[data-result-card]');
        pixBox.hidden = true; boletoBox.hidden = true; cardBox.hidden = true;

        if (order.passwordSetupUrl) setupUrl = order.passwordSetupUrl;
        if (setupLink && setupUrl) {
            setupLink.href = setupUrl;
            setupLink.hidden = !paid;
        }
        if (setupHint) {
            setupHint.hidden = !(setupUrl && !paid);
        }

        if (order.billingType === 'PIX' && !paid) {
            pixBox.hidden = false;
            const img = result.querySelector('[data-pix-image]');
            if (order.pix?.qrCodeBase64) {
                img.src = `data:image/png;base64,${order.pix.qrCodeBase64}`;
                img.hidden = false;
            } else img.hidden = true;
            result.querySelector('[data-pix-copy]').value = order.pix?.copyPaste || '';
        }
        if (order.billingType === 'BOLETO' && !paid) {
            boletoBox.hidden = false;
            result.querySelector('[data-boleto-link]').href = order.boleto?.url || order.invoiceUrl || '#';
            result.querySelector('[data-boleto-line]').textContent = order.boleto?.identificationField || '';
        }
        if (order.billingType === 'CREDIT_CARD') {
            cardBox.hidden = false;
            result.querySelector('[data-card-msg]').textContent = paid
                ? 'Pagamento confirmado. Crie sua senha para acessar o painel.'
                : 'Pagamento em análise no Asaas.';
        }

        if (paid && setupUrl) {
            window.setTimeout(() => { window.location.href = setupUrl; }, 1600);
        }
        refreshIcons();
    };

    result.querySelector('[data-copy-pix]')?.addEventListener('click', async () => {
        const value = result.querySelector('[data-pix-copy]').value;
        try {
            await navigator.clipboard.writeText(value);
            toast('Código Pix copiado.');
        } catch (_) {
            result.querySelector('[data-pix-copy]').select();
            document.execCommand('copy');
            toast('Código Pix copiado.');
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');
        if (submitting) return;
        if (!form.terms?.checked) {
            showError('Aceite os Termos de Uso para continuar.');
            return;
        }
        if (!validateDomain()) {
            showError('Informe um domínio válido.');
            showStep(1);
            return;
        }

        submitting = true;
        submitBtn.disabled = true;
        if (submitLabel) submitLabel.textContent = 'Processando…';

        const billingType = form.billingType.value;
        const payload = {
            csrfToken: root.dataset.csrf,
            plan: root.dataset.plan,
            period: form.period.value,
            domain: form.domain.value.trim(),
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
            if (data.order.passwordSetupUrl) setupUrl = data.order.passwordSetupUrl;
            toast('Pedido criado com sucesso.');
            renderResult(data.order);
            if (data.order.billingType === 'PIX' || data.order.billingType === 'BOLETO') {
                const poll = window.setInterval(async () => {
                    try {
                        const st = await fetch(`${root.dataset.statusUrl}?id=${encodeURIComponent(data.order.id)}`, { credentials: 'same-origin' });
                        const body = await st.json();
                        if (body.ok && body.order?.status === 'paid_pending_provisioning') {
                            window.clearInterval(poll);
                            if (setupUrl) body.order.passwordSetupUrl = setupUrl;
                            renderResult(body.order);
                        }
                    } catch (_) { /* ignore */ }
                }, 5000);
                window.setTimeout(() => window.clearInterval(poll), 15 * 60 * 1000);
            }
        } catch (error) {
            showError(error.message || 'Erro inesperado.');
            toast(error.message || 'Erro no checkout.', 'danger');
            submitting = false;
            submitBtn.disabled = false;
            if (submitLabel) submitLabel.textContent = 'Finalizar pedido';
        }
    });

    syncSummary();
    syncPayDetail();
    showStep(1);
    refreshIcons();
})();
