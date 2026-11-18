<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Entrar';
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
        $user = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($user) {
            header('Location: ' . url('/painel/'));
            exit;
        }
        $error = 'E-mail ou senha inválidos.';
    }
}
?>
<section class="panel-auth">
    <img src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="140" height="40" style="margin-bottom:18px">
    <h1>Entrar no painel</h1>
    <p>Use o e-mail do checkout e a senha criada após o pagamento.</p>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" class="panel-form" style="padding:0;border:0;box-shadow:none;background:transparent;max-width:none">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>E-mail<input type="email" name="email" required autocomplete="username"></label>
        <label>Senha<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="gh-btn gh-btn-primary" type="submit">Entrar</button>
    </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
