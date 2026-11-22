<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';
require_once dirname(__DIR__) . '/includes/AccountBilling.php';

$gate = new AdminGate();
try {
    $ctx = $gate->requireAdmin();
} catch (Throwable $e) {
    $ctx = null;
}
$notice = '';
$noticeError = '';
if ($ctx && in_array('datas', $gate->pagesFor($ctx['admin']), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $noticeError = 'Sessão expirada. Recarregue a página.';
    } else {
        try {
            (new AccountBilling())->decide(
                (int) ($_POST['request_id'] ?? 0),
                (string) ($_POST['decision'] ?? ''),
                (string) ($_POST['admin_note'] ?? '')
            );
            header('Location: ' . url('/admin/datas-cobranca?ok=1'));
            exit;
        } catch (Throwable $e) {
            $noticeError = $e->getMessage();
        }
    }
}

$adminTitle = 'Datas de cobrança';
$adminSection = 'datas';
$adminLead = 'Aprovar ou recusar o dia do cartão. Sem resposta, vale o dia 10.';
require __DIR__ . '/_layout.php';

$billing = new AccountBilling();
$rows = $billing->listDateRequests();
$fmt = static function (?string $iso): string {
    if (!$iso) {
        return '—';
    }
    $ts = strtotime(substr($iso, 0, 10));
    return $ts ? date('d/m/Y', $ts) : '—';
};
?>
<article class="admin-card">
    <h2>Solicitações de data</h2>
    <?php if (isset($_GET['ok'])): ?><p>Resposta registrada.</p><?php endif; ?>
    <?php if ($noticeError !== ''): ?><p><?= e($noticeError) ?></p><?php endif; ?>
    <p>Enquanto a solicitação está aberta, a cobrança no cartão fica no dia 10. Se recusar, informe o motivo: o cliente pede outra data ou permanece no dia 10. Sem pedido, o cartão segue o dia da compra e uma recusa é tentada de novo no mês seguinte.</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Cliente</th>
                <th>Plano</th>
                <th>Dia pedido</th>
                <th>Status</th>
                <th>Resposta</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="5">Nenhuma solicitação.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <strong><?= e((string) ($row['name'] !== '' ? $row['name'] : $row['user_email'])) ?></strong><br>
                        <?= e((string) $row['user_email']) ?><br>
                        <?= e($fmt((string) ($row['created_at'] ?? ''))) ?>
                    </td>
                    <td><?= e((string) ($row['plan_code'] ?? '—')) ?><br><?= e((string) ($row['billing_period'] ?? '')) ?></td>
                    <td>Dia <?= (int) $row['requested_day'] ?><?php if (!empty($row['previous_day'])): ?><br>antes: <?= (int) $row['previous_day'] ?><?php endif; ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td>
                        <?php if ((string) $row['status'] === 'pending'): ?>
                            <form method="post" action="<?= e(url('/admin/datas-cobranca')) ?>">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                                <label>Motivo, se recusar
                                    <input type="text" name="admin_note" maxlength="500" placeholder="Obrigatório na recusa">
                                </label>
                                <button class="gh-btn" type="submit" name="decision" value="approve">Aprovar</button>
                                <button class="gh-btn" type="submit" name="decision" value="reject">Recusar</button>
                            </form>
                        <?php else: ?>
                            <?= e((string) ($row['admin_note'] !== '' && $row['admin_note'] !== null ? $row['admin_note'] : '—')) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
