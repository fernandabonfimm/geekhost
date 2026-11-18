<?php

declare(strict_types=1);

$panelTitle = 'Área do cliente';
$panelSection = 'inicio';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/db.php';

$orders = new OrderService();
$counts = $orders->invoiceCounts((int) $panelUser['id']);
$pdo = geekhost_db();
$services = $pdo->prepare('SELECT * FROM services WHERE user_id = :u ORDER BY id DESC LIMIT 5');
$services->execute([':u' => $panelUser['id']]);
$serviceRows = $services->fetchAll() ?: [];
?>
<section class="panel-home">
    <h1>Olá, <?= e(explode(' ', (string) $panelUser['name'])[0] ?: 'cliente') ?></h1>
    <p class="panel-lead">Status da hospedagem: <strong><?= e((string) $panelUser['hostingStatus']) ?></strong></p>
    <div class="panel-cards">
        <a class="panel-card" href="/painel/faturas"><strong><?= (int) $counts['em_aberto'] ?></strong><span>Faturas em aberto</span></a>
        <a class="panel-card" href="/painel/faturas?status=pago"><strong><?= (int) $counts['pago'] ?></strong><span>Faturas pagas</span></a>
        <a class="panel-card" href="/painel/perfil"><strong>Perfil</strong><span>Dados e cartão recorrente</span></a>
    </div>
    <h2>Serviços</h2>
    <?php if ($serviceRows === []): ?>
        <p>Nenhum serviço provisionado ainda. Após o pagamento, o status fica em <code>paid_pending_provisioning</code>.</p>
    <?php else: ?>
        <ul class="panel-service-list">
            <?php foreach ($serviceRows as $service): ?>
                <li>
                    <strong><?= e((string) $service['domain']) ?></strong>
                    <span><?= e((string) $service['plan_code']) ?> · <?= e((string) $service['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
