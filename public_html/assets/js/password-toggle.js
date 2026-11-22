(() => {
    const bind = (button) => {
        if (!(button instanceof HTMLButtonElement) || button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        const control = button.closest('.gh-pass-control');
        const input = control ? control.querySelector('input') : null;
        const showIcon = button.querySelector('.gh-pass-icon-show');
        const hideIcon = button.querySelector('.gh-pass-icon-hide');
        if (!(input instanceof HTMLInputElement)) return;

        button.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
            button.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
            if (showIcon instanceof SVGElement) showIcon.hidden = !visible;
            if (hideIcon instanceof SVGElement) hideIcon.hidden = visible;
        });
    };

    document.querySelectorAll('[data-password-toggle]').forEach(bind);
})();
