<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Criar senha';
require __DIR__ . '/_layout.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$message = '';
$authUser = $auth->findUserBySetupToken($token);
$paid = $authUser ? $auth->userHasPaidOrder((int) $authUser['id']) : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Recarregue a página.';
    } else {
        try {
            $auth->completePasswordSetup(
                (string) ($_POST['token'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );
            header('Location: ' . url('/painel/'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $authUser = $auth->findUserBySetupToken($token);
            $paid = $authUser ? $auth->userHasPaidOrder((int) $authUser['id']) : false;
        }
    }
}
?>
<section class="panel-auth">
    <img src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="140" height="40" style="margin-bottom:18px">
    <p class="panel-eyebrow">GeekHost</p>
    <h1>Criar senha do painel</h1>
    <?php if (!$authUser): ?>
        <div class="gh-alert gh-alert-danger"><div>Link inválido ou expirado. Conclua um novo checkout ou fale com o suporte.</div></div>
        <a class="gh-btn gh-btn-primary" href="<?= e(url('/painel/login')) ?>">Ir para login</a>
    <?php elseif (!$paid): ?>
        <div class="gh-alert gh-alert-warn"><div>Aguarde a confirmação do pagamento para liberar a criação de senha.</div></div>
    <?php else: ?>
        <p>Defina a senha de acesso à área do cliente.</p>
        <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
        <form method="post" class="panel-form" style="padding:0;border:0;box-shadow:none;background:transparent;max-width:none">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>Nova senha<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirmar senha<input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"></label>
            <button class="gh-btn gh-btn-primary" type="submit">Criar senha</button>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
