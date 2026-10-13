document.addEventListener('DOMContentLoaded', () => {
    const scrollTopButton = document.querySelector('[data-scroll-top]');

    if (scrollTopButton) {
        const updateScrollTopVisibility = () => {
            scrollTopButton.hidden = window.scrollY < 360;
        };

        updateScrollTopVisibility();
        window.addEventListener('scroll', updateScrollTopVisibility, { passive: true });
        scrollTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'auto' });
        });
    }

    const menuToggle = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.site-navigation');

    if (menuToggle && navigation) {
        menuToggle.addEventListener('click', () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
            navigation.classList.toggle('is-open', !isExpanded);
        });
    }

    const contactForm = document.querySelector('[data-contact-form]');
    if (contactForm) {
        contactForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const email = contactForm.dataset.contactEmail;
            const formData = new FormData(contactForm);
            const status = document.querySelector('[data-contact-status]');

            if (!email) {
                status.textContent = 'O canal de e-mail ainda não foi configurado.';
                return;
            }

            const subject = `[GeekHost] ${formData.get('subject')}`;
            const body = [
                `Nome: ${formData.get('name')}`,
                `E-mail para resposta: ${formData.get('email')}`,
                '',
                String(formData.get('message')),
            ].join('\n');
            window.location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            status.textContent = 'Seu aplicativo de e-mail deve abrir com a mensagem pronta.';
        });
    }

    const generatorForm = document.querySelector('[data-generator-form]');
    if (generatorForm) {
        const countryCodeInput = document.querySelector('#country-code');
        const phoneInput = document.querySelector('#phone-number');
        const messageInput = document.querySelector('#whatsapp-message');
        const status = document.querySelector('[data-generator-status]');
        const qrContainer = document.querySelector('[data-qr-code]');
        const qrPlaceholder = document.querySelector('[data-qr-placeholder]');
        const qrError = document.querySelector('[data-qr-error]');
        const resultHint = document.querySelector('[data-result-hint]');
        const resultWrap = document.querySelector('[data-generated-link-wrap]');
        const generatedLink = document.querySelector('[data-generated-link]');
        const openWhatsapp = document.querySelector('[data-open-whatsapp]');
        const copyButton = document.querySelector('[data-copy-link]');
        const downloadButton = document.querySelector('[data-download-qr]');
        const hostingDialog = document.querySelector('[data-hosting-dialog]');
        const dialogContinue = document.querySelector('[data-dialog-continue]');
        const dialogClose = document.querySelector('[data-dialog-close]');
        let pendingLink = '';

        const generateResult = (link) => {
            generatedLink.value = link;
            openWhatsapp.href = link;
            resultWrap.hidden = false;
            resultHint.hidden = true;
            qrError.hidden = true;
            status.textContent = 'Link criado. Confira o resultado e teste antes de divulgar.';

            if (typeof window.QRCode !== 'function') {
                qrPlaceholder.hidden = true;
                qrError.hidden = false;
                return;
            }

            qrPlaceholder.hidden = true;
            qrContainer.hidden = false;
            qrContainer.replaceChildren();
            new window.QRCode(qrContainer, {
                text: link,
                width: 220,
                height: 220,
                colorDark: '#1a1e24',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.H,
            });
        };

        generatorForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const countryCode = countryCodeInput.value.replace(/\D/g, '');
            const phone = phoneInput.value.replace(/\D/g, '');
            const message = messageInput.value.trim();

            if (countryCode.length < 1 || countryCode.length > 3 || phone.length < 4 || `${countryCode}${phone}`.length > 15) {
                status.textContent = 'Informe um código de país e um número com DDD válidos.';
                phoneInput.focus();
                return;
            }

            const fullNumber = `${countryCode}${phone}`;
            const query = message ? `?text=${encodeURIComponent(message)}` : '';
            const link = `https://wa.me/${fullNumber}${query}`;

            if (hostingDialog && typeof hostingDialog.showModal === 'function') {
                pendingLink = link;
                status.textContent = 'Escolha se deseja conhecer os planos ou continuar com o gerador gratuito.';
                hostingDialog.showModal();
                return;
            }

            generateResult(link);
        });

        if (hostingDialog && dialogContinue) {
            dialogContinue.addEventListener('click', () => {
                const link = pendingLink;
                pendingLink = '';
                hostingDialog.close();
                if (link) generateResult(link);
            });

            hostingDialog.addEventListener('close', () => {
                pendingLink = '';
            });
        }

        if (hostingDialog && dialogClose) {
            dialogClose.addEventListener('click', () => hostingDialog.close());
            hostingDialog.addEventListener('click', (event) => {
                if (event.target === hostingDialog) hostingDialog.close();
            });
        }

        copyButton.addEventListener('click', async () => {
            if (!generatedLink.value) return;
            try {
                await navigator.clipboard.writeText(generatedLink.value);
                copyButton.textContent = 'Copiado';
                status.textContent = 'Link copiado para a área de transferência.';
            } catch {
                generatedLink.select();
                status.textContent = 'Selecione e copie o link manualmente.';
            }
        });

        downloadButton.addEventListener('click', () => {
            const qrImage = qrContainer.querySelector('img');
            const qrCanvas = qrContainer.querySelector('canvas');
            const downloadUrl = qrCanvas ? qrCanvas.toDataURL('image/png') : qrImage?.src;

            if (!downloadUrl) {
                status.textContent = 'Gere um QR code antes de baixar.';
                return;
            }

            const downloadLink = document.createElement('a');
            downloadLink.href = downloadUrl;
            downloadLink.download = 'qr-code-whatsapp.png';
            downloadLink.click();
        });
    }
});