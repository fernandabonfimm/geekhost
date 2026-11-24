<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/csrf.php';
    require_once dirname(__DIR__) . '/includes/Auth.php';
    require_once dirname(__DIR__) . '/includes/AccountBilling.php';
    $auth = new Auth();
    $actor = $auth->user();
    if (!$actor) {
        header('Location: ' . url('/painel/login?next=' . rawurlencode('/painel/faturas')));
        exit;
    }
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        header('Location: ' . url('/painel/faturas?erro=' . rawurlencode('Sessão expirada. Recarregue a página.')));
        exit;
    }
    $billingAction = new AccountBilling();
    $action = (string) ($_POST['billing_action'] ?? '');
    try {
        if ($action === 'cancel') {
            $reasons = isset($_POST['reasons']) && is_array($_POST['reasons']) ? array_map('strval', $_POST['reasons']) : [];
            $result = $billingAction->cancel((int) $actor['id'], $reasons, (string) ($_POST['comment'] ?? ''));
            header('Location: ' . url('/painel/faturas?cancelado=1&ate=' . rawurlencode($result['access_until'])));
            exit;
        }
        if ($action === 'billing_day') {
            $billingAction->requestDay((int) $actor['id'], (int) ($_POST['billing_day'] ?? 0));
            header('Location: ' . url('/painel/faturas?data=1'));
            exit;
        }
        header('Location: ' . url('/painel/faturas'));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . url('/painel/faturas?erro=' . rawurlencode($e->getMessage())));
        exit;
    }
}

$panelTitle = 'Faturas';
$panelSection = 'faturas';
$panelLead = 'Acompanhe pagamentos, vencimentos e comprovantes.';
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

require_once dirname(__DIR__) . '/includes/AccountBilling.php';
$accountBilling = new AccountBilling();
$billingState = $accountBilling->summary((int) $panelUser['id']);

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
<?php
$invoiceFlash = '';
$invoiceFlashTone = 'ok';
if (isset($_GET['cancelado'])) {
    $invoiceFlash = 'Cancelamento registrado. Não há reembolso em PIX, boleto, débito ou cartão. A hospedagem segue disponível até ' . $fmtDate(isset($_GET['ate']) ? (string) $_GET['ate'] : null) . ', a data da próxima cobrança.';
} elseif (isset($_GET['data'])) {
    $invoiceFlash = 'Data enviada para análise. Até aprovar ou recusar, a cobrança no cartão fica no dia 10.';
} elseif (isset($_GET['erro'])) {
    $invoiceFlash = (string) $_GET['erro'];
    $invoiceFlashTone = 'warn';
}
$periodLabel = match ((string) ($billingState['period'] ?? 'mensal')) {
    'semestral' => 'semestral',
    'anual' => 'anual',
    default => 'mensal',
};
?>
<section class="panel-invoices" data-invoices data-csrf="<?= e($csrf) ?>">
    <?php if ($invoiceFlash !== ''): ?>
        <p class="billing-flash is-<?= e($invoiceFlashTone) ?>"><?= e($invoiceFlash) ?></p>
    <?php endif; ?>
    <?php if (!empty($billingState['cancellation'])): ?>
        <p class="billing-flash">Cancelamento confirmado. Sem reembolso. Uso liberado até <?= e($fmtDate((string) ($billingState['cancellation']['access_until'] ?? ''))) ?>.</p>
    <?php endif; ?>
    <?php if (($billingState['request']['status'] ?? '') === 'rejected' && !empty($billingState['request']['admin_note'])): ?>
        <p class="billing-flash is-warn">A data pedida foi recusada: <?= e((string) $billingState['request']['admin_note']) ?> Solicite outra data ou aceite o dia 10, que fica valendo até uma nova aprovação.</p>
    <?php endif; ?>

    <div class="billing-actions">
        <button type="button" class="gh-btn gh-btn-secondary" data-open-dialog="cancel-hosting" <?= empty($billingState['hasService']) || !empty($billingState['cancellation']) ? 'disabled' : '' ?>>Cancelar hospedagem</button>
        <button type="button" class="gh-btn gh-btn-secondary" data-open-dialog="billing-day" <?= empty($billingState['canRequestDay']) || (($billingState['request']['status'] ?? '') === 'pending') ? 'disabled' : '' ?>>Definir data de cobrança</button>
        <p class="billing-actions-note">
            <?php if (!empty($billingState['canRequestDay'])): ?>
                Cobrança no cartão no dia <?= (int) $billingState['billingDay'] ?>
                <?php if (($billingState['source'] ?? '') === 'pending'): ?>
                    (dia 10 enquanto a solicitação está em análise)
                <?php elseif (($billingState['source'] ?? '') === 'purchase'): ?>
                    (dia da compra; se o cartão recusar, a tentativa seguinte é no mês seguinte)
                <?php elseif (($billingState['source'] ?? '') === 'rejected'): ?>
                    (dia 10 depois da recusa)
                <?php endif; ?>
                · plano <?= e($periodLabel) ?>.
            <?php else: ?>
                Esta hospedagem não foi contratada no cartão. PIX, boleto e débito são cobrados na hora e não têm os 7 dias grátis.
            <?php endif; ?>
        </p>
    </div>
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
                                <a class="gh-btn gh-btn-ghost gh-btn-sm" href="<?= e((string) $invoice['invoiceUrl']) ?>" target="_blank" rel="noopener noreferrer" title="Abrir fatura">
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

<dialog class="billing-dialog" id="cancel-hosting">
    <form method="post" action="<?= e(url('/painel/faturas')) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="billing_action" value="cancel">
        <h2>Cancelar hospedagem</h2>
        <p>PIX, boleto, débito e cartão não têm reembolso. O serviço continua até a próxima data de cobrança. Exemplo: compra em 01/10 e cancelamento em 25/10 — uso até 01/11.</p>
        <fieldset>
            <legend>Por que você quer cancelar?</legend>
            <?php foreach (AccountBilling::REASONS as $key => $label): ?>
                <label class="billing-check"><input type="checkbox" name="reasons[]" value="<?= e($key) ?>"> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <label class="billing-comment">Comentário
            <textarea name="comment" rows="4" maxlength="1000" placeholder="Conte o que aconteceu"></textarea>
        </label>
        <div class="billing-dialog-actions">
            <button type="button" class="gh-btn gh-btn-ghost" data-close-dialog>Voltar</button>
            <button type="submit" class="gh-btn gh-btn-primary">Confirmar cancelamento</button>
        </div>
    </form>
</dialog>

<dialog class="billing-dialog" id="billing-day">
    <form method="post" action="<?= e(url('/painel/faturas')) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="billing_action" value="billing_day">
        <h2>Data de cobrança no cartão</h2>
        <p>Vale para plano mensal, semestral e anual. A solicitação vai para análise. Enquanto isso, a cobrança fica no dia 10. Sem solicitação, o cartão é cobrado no dia da compra e, se recusar, tentamos de novo no mês seguinte.</p>
        <label>Dia de todo mês
            <input type="number" name="billing_day" min="1" max="28" value="10" required>
        </label>
        <div class="billing-dialog-actions">
            <button type="button" class="gh-btn gh-btn-ghost" data-close-dialog>Voltar</button>
            <button type="submit" class="gh-btn gh-btn-primary">Enviar para análise</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.getAttribute('data-open-dialog'));
        if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    });
});
document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
</script>

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
