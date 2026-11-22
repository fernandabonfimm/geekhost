<?php

declare(strict_types=1);

$adminTitle = 'Cancelamentos';
$adminSection = 'cancelamentos';
$adminLead = 'Pedidos de cancelamento, sem reembolso, com dados para recuperar o cliente.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/AccountBilling.php';

$billing = new AccountBilling();
$rows = $billing->listCancellations();
$fmt = static function (?string $iso): string {
    if (!$iso) {
        return '—';
    }
    $ts = strtotime(substr($iso, 0, 10));
    return $ts ? date('d/m/Y', $ts) : '—';
};
?>
<article class="admin-card">
    <h2>Cancelamentos</h2>
    <p>Nenhum meio (PIX, boleto, débito ou cartão) gera reembolso. O cliente usa o serviço até a próxima cobrança.</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Quando</th>
                <th>Cliente</th>
                <th>Contato</th>
                <th>Plano</th>
                <th>Pagamento</th>
                <th>Motivos</th>
                <th>Comentário</th>
                <th>Usa até</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8">Nenhum cancelamento registrado.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($fmt((string) ($row['created_at'] ?? ''))) ?></td>
                    <td>
                        <strong><?= e((string) ($row['name'] !== '' ? $row['name'] : $row['user_email'])) ?></strong><br>
                        <?= e((string) $row['user_email']) ?>
                        <?php if (!empty($row['document'])): ?><br><?= e((string) $row['document']) ?><?php endif; ?>
                    </td>
                    <td><?= e((string) ($row['phone_plain'] !== '' ? $row['phone_plain'] : '—')) ?></td>
                    <td>
                        <?= e((string) ($row['plan_code'] ?? '—')) ?><br>
                        <?= e((string) ($row['domain'] !== '' ? $row['domain'] : 'domínio pendente')) ?>
                        · <?= e((string) ($row['billing_period'] ?? '')) ?>
                    </td>
                    <td><?= e((string) ($row['billing_type'] ?? '—')) ?><br>Reembolso R$ 0,00</td>
                    <td><?= e(implode(', ', $row['reason_labels'] ?? [])) ?></td>
                    <td><?= e((string) ($row['comment'] !== '' ? $row['comment'] : '—')) ?></td>
                    <td><?= e($fmt((string) ($row['access_until'] ?? ''))) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
