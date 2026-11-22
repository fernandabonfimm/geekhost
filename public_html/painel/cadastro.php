<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Cadastro';
require __DIR__ . '/_layout.php';

if ($panelUser) {
    header('Location: ' . url('/painel/'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        try {
            if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirm'] ?? '')) {
                throw new InvalidArgumentException('A confirmação de senha não confere.');
            }
            $first = trim((string) ($_POST['first_name'] ?? ''));
            $last = trim((string) ($_POST['last_name'] ?? ''));
            $name = trim($first . ' ' . $last);
            if ($name === '') {
                $name = trim((string) ($_POST['name'] ?? ''));
            }
            $created = $auth->registerAccount(
                $name,
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                [
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'mobilePhone' => (string) ($_POST['phone'] ?? ''),
                    'postalCode' => (string) ($_POST['postal_code'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                    'addressNumber' => (string) ($_POST['address_number'] ?? ''),
                    'complement' => trim((string) ($_POST['district'] ?? '') . (((string) ($_POST['company'] ?? '')) !== '' ? ' · ' . trim((string) $_POST['company']) : '')),
                    'province' => (string) ($_POST['province'] ?? ''),
                    'city' => (string) ($_POST['city'] ?? ''),
                ]
            );
            header('Location: ' . url('/painel/confirmar-email?email=' . rawurlencode((string) $created['email'])));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<section class="auth-panel">
    <p class="auth-kicker">Área do cliente</p>
    <h1>Crie sua conta.</h1>
    <p class="auth-lead">Três passos curtos. Você confirma o e-mail no final.</p>
    <?php if ($error): ?><div class="auth-alert is-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="auth-progress" data-auth-progress>
        <span class="is-on"></span><span></span><span></span>
    </div>
    <form method="post" class="auth-form" data-auth-steps>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="auth-step" data-auth-step="1">
            <div class="auth-grid">
                <div>
                    <div class="auth-label-row"><span>Primeiro nome</span></div>
                    <label class="auth-field"><i data-lucide="user"></i><input name="first_name" required autocomplete="given-name" value="<?= e((string) ($_POST['first_name'] ?? '')) ?>"></label>
                </div>
                <div>
                    <div class="auth-label-row"><span>Sobrenome</span></div>
                    <label class="auth-field"><i data-lucide="user"></i><input name="last_name" required autocomplete="family-name" value="<?= e((string) ($_POST['last_name'] ?? '')) ?>"></label>
                </div>
            </div>
            <div>
                <div class="auth-label-row"><span>Endereço de e-mail</span></div>
                <label class="auth-field"><i data-lucide="mail"></i><input type="email" name="email" required autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="seu@email.com"></label>
            </div>
            <div>
                <div class="auth-label-row"><span>Celular</span></div>
                <label class="auth-field"><i data-lucide="phone"></i><input name="phone" required inputmode="tel" autocomplete="tel" placeholder="(16) 99999-0000" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></label>
            </div>
            <button class="auth-submit" type="button" data-auth-next>Continuar <i data-lucide="arrow-right"></i></button>
        </div>
        <div class="auth-step" data-auth-step="2" hidden>
            <div class="auth-grid">
                <div>
                    <div class="auth-label-row"><span>Empresa (opcional)</span></div>
                    <label class="auth-field"><i data-lucide="building-2"></i><input name="company" value="<?= e((string) ($_POST['company'] ?? '')) ?>"></label>
                </div>
                <div>
                    <div class="auth-label-row"><span>CEP</span></div>
                    <label class="auth-field"><i data-lucide="map-pin"></i><input name="postal_code" required inputmode="numeric" maxlength="9" data-cep value="<?= e((string) ($_POST['postal_code'] ?? '')) ?>"></label>
                </div>
            </div>
            <p class="auth-cep-status" data-cep-status></p>
            <div>
                <div class="auth-label-row"><span>Endereço</span></div>
                <label class="auth-field"><i data-lucide="map"></i><input name="address" required data-cep-street value="<?= e((string) ($_POST['address'] ?? '')) ?>"></label>
            </div>
            <div class="auth-grid">
                <div>
                    <div class="auth-label-row"><span>Número</span></div>
                    <label class="auth-field"><i data-lucide="hash"></i><input name="address_number" required value="<?= e((string) ($_POST['address_number'] ?? '')) ?>"></label>
                </div>
                <div>
                    <div class="auth-label-row"><span>Bairro</span></div>
                    <label class="auth-field"><i data-lucide="signpost"></i><input name="district" data-cep-district value="<?= e((string) ($_POST['district'] ?? '')) ?>"></label>
                </div>
            </div>
            <div class="auth-grid">
                <div>
                    <div class="auth-label-row"><span>Cidade</span></div>
                    <label class="auth-field"><i data-lucide="building"></i><input name="city" required data-cep-city value="<?= e((string) ($_POST['city'] ?? '')) ?>"></label>
                </div>
                <div>
                    <div class="auth-label-row"><span>UF</span></div>
                    <label class="auth-field"><i data-lucide="flag"></i><input name="province" required maxlength="2" data-cep-uf value="<?= e((string) ($_POST['province'] ?? '')) ?>"></label>
                </div>
            </div>
            <div class="auth-actions">
                <button class="auth-ghost" type="button" data-auth-back>Voltar</button>
                <button class="auth-submit" type="button" data-auth-next>Continuar <i data-lucide="arrow-right"></i></button>
            </div>
        </div>
        <div class="auth-step" data-auth-step="3" hidden>
            <div>
                <div class="auth-label-row"><span>Senha</span></div>
                <label class="auth-field"><i data-lucide="lock"></i><?php geekhost_password_input('password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'placeholder' => 'Mínimo de 8 caracteres']); ?></label>
            </div>
            <div>
                <div class="auth-label-row"><span>Confirmar senha</span></div>
                <label class="auth-field"><i data-lucide="shield-check"></i><?php geekhost_password_input('password_confirm', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'placeholder' => 'Repita a senha']); ?></label>
            </div>
            <div class="auth-actions">
                <button class="auth-ghost" type="button" data-auth-back>Voltar</button>
                <button class="auth-submit" type="submit">Criar conta <i data-lucide="arrow-right"></i></button>
            </div>
        </div>
    </form>
    <p class="auth-switch">Já tem conta? <a href="<?= e(url('/painel/login')) ?>">Entrar</a></p>
</section>
<script>
(() => {
    const form = document.querySelector('[data-auth-steps]');
    if (!form) return;
    const steps = [...form.querySelectorAll('[data-auth-step]')];
    const bars = [...document.querySelectorAll('[data-auth-progress] span')];
    let index = 0;
    const show = (next) => {
        index = next;
        steps.forEach((step, i) => { step.hidden = i !== index; });
        bars.forEach((bar, i) => bar.classList.toggle('is-on', i <= index));
        if (window.lucide) window.lucide.createIcons();
    };
    const valid = () => [...steps[index].querySelectorAll('input')].every((input) => input.reportValidity());
    form.querySelectorAll('[data-auth-next]').forEach((button) => {
        button.addEventListener('click', () => { if (valid()) show(Math.min(index + 1, steps.length - 1)); });
    });
    form.querySelectorAll('[data-auth-back]').forEach((button) => {
        button.addEventListener('click', () => show(Math.max(index - 1, 0)));
    });
    const cep = form.querySelector('[data-cep]');
    const status = document.querySelector('[data-cep-status]');
    cep?.addEventListener('blur', async () => {
        const digits = (cep.value || '').replace(/\D/g, '');
        if (digits.length !== 8) return;
        if (status) status.textContent = 'Buscando CEP…';
        try {
            const response = await fetch('https://viacep.com.br/ws/' + digits + '/json/');
            const data = await response.json();
            if (data.erro) throw new Error('CEP não encontrado');
            const set = (sel, value) => { const el = form.querySelector(sel); if (el && value) el.value = value; };
            set('[data-cep-street]', data.logradouro || '');
            set('[data-cep-district]', data.bairro || '');
            set('[data-cep-city]', data.localidade || '');
            set('[data-cep-uf]', data.uf || '');
            if (status) status.textContent = 'Endereço preenchido.';
        } catch (error) {
            if (status) status.textContent = 'Não achamos esse CEP. Preencha o endereço.';
        }
    });
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
