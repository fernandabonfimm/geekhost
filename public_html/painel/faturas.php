<?php

declare(strict_types=1);

$panelTitle = 'Faturas';
$panelSection = 'faturas';
$panelScripts = ['assets/js/panel.js'];
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';

$filter = isset($_GET['status']) ? (string) $_GET['status'] : null;
$allowed = ['pago', 'em_aberto', 'cancelado', 'reembolsado'];
if ($filter !== null && !in_array($filter, $allowed, true)) {
    $filter = null;
}

$orders = new OrderService();
$counts = $orders->invoiceCounts((int) $panelUser['id']);
$invoices = $orders->listInvoicesForUser((int) $panelUser['id'], $filter);

$fmtDate = static function (?string $iso): string {
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : '—';
};
?>
<section class="panel-invoices" data-invoices data-csrf="<?= e($csrf) ?>">
    <div class="panel-invoices-head">
        <div>
            <h1>Faturas</h1>
            <p><?= (int) $counts['total'] ?> faturas, <?= (int) $counts['em_aberto'] ?> em aberto</p>
        </div>
    </div>

    <div class="panel-filters" role="tablist">
        <?php
        $tabs = [
            '' => ['Todos', $counts['total']],
            'pago' => ['Pago', $counts['pago']],
            'em_aberto' => ['Em aberto', $counts['em_aberto']],
            'cancelado' => ['Cancelado', $counts['cancelado']],
            'reembolsado' => ['Reembolsado', $counts['reembolsado']],
        ];
        foreach ($tabs as $key => [$label, $count]):
            $href = $key === '' ? '/painel/faturas' : '/painel/faturas?status=' . urlencode($key);
            $active = ($filter ?? '') === $key;
            ?>
            <a class="panel-filter <?= $active ? 'is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?> (<?= (int) $count ?>)</a>
        <?php endforeach; ?>
    </div>

    <div class="panel-table-wrap">
        <table class="panel-table">
            <thead>
            <tr>
                <th>Fatura nº</th>
                <th>Data da fatura</th>
                <th>Vencimento</th>
                <th>Total</th>
                <th>Pago em</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr><td colspan="7">Nenhuma fatura neste filtro.</td></tr>
            <?php else: foreach ($invoices as $invoice): ?>
                <tr>
                    <td>#<?= e((string) ($invoice['invoiceNumber'] ?? '—')) ?></td>
                    <td><?= e($fmtDate($invoice['createdAt'] ?? null)) ?></td>
                    <td><?= e($fmtDate($invoice['dueDate'] ?? null)) ?></td>
                    <td><?= e($invoice['amountFormatted']) ?></td>
                    <td><?= e($fmtDate($invoice['paidAt'] ?? null)) ?></td>
                    <td><span class="panel-badge panel-badge-<?= e(preg_replace('/\s+/', '_', strtolower($invoice['statusLabel']))) ?>"><?= e($invoice['statusLabel']) ?></span></td>
                    <td><button type="button" class="button button-outline-dark panel-view-btn" data-view-invoice='<?= e(json_encode($invoice, JSON_UNESCAPED_UNICODE)) ?>'>Ver</button></td>
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
        <h2 id="invoice-drawer-title">Detalhes da fatura</h2>
        <div data-drawer-body></div>
    </div>
</aside>
<?php require __DIR__ . '/_footer.php'; ?>
