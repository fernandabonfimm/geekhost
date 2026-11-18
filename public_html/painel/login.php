<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Entrar';
require __DIR__ . '/_layout.php';

if ($panelUser) {
    header('Location: /painel/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $user = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($user) {
            header('Location: /painel/faturas');
            exit;
        }
        $error = 'E-mail ou senha inválidos.';
    }
}
?>
<section class="panel-auth">
    <h1>Entrar no painel</h1>
    <p>Use o e-mail e a senha criados no checkout. A sessão dura 7 horas.</p>
    <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="panel-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>E-mail<input type="email" name="email" required autocomplete="username"></label>
        <label>Senha<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button button-lime" type="submit">Entrar</button>
    </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
