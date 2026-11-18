<?php

declare(strict_types=1);

$panelTitle = 'Faturas';
$panelSection = 'faturas';
$panelLead = 'Acompanhe pagamentos, vencimentos e comprovantes Asaas.';
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
$allInvoices = $orders->listInvoicesForUser((int) $panelUser['id']);
$invoices = $orders->listInvoicesForUser((int) $panelUser['id'], null, [
    'status' => $status !== '' ? $status : null,
    'q' => $q !== '' ? $q : null,
    'from' => $from !== '' ? $from : null,
    'to' => $to !== '' ? $to : null,
]);

$openAmount = 0.0;
$paidAmount = 0.0;
$overdueAmount = 0.0;
foreach ($allInvoices as $inv) {
    $amount = (float) ($inv['amount'] ?? 0);
    $st = (string) ($inv['status'] ?? '');
    if (in_array($st, ['awaiting_payment', 'overdue'], true)) {
        $openAmount += $amount;
        if ($st === 'overdue') {
            $overdueAmount += $amount;
        }
    }
    if ($st === 'paid_pending_provisioning') {
        $paidAmount += $amount;
    }
}

$fmtDate = static function (?string $iso): string {
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : '—';
};

$fmtMoney = static function (float $n): string {
    return 'R$ ' . number_format($n, 2, ',', '.');
};

$billingLabel = static function (string $type): string {
    return match (strtoupper($type)) {
        'PIX' => 'Pix',
        'BOLETO' => 'Boleto',
        'CREDIT_CARD' => 'Cartão',
        default => $type,
    };
};

$badgeClass = static function (string $label): string {
    $key = preg_replace('/\s+/', '_', strtolower($label)) ?? '';
    return match ($key) {
        'pago' => 'ok',
        'em_aberto' => 'warn',
        default => 'muted',
    };
};
?>
<section class="panel-invoices" data-invoices data-csrf="<?= e($csrf) ?>">
    <div class="invoice-kpi-grid">
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top">
                <span>Total em aberto</span>
                <span class="invoice-kpi-icon is-warn"><i data-lucide="circle-dollar-sign"></i></span>
            </div>
            <strong><?= e($fmtMoney($openAmount)) ?></strong>
        </article>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top">
                <span>Total pago</span>
                <span class="invoice-kpi-icon is-ok"><i data-lucide="badge-check"></i></span>
            </div>
            <strong><?= e($fmtMoney($paidAmount)) ?></strong>
        </article>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top">
                <span>Faturas vencidas</span>
                <span class="invoice-kpi-icon is-danger"><i data-lucide="triangle-alert"></i></span>
            </div>
            <strong><?= e($fmtMoney($overdueAmount)) ?></strong>
        </article>
    </div>

    <form class="panel-filter-bar" method="get" action="<?= e(url('/painel/faturas')) ?>">
        <label class="panel-filter-field">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nº, plano, domínio…">
        </label>
        <label class="panel-filter-field">
            <span>Status</span>
            <select name="status">
                <option value="">Todos os status</option>
                <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago (<?= (int) $counts['pago'] ?>)</option>
                <option value="em_aberto" <?= $status === 'em_aberto' ? 'selected' : '' ?>>Em aberto (<?= (int) $counts['em_aberto'] ?>)</option>
                <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado (<?= (int) $counts['cancelado'] ?>)</option>
                <option value="reembolsado" <?= $status === 'reembolsado' ? 'selected' : '' ?>>Reembolsado (<?= (int) $counts['reembolsado'] ?>)</option>
            </select>
        </label>
        <div class="panel-filter-actions">
            <input type="hidden" name="from" value="<?= e($from) ?>">
            <input type="hidden" name="to" value="<?= e($to) ?>">
            <button class="gh-btn gh-btn-primary" type="submit">Filtrar</button>
            <a class="gh-btn gh-btn-secondary" href="<?= e(url('/painel/faturas')) ?>">Limpar</a>
        </div>
    </form>

    <div class="panel-table-wrap">
        <table class="panel-table">
            <thead>
            <tr>
                <th>Fatura nº</th>
                <th>Data</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr><td colspan="6">Nenhuma fatura encontrada com esses filtros.</td></tr>
            <?php else: foreach ($invoices as $invoice):
                $payload = $invoice;
                $payload['billingTypeLabel'] = $billingLabel((string) ($invoice['billingType'] ?? ''));
                $payload['createdAtLabel'] = $fmtDate($invoice['createdAt'] ?? null);
                $payload['dueDateLabel'] = $fmtDate($invoice['dueDate'] ?? null);
                $payload['paidAtLabel'] = $fmtDate($invoice['paidAt'] ?? null);
                $tone = $badgeClass((string) ($invoice['statusLabel'] ?? ''));
                ?>
                <tr>
                    <td><strong>#<?= e((string) ($invoice['invoiceNumber'] ?? '—')) ?></strong></td>
                    <td><?= e($fmtDate($invoice['createdAt'] ?? null)) ?></td>
                    <td><?= e($fmtDate($invoice['dueDate'] ?? null)) ?></td>
                    <td><?= e($invoice['amountFormatted']) ?></td>
                    <td><span class="gh-badge gh-badge-<?= e($tone) ?> gh-badge-dot"><?= e($invoice['statusLabel']) ?></span></td>
                    <td>
                        <div class="domain-actions-inline">
                            <button type="button" class="gh-btn gh-btn-secondary gh-btn-sm" data-view-invoice='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>Ver</button>
                            <?php if (!empty($invoice['invoiceUrl'])): ?>
                                <a class="gh-btn gh-btn-ghost gh-btn-sm" href="<?= e((string) $invoice['invoiceUrl']) ?>" target="_blank" rel="noopener noreferrer" title="Abrir no Asaas">
                                    <i data-lucide="download"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<aside class="invoice-drawer" data-invoice-drawer hidden>
    <div class="invoice-drawer-backdrop" data-drawer-close></div>
    <div class="invoice-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="invoice-drawer-title">
        <button type="button" class="invoice-drawer-close" data-drawer-close aria-label="Fechar"><i data-lucide="x"></i></button>
        <p class="eyebrow">Detalhe</p>
        <h2 id="invoice-drawer-title">Fatura</h2>
        <div data-drawer-body></div>
    </div>
</aside>
<?php require __DIR__ . '/_footer.php'; ?>
