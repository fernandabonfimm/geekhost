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

    const pixImageSrc = (order) => {
        const raw = String(order?.pix?.qrCodeBase64 || '').replace(/\s+/g, '');
        if (raw.startsWith('data:image/')) return raw;
        if (raw) {
            const mime = raw.startsWith('/9j/') ? 'jpeg' : 'png';
            return `data:image/${mime};base64,${raw}`;
        }
        const payload = String(order?.pix?.copyPaste || '');
        if (!payload || typeof qrcode !== 'function') return '';
        try {
            const qr = qrcode(0, 'M');
            qr.addData(payload);
            qr.make();
            return qr.createDataURL(6, 8);
        } catch (_) {
            return '';
        }
    };

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
        if (form) form.hidden = n === 4;
        if (result) result.hidden = n !== 4;
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

    const resetSubmit = () => {
        submitting = false;
        if (submitBtn) submitBtn.disabled = false;
        if (submitLabel) submitLabel.textContent = 'Finalizar pedido';
    };

    const rememberOrder = (orderId) => {
        if (!orderId) return;
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('order', orderId);
            window.history.replaceState({}, '', url.toString());
        } catch (_) { /* ignore */ }
    };

    let pollTimer = null;
    const stopPoll = () => {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
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
                ? `Renova depois por ${formatBRL(renew)}/mês via assinatura automática.`
                : `Renova em ${months} meses por ${formatBRL(renew)}.`;
        }
        const domain = form.domain?.value?.trim();
        if (domainEl) {
            domainEl.textContent = domainMode === 'later'
                ? 'Domínio:  '
                : (domain ? `Domínio: ${domain}` : 'Domínio: —');
        }
        root.dataset.period = period;
        root.dataset.amount = String(info.amount || root.dataset.amount);
    };

    let domainMode = 'own';
    const ownBox = root.querySelector('[data-domain-own]');
    const laterNote = root.querySelector('[data-domain-later]');
    const applyDomainMode = (mode) => {
        domainMode = mode === 'later' ? 'later' : 'own';
        root.querySelectorAll('[data-domain-mode]').forEach((b) => {
            const on = b.getAttribute('data-domain-mode') === domainMode;
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (ownBox) ownBox.hidden = domainMode === 'later';
        if (laterNote) laterNote.hidden = domainMode !== 'later';
        if (form.domain) {
            form.domain.required = domainMode !== 'later';
            if (domainMode === 'later') form.domain.value = '';
        }
        syncSummary();
    };

    const validateDomain = () => {
        if (domainMode === 'later') {
            const error = root.querySelector('[data-domain-error]');
            const ok = root.querySelector('[data-domain-ok]');
            if (error) error.hidden = true;
            if (ok) ok.hidden = true;
            return true;
        }
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
            if (btn.disabled) return;
            applyDomainMode(btn.getAttribute('data-domain-mode') || 'own');
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
        const label = root.querySelector('[data-submit-label]');
        if (label && !submitting) {
            label.textContent = type === 'PIX' ? 'Gerar PIX' : type === 'BOLETO' ? 'Gerar boleto' : 'Confirmar cartão';
        }
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
        if (!order || !result) return;
        rememberOrder(order.id);
        showStep(4, { completedUntil: order.status === 'paid_pending_provisioning' || order.status === 'trial_active' ? 4 : 3 });

        const trial = order.status === 'trial_active';
        const paid = order.status === 'paid_pending_provisioning';
        const pending = ['awaiting_payment', 'overdue'].includes(order.status);
        const refused = ['canceled', 'refunded'].includes(order.status);

        setHero(
            trial ? 'Cartão confirmado' : paid ? 'Pedido concluído!' : order.billingType === 'PIX' ? 'Pague com Pix' : pending ? 'Pedido recebido' : refused ? 'Pagamento não confirmado' : 'Pedido criado',
            trial ? 'Você tem 7 dias grátis. A cobrança no cartão começa depois disso.' : paid ? 'Sua hospedagem foi contratada com sucesso.' : 'Conclua o pagamento nesta etapa para liberar o painel.',
            'Checkout GeekHost'
        );

        const orderIdEl = result.querySelector('[data-order-id]');
        if (orderIdEl) orderIdEl.textContent = order.id || '—';
        const title = result.querySelector('[data-result-title]');
        const status = result.querySelector('[data-result-status]');
        const panelCta = result.querySelector('[data-panel-cta]');
        const setupLink = result.querySelector('[data-setup-link]');
        const setupHint = result.querySelector('[data-setup-hint]');
        const paidBox = root.querySelector('[data-summary-paid]');

        if (trial) {
            setConfirmVisual('ok');
            const when = order.chargeOn ? new Date(order.chargeOn + 'T12:00:00').toLocaleDateString('pt-BR') : '7 dias';
            if (title) title.textContent = 'Cartão confirmado — 7 dias grátis';
            if (status) {
                status.textContent = `Não cobramos agora. O cartão fica salvo e a primeira cobrança sai em ${when}. PIX, boleto e débito não têm esses 7 dias: esses meios são cobrados na hora. Se cancelar, não há reembolso e o uso segue até a próxima data de cobrança.`;
            }
            if (panelCta) {
                panelCta.href = root.dataset.panelUrl || root.dataset.loginUrl;
                panelCta.textContent = 'Ir para o painel';
            }
        } else if (paid) {
            setConfirmVisual('ok');
            if (title) title.textContent = 'Pagamento aprovado!';
            if (status) status.textContent = 'Estamos preparando sua hospedagem. Agora crie a senha do painel para acessar sua conta.';
            if (panelCta) {
                panelCta.href = root.dataset.panelUrl || root.dataset.loginUrl;
                panelCta.textContent = 'Ir para o painel';
            }
            if (paidBox) {
                paidBox.hidden = false;
                const amt = paidBox.querySelector('[data-paid-amount]');
                if (amt) amt.textContent = order.amountFormatted || formatBRL(order.amount || root.dataset.amount);
            }
        } else if (pending) {
            setConfirmVisual('warn');
            if (title) {
                title.textContent = order.billingType === 'PIX'
                    ? 'Quase lá — finalize o Pix'
                    : order.billingType === 'BOLETO'
                        ? 'Boleto gerado'
                        : 'Aguardando confirmação do pagamento';
            }
            if (status) {
                status.textContent = order.billingType === 'PIX'
                    ? 'Use o QR Code ou o código copia e cola abaixo. Assim que o Pix for confirmado, liberamos a criação da senha.'
                    : 'Seu pedido foi recebido. Estamos aguardando a confirmação do pagamento.';
            }
            if (panelCta) {
                panelCta.href = root.dataset.loginUrl;
                panelCta.textContent = 'Já tenho senha — ir ao login';
            }
        } else if (refused) {
            setConfirmVisual('danger');
            if (title) title.textContent = 'Não foi possível confirmar seu pagamento';
            if (status) status.textContent = `Status: ${order.statusLabel || order.status}. Você pode iniciar um novo checkout com o mesmo plano.`;
            if (panelCta) {
                const url = new URL(window.location.href);
                url.searchParams.delete('order');
                panelCta.href = url.pathname + url.search;
                panelCta.textContent = 'Tentar novamente';
            }
        } else {
            setConfirmVisual('warn');
            if (title) title.textContent = 'Pedido criado';
            if (status) status.textContent = `Status: ${order.statusLabel || order.status}`;
        }

        const pixBox = result.querySelector('[data-result-pix]');
        const boletoBox = result.querySelector('[data-result-boleto]');
        const cardBox = result.querySelector('[data-result-card]');
        if (pixBox) pixBox.hidden = true;
        if (boletoBox) boletoBox.hidden = true;
        if (cardBox) cardBox.hidden = true;

        if (order.passwordSetupUrl) setupUrl = order.passwordSetupUrl;
        if (setupLink) {
            if (setupUrl) {
                setupLink.href = setupUrl;
                setupLink.hidden = !paid;
                setupLink.className = paid ? 'co-btn co-btn-primary' : 'co-btn co-btn-ghost';
                if (panelCta) panelCta.className = paid ? 'co-btn co-btn-ghost' : 'co-btn co-btn-ghost';
            } else {
                setupLink.hidden = true;
            }
        }
        if (setupHint) {
            setupHint.hidden = !(setupUrl && !paid);
        }

        if (order.billingType === 'PIX' && !paid && pixBox) {
            pixBox.hidden = false;
            const img = result.querySelector('[data-pix-image]');
            const copy = result.querySelector('[data-pix-copy]');
            const waiting = result.querySelector('[data-pix-waiting]');
            const hasQr = Boolean(order.pix?.qrCodeBase64);
            const hasCopy = Boolean(order.pix?.copyPaste);
            if (img) {
                const drawn = pixImageSrc(order);
                if (drawn) {
                    img.src = drawn;
                    img.hidden = false;
                } else {
                    img.removeAttribute('src');
                    img.hidden = true;
                }
            }
            if (copy) copy.value = order.pix?.copyPaste || '';
            if (waiting) {
                waiting.hidden = hasQr || hasCopy;
                if (!hasQr && !hasCopy) {
                    waiting.hidden = false;
                    const err = String(order.pixError || '');
                    waiting.textContent = err.includes('chave Pix')
                        ? err
                        : 'Gerando o QR Code do Pix…';
                    if (err && root.dataset.helpOpened !== '1') {
                        root.dataset.helpOpened = '1';
                        openWhatsappHelp('PIX');
                    }
                }
            }
        }
        if (order.billingType === 'BOLETO' && !paid && boletoBox) {
            boletoBox.hidden = false;
            const link = result.querySelector('[data-boleto-link]');
            const line = result.querySelector('[data-boleto-line]');
            if (link) link.href = order.boleto?.url || order.invoiceUrl || '#';
            if (line) line.textContent = order.boleto?.identificationField || '';
        }
        if (order.billingType === 'CREDIT_CARD' && cardBox) {
            cardBox.hidden = false;
            const msg = result.querySelector('[data-card-msg]');
            if (msg) {
                msg.textContent = paid
                    ? 'Pagamento confirmado. Vamos te levar para criar a senha do painel.'
                    : 'Pagamento em análise. Assim que confirmar, você poderá criar a senha.';
            }
        }

        if (paid && setupUrl) {
            window.setTimeout(() => { window.location.href = setupUrl; }, 1200);
        }
        refreshIcons();
    };

    const paymentLabel = (type) => {
        if (type === 'PIX') return 'Pix';
        if (type === 'BOLETO') return 'boleto';
        if (type === 'CREDIT_CARD') return 'cartão';
        return type || 'pagamento';
    };

    const openWhatsappHelp = (billingType) => {
        const phone = String(root.dataset.whatsapp || '').replace(/\D/g, '');
        if (!phone) return;
        const plan = root.dataset.planName || 'plano';
        const amount = (root.querySelector('[data-summary-total]')?.textContent || '').trim() || 'o valor do plano';
        const text = `Preciso de ajuda para finalizar a compra do ${plan} no valor de ${amount} utilizando o método de pagamento ${paymentLabel(billingType)}.`;
        const url = `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    };

    const startPaymentPoll = (orderId) => {
        stopPoll();
        if (!orderId) return;
        let ticks = 0;
        pollTimer = window.setInterval(async () => {
            ticks += 1;
            if (ticks > 180) {
                stopPoll();
                return;
            }
            try {
                const st = await fetch(`${root.dataset.statusUrl}?id=${encodeURIComponent(orderId)}`, { credentials: 'same-origin' });
                const body = await st.json();
                if (!body.ok || !body.order) return;
                if (setupUrl) body.order.passwordSetupUrl = setupUrl;
                renderResult(body.order);
                if (body.order.status === 'paid_pending_provisioning' || (body.order.pixError && String(body.order.pixError).includes('chave Pix'))) {
                    stopPoll();
                }
            } catch (_) { /* keep polling */ }
        }, 4000);
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
            domain: domainMode === 'later' ? '' : form.domain.value.trim(),
            domainLater: domainMode === 'later',
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

        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timeoutId = controller ? window.setTimeout(() => controller.abort(), 45000) : null;

        try {
            const response = await fetch(root.dataset.createUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                signal: controller?.signal,
            });
            let data = null;
            try {
                data = await response.json();
            } catch (_) {
                throw new Error('Resposta inválida do servidor. Recarregue e tente novamente.');
            }
            if (!response.ok || !data?.ok || !data.order) {
                const failure = new Error(data?.error || 'Não foi possível concluir o checkout.');
                failure.asaas = Boolean(data?.asaas);
                throw failure;
            }
            if (form.cardNumber) form.cardNumber.value = '';
            if (form.cardCcv) form.cardCcv.value = '';
            if (data.order.passwordSetupUrl) setupUrl = data.order.passwordSetupUrl;
            toast('Pedido criado com sucesso.');
            renderResult(data.order);
            if (data.order.billingType === 'PIX' || data.order.billingType === 'BOLETO' || data.order.status !== 'paid_pending_provisioning') {
                startPaymentPoll(data.order.id);
            }
        } catch (error) {
            const message = error?.name === 'AbortError'
                ? 'O pagamento está demorando. Se o Pix foi gerado, recarregue a página em alguns segundos.'
                : (error.message || 'Erro inesperado.');
            showError(message);
            toast(message, 'danger');
            showStep(3);
            if (error?.asaas) {
                openWhatsappHelp(billingType);
            }
        } finally {
            if (timeoutId) window.clearTimeout(timeoutId);
            resetSubmit();
        }
    });

    syncSummary();
    syncPayDetail();

    // Resume confirmation step when returning with ?order=
    const resumeOrder = new URLSearchParams(window.location.search).get('order');
    if (resumeOrder && /^[a-f0-9-]{36}$/i.test(resumeOrder)) {
        (async () => {
            try {
                const st = await fetch(`${root.dataset.statusUrl}?id=${encodeURIComponent(resumeOrder)}`, { credentials: 'same-origin' });
                const body = await st.json();
                if (body.ok && body.order) {
                    renderResult(body.order);
                    if (body.order.status !== 'paid_pending_provisioning') {
                        startPaymentPoll(body.order.id);
                    }
                    return;
                }
            } catch (_) { /* fall through */ }
            showStep(1);
        })();
    } else {
        showStep(1);
    }
    refreshIcons();
})();
