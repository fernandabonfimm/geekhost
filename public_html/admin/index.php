<?php

declare(strict_types=1);

$adminTitle = 'Visão geral';
$adminSection = 'overview';
$adminLead = 'Indicadores reais do banco GeekHost (espelho Asaas + hospedagens DA).';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/AdminMetricsService.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$metrics = new AdminMetricsService();
$period = (string) ($_GET['period'] ?? '30d');
$range = $metrics->resolveRange($period);
$overview = $metrics->overview($range);
$activity = $metrics->recentActivity(20);
$series = $metrics->salesSeries($range['from'], $range['to']);

$fmt = static function (?int $cents): string {
    if ($cents === null) {
        return '—';
    }
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
};
$delta = static function (?float $pct): array {
    if ($pct === null) {
        return ['—', 'is-muted'];
    }
    $cls = $pct < 0 ? 'is-down' : '';
    $sign = $pct > 0 ? '+' : '';
    return [$sign . $pct . '% vs período anterior', $cls];
};

$fin = $overview['financial'];
$cli = $overview['clients'];
$host = $overview['hosting'];
[$gDelta, $gCls] = $delta($fin['grossDeltaPct'] ?? null);
[$rDelta, $rCls] = $delta($fin['receivedDeltaPct'] ?? null);
?>
<section class="admin-kpis">
    <article class="admin-kpi"><span>Faturamento bruto</span><strong><?= e($fmt((int) $fin['grossCents'])) ?></strong><em class="<?= e($gCls) ?>"><?= e($gDelta) ?></em></article>
    <article class="admin-kpi"><span>Receita recebida</span><strong><?= e($fmt((int) $fin['receivedCents'])) ?></strong><em class="<?= e($rCls) ?>"><?= e($rDelta) ?></em></article>
    <article class="admin-kpi"><span>MRR</span><strong><?= e($fmt((int) $fin['mrrCents'])) ?></strong><em class="is-muted">ARR <?= e($fmt((int) $fin['arrCents'])) ?></em></article>
    <article class="admin-kpi"><span>Em aberto</span><strong><?= e($fmt((int) $fin['openCents'])) ?></strong></article>
    <article class="admin-kpi"><span>Vencido</span><strong><?= e($fmt((int) $fin['overdueCents'])) ?></strong></article>
    <article class="admin-kpi"><span>Ticket médio</span><strong><?= e($fmt((int) $fin['avgTicketCents'])) ?></strong></article>
    <article class="admin-kpi"><span>Clientes (total)</span><strong><?= (int) $cli['total'] ?></strong><em class="is-muted">Novos no período: <?= (int) $cli['new'] ?></em></article>
    <article class="admin-kpi"><span>Hospedagens ativas</span><strong><?= (int) $host['active'] ?></strong><em class="is-muted">Pendentes: <?= (int) $host['pending'] ?></em></article>
</section>

<div class="admin-grid-2">
    <article class="admin-card">
        <h2>Evolução do faturamento</h2>
        <?php if ($series === []): ?>
            <p class="admin-empty">Sem vendas no período selecionado.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Dia</th><th>Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($series as $row): ?>
                        <tr>
                            <td><?= e((string) $row['day']) ?></td>
                            <td><?= e($fmt((int) $row['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
    <article class="admin-card">
        <h2>Operação</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <tbody>
                <tr><td>Clientes ativos (hosting_status)</td><td><?= (int) $cli['active'] ?></td></tr>
                <tr><td>Inadimplentes</td><td><?= (int) $cli['delinquent'] ?></td></tr>
                <tr><td>Com hospedagem DA ativa</td><td><?= (int) $cli['withActiveHosting'] ?></td></tr>
                <tr><td>Hospedagens suspensas</td><td><?= (int) $host['suspended'] ?></td></tr>
                <tr><td>Jobs DA com erro</td><td><?= (int) $host['failed'] ?></td></tr>
                <tr><td>Domínios</td><td><?= (int) $host['domains'] ?></td></tr>
                <tr><td>Sites WordPress</td><td><?= (int) $host['wordpress'] ?></td></tr>
                <tr><td>Sites blank</td><td><?= (int) $host['blankSites'] ?></td></tr>
                </tbody>
            </table>
        </div>
        <p class="admin-empty" style="margin-top:10px"><?= e((string) $fin['netNote']) ?></p>
    </article>
</div>

<article class="admin-card">
    <h2>Atividade recente</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Evento</th><th>Valor</th><th>Quando</th><th></th></tr></thead>
            <tbody>
            <?php if ($activity === []): ?>
                <tr><td colspan="4" class="admin-empty">Nenhum evento ainda.</td></tr>
            <?php else: foreach ($activity as $ev): ?>
                <tr>
                    <td><?= e((string) $ev['title']) ?></td>
                    <td><?= isset($ev['amountCents']) ? e($fmt((int) $ev['amountCents'])) : '—' ?></td>
                    <td><?= e((string) ($ev['at'] ?? '')) ?></td>
                    <td><a class="gh-btn" href="<?= e(url((string) $ev['link'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
