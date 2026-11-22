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
        if (!empty($user['unverified'])) {
            header('Location: ' . url('/painel/confirmar-email?next=admin&email=' . rawurlencode((string) $user['email'])));
            exit;
        }
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
<section class="auth-shell">
    <aside class="auth-visual">
        <div class="auth-stars" aria-hidden="true"></div>
        <div class="auth-orbit" aria-hidden="true"></div>
        <div class="auth-visual-top">
            <div class="auth-wordmark">
                <img src="<?= e(asset('assets/images/favicon.png')) ?>" alt="" width="42" height="42">
                GeekHost
            </div>
        </div>
        <div class="auth-visual-copy">
            <h2>Painel da operação.</h2>
            <p>Cobranças, hospedagens e clientes. Esta entrada não é a área do cliente.</p>
        </div>
    </aside>
    <div class="auth-panel">
        <p class="auth-kicker">Admin</p>
        <h1>Bem-vindo de volta.</h1>
        <p class="auth-lead">Entre com o e-mail de staff para continuar.</p>
        <?php if ($error): ?><div class="auth-alert is-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if (($_GET['senha'] ?? '') === '1'): ?><div class="auth-alert">Senha atualizada. Entre com a nova senha.</div><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/login')) ?>" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div>
                <div class="auth-label-row"><span>E-mail</span></div>
                <label class="auth-field"><i data-lucide="mail"></i><input type="email" name="email" required autocomplete="username" placeholder="seu@email.com"></label>
            </div>
            <div>
                <div class="auth-label-row"><span>Senha</span><a href="<?= e(url('/admin/esqueci-senha')) ?>">Esqueceu a senha?</a></div>
                <label class="auth-field"><i data-lucide="lock"></i><?php geekhost_password_input('password', ['required' => true, 'autocomplete' => 'current-password', 'placeholder' => 'Sua senha']); ?></label>
            </div>
            <button class="auth-submit" type="submit">Entrar <i data-lucide="arrow-right"></i></button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
