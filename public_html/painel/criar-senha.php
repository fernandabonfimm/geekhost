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
            header('Location: ' . url('/painel/faturas'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $authUser = $auth->findUserBySetupToken($token);
            $paid = $authUser ? $auth->userHasPaidOrder((int) $authUser['id']) : false;
        }
    }
}
?>
<section class="panel-auth max-w-md mx-auto border border-[var(--panel-line)] bg-white p-7 shadow-[0_18px_40px_rgba(26,30,36,.08)]">
    <p class="eyebrow !text-[11px] !tracking-[.08em]">GeekHost</p>
    <h1 class="!mt-1 !mb-2 text-[28px] font-extrabold text-[var(--panel-grey)]">Criar senha do painel</h1>
    <?php if (!$authUser): ?>
        <p class="panel-alert panel-alert-danger">Link inválido ou expirado. Conclua um novo checkout ou fale com o suporte.</p>
        <a class="button button-lime mt-3 inline-flex" href="<?= e(url('/painel/login')) ?>">Ir para login</a>
    <?php elseif (!$paid): ?>
        <p class="panel-lead">Conta encontrada: <strong><?= e((string) $authUser['email']) ?></strong></p>
        <p class="panel-alert panel-alert-danger">Aguardando confirmação do pagamento. Assim que o Pix/boleto/cartão for confirmado, volte neste link para criar a senha.</p>
        <meta http-equiv="refresh" content="12">
    <?php else: ?>
        <p class="panel-lead">Pagamento confirmado. Defina a senha de acesso para <strong><?= e((string) $authUser['email']) ?></strong>.</p>
        <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="mt-4 grid gap-3">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label class="grid gap-1 text-sm font-bold">Senha
                <input class="min-h-11 border border-[var(--panel-line)] px-3" type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label class="grid gap-1 text-sm font-bold">Confirmar senha
                <input class="min-h-11 border border-[var(--panel-line)] px-3" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button class="button button-lime" type="submit">Salvar senha e entrar</button>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
