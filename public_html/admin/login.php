<?php

declare(strict_types=1);

$requireAdminAuth = false;
$adminTitle = 'Entrar no Admin';

try {
    require __DIR__ . '/_layout.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Erro Admin</title>';
    echo '<body style="font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px">';
    echo '<h1>Erro ao abrir o Admin</h1>';
    echo '<p style="color:#b42318"><code>' . $msg . '</code></p>';
    echo '<p>Se a mensagem falar em schema missing: suba <code>public_html/database/schema.sql</code> ou rode o install-db (tabelas já criadas).</p>';
    echo '<p>Se o admin seed falhou: abra <code>/fix-admin.php?key=SUA_CHAVE</code>.</p>';
    echo '</body></html>';
    exit;
}

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
