<?php

declare(strict_types=1);

$requireAdminAuth = false;
$adminTitle = 'Entrar no Admin';
require __DIR__ . '/_layout.php';

$auth = $adminGate->auth();
if ($auth->user()) {
    try {
        $adminGate->requireAdmin();
        header('Location: ' . url('/admin/'));
        exit;
    } catch (Throwable $e) {
        // logged in as client only
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $user = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($user) {
            try {
                $adminGate->requireAdmin();
                header('Location: ' . url('/admin/'));
                exit;
            } catch (Throwable $e) {
                $error = 'Esta conta não tem acesso ao Admin GeekHost. O painel do cliente é /painel.';
            }
        } else {
            $error = 'E-mail ou senha inválidos.';
        }
    }
}
?>
<section class="admin-auth">
    <div class="admin-auth-card">
        <img src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="140" height="40">
        <h1>Admin GeekHost</h1>
        <p>Painel operacional da revenda (DirectAdmin <code>geek6923</code>). Não é o painel do cliente.</p>
        <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label>E-mail staff<input type="email" name="email" required autocomplete="username"></label>
            <label>Senha<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="gh-btn gh-btn-primary" type="submit">Entrar no admin</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
