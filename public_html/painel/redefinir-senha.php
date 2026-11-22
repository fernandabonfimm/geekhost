<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Nova senha';
require __DIR__ . '/_layout.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        try {
            $auth->resetPassword($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
            header('Location: ' . url('/painel/'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<section class="panel-auth">
    <p class="panel-eyebrow">Área do cliente</p>
    <h1>Criar nova senha</h1>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" class="panel-form" style="padding:0;border:0;box-shadow:none;background:transparent;max-width:none">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nova senha<?php geekhost_password_input('password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']); ?></label>
        <label>Confirmar senha<?php geekhost_password_input('password_confirm', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']); ?></label>
        <button class="gh-btn gh-btn-primary" type="submit">Salvar senha</button>
    </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
