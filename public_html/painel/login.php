<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Entrar';
require __DIR__ . '/_layout.php';

$nextRaw = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$nextTarget = geekhost_safe_painel_next($nextRaw, '/painel/');

if ($panelUser) {
    header('Location: ' . url($nextTarget));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $user = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!empty($user['unverified'])) {
            $confirm = url('/painel/confirmar-email?email=' . rawurlencode((string) $user['email']));
            if ($nextTarget !== '/painel/' && $nextTarget !== '') {
                $confirm .= '&next=' . rawurlencode($nextTarget);
            }
            header('Location: ' . $confirm);
            exit;
        }
        if ($user) {
            header('Location: ' . url($nextTarget));
            exit;
        }
        $error = 'E-mail ou senha inválidos.';
    }
}
?>
<section class="auth-panel">
    <p class="auth-kicker">Área do cliente</p>
    <h1>Bem-vindo de volta.</h1>
    <p class="auth-lead">Entre na sua conta para continuar.</p>
    <?php if ($error): ?><div class="auth-alert is-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/painel/login')) ?>" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="next" value="<?= e($nextTarget) ?>">
        <div>
            <div class="auth-label-row"><span>Endereço de e-mail</span></div>
            <label class="auth-field">
                <i data-lucide="mail" aria-hidden="true"></i>
                <input type="email" name="email" required autocomplete="username" placeholder="seu@email.com">
            </label>
        </div>
        <div>
            <div class="auth-label-row">
                <span>Senha</span>
                <a href="<?= e(url('/painel/esqueci-senha')) ?>">Esqueceu a senha?</a>
            </div>
            <label class="auth-field">
                <i data-lucide="lock" aria-hidden="true"></i>
                <?php geekhost_password_input('password', ['required' => true, 'autocomplete' => 'current-password', 'placeholder' => 'Sua senha']); ?>
            </label>
        </div>
        <button class="auth-submit" type="submit">Entrar <i data-lucide="arrow-right" aria-hidden="true"></i></button>
    </form>
    <p class="auth-switch">Ainda não tem conta? <a href="<?= e(url('/painel/cadastro')) ?>">Criar conta</a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
