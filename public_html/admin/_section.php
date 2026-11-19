<?php

declare(strict_types=1);

/** Shared placeholder for admin sections still being expanded with real APIs. */
$adminTitle = $adminTitle ?? 'Admin';
$adminSection = $adminSection ?? '';
$adminLead = $adminLead ?? 'Seção do Admin GeekHost (app separado do painel do cliente).';
require __DIR__ . '/_layout.php';
?>
<article class="admin-card">
    <h2><?= e($adminTitle) ?></h2>
    <p class="admin-empty">
        Estrutura do Admin GeekHost ativa. Esta seção será preenchida com dados reais do banco/Asaas/DirectAdmin na sequência das fases —
        sem mock de produção.
    </p>
    <p><a class="gh-btn" href="<?= e(url('/admin/')) ?>">Voltar à visão geral</a></p>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
