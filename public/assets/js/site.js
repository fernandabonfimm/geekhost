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

    const testimonialCarousel = document.querySelector('[data-testimonial-carousel]');
    if (testimonialCarousel) {
        const slides = [...testimonialCarousel.querySelectorAll('[data-testimonial-slide]')];
        const previousButton = testimonialCarousel.querySelector('[data-testimonial-prev]');
        const nextButton = testimonialCarousel.querySelector('[data-testimonial-next]');
        const dots = [...testimonialCarousel.querySelectorAll('[data-testimonial-dot]')];
        const status = testimonialCarousel.querySelector('[data-testimonial-status]');

        if (slides.length > 1 && previousButton && nextButton) {
            let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));

            const showSlide = (index) => {
                activeIndex = (index + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => {
                    const isActive = slideIndex === activeIndex;
                    slide.classList.toggle('is-active', isActive);
                    slide.setAttribute('aria-hidden', String(!isActive));
                    slide.inert = !isActive;
                });
                dots.forEach((dot, dotIndex) => {
                    const isActive = dotIndex === activeIndex;
                    dot.classList.toggle('is-active', isActive);
                    dot.setAttribute('aria-pressed', String(isActive));
                });
                if (status) status.textContent = `Depoimento ${activeIndex + 1} de ${slides.length}`;
            };

            previousButton.addEventListener('click', () => showSlide(activeIndex - 1));
            nextButton.addEventListener('click', () => showSlide(activeIndex + 1));
            dots.forEach((dot) => {
                dot.addEventListener('click', () => showSlide(Number(dot.dataset.testimonialDot)));
            });
            testimonialCarousel.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    showSlide(activeIndex - 1);
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    showSlide(activeIndex + 1);
                }
            });
            showSlide(activeIndex);
        }
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
        const linkExpiryNote = document.querySelector('[data-link-expiry]');
        const openWhatsapp = document.querySelector('[data-open-whatsapp]');
        const copyButton = document.querySelector('[data-copy-link]');
        const downloadButton = document.querySelector('[data-download-qr]');
        const submitButton = generatorForm.querySelector('button[type="submit"]');
        const hostingDialog = document.querySelector('[data-hosting-dialog]');
        const dialogContinue = document.querySelector('[data-dialog-continue]');
        const dialogClose = document.querySelector('[data-dialog-close]');
        let pendingLink = '';

        const generateResult = async (linkData) => {
            submitButton.disabled = true;
            resultWrap.hidden = true;
            resultHint.hidden = false;
            linkExpiryNote.hidden = true;
            qrError.hidden = true;
            qrPlaceholder.hidden = false;
            qrContainer.hidden = true;
            status.textContent = 'Criando seu link temporário...';

            let result;
            try {
                const response = await fetch('criar-link.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(linkData),
                });
                result = await response.json();
                if (!response.ok || typeof result.url !== 'string' || !Number.isInteger(result.expiresAt)) {
                    throw new Error('Link temporário indisponível. Tente novamente.');
                }
            } catch {
                status.textContent = 'Não foi possível criar o link temporário. Tente novamente.';
                submitButton.disabled = false;
                return;
            }

            generatedLink.value = result.url;
            openWhatsapp.href = result.url;
            resultWrap.hidden = false;
            resultHint.hidden = true;
            status.textContent = 'Link criado. Confira e teste antes de divulgar.';
            const expiresAt = new Date(result.expiresAt * 1000);
            const formattedExpiry = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(expiresAt);
            linkExpiryNote.textContent = `Válido até ${formattedExpiry}. Depois desse prazo, os acessos serão direcionados aos planos GeekHost.`;
            linkExpiryNote.hidden = false;

            if (typeof window.QRCode !== 'function') {
                qrPlaceholder.hidden = true;
                qrError.hidden = false;
                submitButton.disabled = false;
                return;
            }

            qrPlaceholder.hidden = true;
            qrContainer.hidden = false;
            qrContainer.replaceChildren();
            new window.QRCode(qrContainer, {
                text: result.url,
                width: 220,
                height: 220,
                colorDark: '#1a1e24',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.H,
            });
            submitButton.disabled = false;
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

            const linkData = { number: `${countryCode}${phone}`, message };

            if (hostingDialog && typeof hostingDialog.showModal === 'function') {
                pendingLink = linkData;
                status.textContent = 'Escolha se deseja conhecer os planos ou continuar com o gerador gratuito.';
                hostingDialog.showModal();
                return;
            }

            generateResult(linkData);
        });

        if (hostingDialog && dialogContinue) {
            dialogContinue.addEventListener('click', () => {
                const linkData = pendingLink;
                pendingLink = '';
                hostingDialog.close();
                if (linkData) generateResult(linkData);
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