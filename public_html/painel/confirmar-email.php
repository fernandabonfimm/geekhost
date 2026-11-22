<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auth = new Auth();
$email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($next !== 'admin') {
    $next = '';
}
$error = '';
$notice = $email !== '' ? 'Enviamos um código de 6 dígitos para confirmar este e-mail.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $action = (string) ($_POST['action'] ?? 'confirm');
        try {
            if ($action === 'resend') {
                $auth->resendEmailCode($email);
                $notice = 'Se houver um cadastro pendente, reenviamos o código. Aguarde um minuto entre os envios.';
            } else {
                $auth->confirmEmailCode($email, (string) ($_POST['code'] ?? ''));
                header('Location: ' . url($next === 'admin' ? '/admin/' : '/painel/'));
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$requireAuth = false;
$panelTitle = 'Confirmar e-mail';
require __DIR__ . '/_layout.php';
?>
<section class="panel-auth">
    <p class="panel-eyebrow">Confirmação de e-mail</p>
    <h1>Digite o código</h1>
    <p>O login não pede código. Este passo só confirma um cadastro novo ou a troca de e-mail.</p>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
    <?php if ($notice): ?><div class="gh-alert"><div><?= e($notice) ?></div></div><?php endif; ?>
    <form method="post" class="panel-form" style="padding:0;border:0;box-shadow:none;background:transparent;max-width:none">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>E-mail<input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>"></label>
        <label>Código de 6 dígitos<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
        <button class="gh-btn gh-btn-primary" type="submit" name="action" value="confirm">Confirmar e-mail</button>
    </form>
    <form method="post" class="panel-auth-links">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <button class="gh-btn" type="submit" name="action" value="resend">Reenviar código</button>
        <a href="<?= e(url($next === 'admin' ? '/admin/login' : '/painel/login')) ?>">Voltar ao login</a>
    </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
