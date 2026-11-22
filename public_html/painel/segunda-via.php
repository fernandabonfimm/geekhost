<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = '2ª via PIX e boleto';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';

$orders = new OrderService();
$email = strtolower(trim((string) ($_POST['email'] ?? ($panelUser['email'] ?? ''))));
$invoices = [];
$searched = $_SERVER['REQUEST_METHOD'] === 'POST' || !empty($panelUser);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate($_POST['csrf'] ?? null)) {
    $error = 'Sessão expirada. Tente novamente.';
    $searched = false;
} elseif ($searched && $email !== '') {
    $account = $auth->findByEmail($email);
    if ($account) {
        $invoices = $orders->listInvoicesForUser((int) $account['id'], null, ['status' => 'em_aberto']);
    }
}
?>
<section class="panel-auth panel-auth-wide">
    <p class="panel-eyebrow">Área do cliente</p>
    <h1>2ª via PIX e boleto</h1>
    <p>Informe o e-mail da conta para gerar uma nova via das faturas em aberto.</p>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" class="panel-form" style="padding:0;border:0;box-shadow:none;background:transparent;max-width:none">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>E-mail<input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>"></label>
        <button class="gh-btn gh-btn-primary" type="submit">Buscar faturas</button>
    </form>
    <?php if ($searched && $error === ''): ?>
        <?php if ($invoices === []): ?>
            <div class="gh-alert" style="margin-top:16px"><div>Não encontramos faturas em aberto para este e-mail.</div></div>
        <?php else: ?>
            <div class="segunda-via-list">
                <?php foreach ($invoices as $invoice): ?>
                    <article class="segunda-via-card">
                        <div>
                            <strong><?= e((string) ($invoice['planName'] ?? 'Hospedagem')) ?></strong>
                            <p><?= e((string) ($invoice['amountFormatted'] ?? '')) ?> · vencimento <?= e((string) ($invoice['dueDate'] ?: 'a confirmar')) ?></p>
                        </div>
                        <div class="segunda-via-actions">
                            <?php if (!empty($invoice['pix']['copyPaste'])): ?>
                                <button class="gh-btn gh-btn-secondary" type="button" data-copy="<?= e((string) $invoice['pix']['copyPaste']) ?>">Copiar PIX</button>
                            <?php endif; ?>
                            <?php if (!empty($invoice['boleto']['url'])): ?>
                                <a class="gh-btn gh-btn-secondary" href="<?= e((string) $invoice['boleto']['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir boleto</a>
                            <?php endif; ?>
                            <?php if (!empty($invoice['invoiceUrl'])): ?>
                                <a class="gh-btn gh-btn-primary" href="<?= e((string) $invoice['invoiceUrl']) ?>" target="_blank" rel="noopener noreferrer">Pagar</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<script>
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.getAttribute('data-copy') || '');
            button.textContent = 'PIX copiado';
        } catch (error) {
            button.textContent = 'Não foi possível copiar';
        }
    });
});
</script>
<?php require __DIR__ . '/_footer.php'; ?>
