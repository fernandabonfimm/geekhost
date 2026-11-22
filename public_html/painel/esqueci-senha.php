<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auth = new Auth();
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$step = (string) ($_POST['step'] ?? 'email');
$error = '';
$sent = $step === 'code';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
        $sent = false;
    } elseif ($step === 'reset') {
        try {
            $auth->resetPasswordWithCode(
                $email,
                (string) ($_POST['code'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );
            header('Location: ' . url('/painel/'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $sent = true;
        }
    } else {
        $auth->requestPasswordReset($email);
        $sent = true;
    }
}

$requireAuth = false;
$panelTitle = 'Esqueci minha senha';
require __DIR__ . '/_layout.php';
?>
<section class="auth-panel">
    <p class="auth-kicker">Área do cliente</p>
    <h1>Recupere o acesso.</h1>
    <p class="auth-lead">Enviamos um código de 6 dígitos. Ele vale por 20 minutos.</p>
    <?php if ($error): ?><div class="auth-alert is-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$sent): ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="step" value="code">
            <div>
                <div class="auth-label-row"><span>Endereço de e-mail</span></div>
                <label class="auth-field">
                    <i data-lucide="mail"></i>
                    <input type="email" name="email" required autocomplete="username" placeholder="seu@email.com">
                </label>
            </div>
            <button class="auth-submit" type="submit">Enviar código <i data-lucide="arrow-right"></i></button>
        </form>
    <?php else: ?>
        <div class="auth-alert">Se este e-mail tiver uma conta, o código já foi enviado.</div>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="step" value="reset">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <div>
                <div class="auth-label-row"><span>Código</span></div>
                <label class="auth-field"><i data-lucide="key-round"></i><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000"></label>
            </div>
            <div>
                <div class="auth-label-row"><span>Nova senha</span></div>
                <label class="auth-field"><i data-lucide="lock"></i><?php geekhost_password_input('password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'placeholder' => 'Mínimo de 8 caracteres']); ?></label>
            </div>
            <div>
                <div class="auth-label-row"><span>Confirmar senha</span></div>
                <label class="auth-field"><i data-lucide="shield-check"></i><?php geekhost_password_input('password_confirm', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'placeholder' => 'Repita a senha']); ?></label>
            </div>
            <button class="auth-submit" type="submit">Salvar nova senha <i data-lucide="arrow-right"></i></button>
        </form>
    <?php endif; ?>
    <p class="auth-switch"><a href="<?= e(url('/painel/login')) ?>">Voltar ao login</a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
