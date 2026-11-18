<?php

declare(strict_types=1);

$panelTitle = 'Faturas';
$panelSection = 'faturas';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$allowed = ['pago', 'em_aberto', 'cancelado', 'reembolsado'];
if ($status !== '' && !in_array($status, $allowed, true)) {
    $status = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
    $from = '';
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
    $to = '';
}

$orders = new OrderService();
$counts = $orders->invoiceCounts((int) $panelUser['id']);
$invoices = $orders->listInvoicesForUser((int) $panelUser['id'], null, [
    'status' => $status !== '' ? $status : null,
    'q' => $q !== '' ? $q : null,
    'from' => $from !== '' ? $from : null,
    'to' => $to !== '' ? $to : null,
]);

$fmtDate = static function (?string $iso): string {
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : '—';
};

$billingLabel = static function (string $type): string {
    return match (strtoupper($type)) {
        'PIX' => 'Pix',
        'BOLETO' => 'Boleto',
        'CREDIT_CARD' => 'Cartão',
        default => $type,
    };
};
?>
<section class="panel-invoices" data-invoices data-csrf="<?= e($csrf) ?>">
    <div class="panel-invoices-head">
        <div>
            <p class="panel-lead"><?= (int) $counts['total'] ?> faturas no total · <?= (int) $counts['em_aberto'] ?> em aberto</p>
        </div>
    </div>

    <form class="panel-filter-bar" method="get" action="<?= e(url('/painel/faturas')) ?>">
        <label class="panel-filter-field panel-filter-grow">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nº, plano, domínio…">
        </label>
        <label class="panel-filter-field">
            <span>De</span>
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label class="panel-filter-field">
            <span>Até</span>
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <label class="panel-filter-field">
            <span>Status</span>
            <select name="status">
                <option value="">Todos</option>
                <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago (<?= (int) $counts['pago'] ?>)</option>
                <option value="em_aberto" <?= $status === 'em_aberto' ? 'selected' : '' ?>>Em aberto (<?= (int) $counts['em_aberto'] ?>)</option>
                <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado (<?= (int) $counts['cancelado'] ?>)</option>
                <option value="reembolsado" <?= $status === 'reembolsado' ? 'selected' : '' ?>>Reembolsado (<?= (int) $counts['reembolsado'] ?>)</option>
            </select>
        </label>
        <div class="panel-filter-actions">
            <button class="button button-lime" type="submit">Filtrar</button>
            <a class="button button-outline-dark" href="<?= e(url('/painel/faturas')) ?>">Limpar</a>
        </div>
    </form>

    <div class="panel-table-wrap">
        <table class="panel-table">
            <thead>
            <tr>
                <th>Fatura nº</th>
                <th>Data</th>
                <th>Vencimento</th>
                <th>Total</th>
                <th>Pago em</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr><td colspan="7">Nenhuma fatura encontrada com esses filtros.</td></tr>
            <?php else: foreach ($invoices as $invoice):
                $payload = $invoice;
                $payload['billingTypeLabel'] = $billingLabel((string) ($invoice['billingType'] ?? ''));
                $payload['createdAtLabel'] = $fmtDate($invoice['createdAt'] ?? null);
                $payload['dueDateLabel'] = $fmtDate($invoice['dueDate'] ?? null);
                $payload['paidAtLabel'] = $fmtDate($invoice['paidAt'] ?? null);
                ?>
                <tr>
                    <td>#<?= e((string) ($invoice['invoiceNumber'] ?? '—')) ?></td>
                    <td><?= e($fmtDate($invoice['createdAt'] ?? null)) ?></td>
                    <td><?= e($fmtDate($invoice['dueDate'] ?? null)) ?></td>
                    <td><?= e($invoice['amountFormatted']) ?></td>
                    <td><?= e($fmtDate($invoice['paidAt'] ?? null)) ?></td>
                    <td><span class="panel-badge panel-badge-<?= e(preg_replace('/\s+/', '_', strtolower($invoice['statusLabel']))) ?>"><?= e($invoice['statusLabel']) ?></span></td>
                    <td><button type="button" class="button button-outline-dark panel-view-btn" data-view-invoice='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>Ver</button></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<aside class="invoice-drawer" data-invoice-drawer hidden>
    <div class="invoice-drawer-backdrop" data-drawer-close></div>
    <div class="invoice-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="invoice-drawer-title">
        <button type="button" class="invoice-drawer-close" data-drawer-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
        <p class="eyebrow">Detalhe</p>
        <h2 id="invoice-drawer-title">Fatura</h2>
        <div data-drawer-body></div>
    </div>
</aside>
<?php require __DIR__ . '/_footer.php'; ?>
