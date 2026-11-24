<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';
require_once dirname(__DIR__) . '/includes/password-field.php';

$adminGate = new AdminGate();
$requireAdminAuth = $requireAdminAuth ?? true;
$adminSection = $adminSection ?? 'overview';
$adminTitle = $adminTitle ?? 'Admin GeekHost';
$adminLead = $adminLead ?? '';
$csrf = csrf_token();

$adminUser = null;
$adminRow = null;
$adminName = 'Admin';
$adminRole = '';

if ($requireAdminAuth) {
    try {
        $ctx = $adminGate->requireAdmin();
        $adminUser = $ctx['user'];
        $adminRow = $ctx['admin'];
        $adminName = (string) ($adminUser['name'] ?? $adminUser['email'] ?? 'Admin');
        $adminRole = (string) ($adminRow['role'] ?? '');
    } catch (Throwable $e) {
        if (!empty($adminGate->auth()->user())) {
            http_response_code(403);
            echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Sem acesso</title>';
            echo '<body style="font-family:sans-serif;padding:40px">';
            echo '<h1>Acesso restrito ao Admin GeekHost</h1>';
            echo '<p>Esta área é o painel operacional da GeekHost (não o painel do cliente).</p>';
            echo '<p><a href="' . e(url('/admin/login')) . '">Entrar no admin</a> · ';
            echo '<a href="' . e(url('/painel/')) . '">Ir ao painel do cliente</a></p>';
            echo '</body></html>';
            exit;
        }
        header('Location: ' . url('/admin/login'));
        exit;
    }
}

$nav = [
    ['ops', 'Operação'],
    ['overview', 'Visão geral', 'layout-dashboard', '/admin/'],
    ['vendas', 'Vendas', 'shopping-bag', '/admin/vendas'],
    ['faturamento', 'Faturamento', 'line-chart', '/admin/faturamento'],
    ['cobrancas', 'Cobranças', 'receipt', '/admin/cobrancas'],
    ['cancelamentos', 'Cancelamentos', 'user-x', '/admin/cancelamentos'],
    ['datas', 'Datas de cobrança', 'calendar', '/admin/datas-cobranca'],
    ['clientes', 'Clientes', 'users', '/admin/clientes'],
    ['ops2', 'Técnico'],
    ['hospedagens', 'Hospedagens', 'server', '/admin/hospedagens'],
    ['dominios', 'Domínios', 'globe-2', '/admin/dominios'],
    ['sites', 'Sites', 'layers', '/admin/sites'],
    ['planos', 'Planos e pacotes', 'package', '/admin/planos'],
    ['ops3', 'Gestão'],
    ['administradores', 'Administradores', 'shield', '/admin/administradores'],
    ['relatorios', 'Relatórios', 'file-bar-chart', '/admin/relatorios'],
    ['auditoria', 'Logs e auditoria', 'scroll-text', '/admin/auditoria'],
    ['configuracoes', 'Configurações', 'settings', '/admin/configuracoes'],
    ['conhecimento', 'Base de conhecimento', 'book-open', '/admin/conhecimento'],
];

$adminAllowed = $adminRow ? $adminGate->pagesFor($adminRow) : [];
$adminDenied = $requireAdminAuth && !in_array($adminSection, $adminAllowed, true);
if ($requireAdminAuth) {
    $filtered = [];
    $label = null;
    foreach ($nav as $item) {
        if (count($item) === 2) {
            $label = $item;
            continue;
        }
        if (!in_array($item[0], $adminAllowed, true)) {
            continue;
        }
        if ($label) {
            $filtered[] = $label;
            $label = null;
        }
        $filtered[] = $item;
    }
    $nav = $filtered;
}
$adminHome = '/admin/';
foreach ($nav as $item) {
    if (count($item) === 4) {
        $adminHome = $item[3];
        break;
    }
}
?>
<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?> | Admin GeekHost</title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.468.0" defer></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/password-field.css')) ?>">
    <?php if (!$requireAdminAuth): ?>
        <link rel="stylesheet" href="<?= e(asset('assets/css/auth.css')) ?>">
    <?php endif; ?>
</head>
<body class="admin-body<?= $requireAdminAuth ? '' : ' is-auth-screen' ?>" data-admin data-csrf="<?= e($csrf) ?>">
<?php if ($requireAdminAuth): ?>
<aside class="admin-sidebar" data-admin-sidebar>
    <a class="admin-brand" href="<?= e(url($adminHome)) ?>">
        <img src="<?= e(asset('assets/images/favicon.png')) ?>" alt="">
        <span>
            <strong>GeekHost</strong>
            <small>Admin · DA geek6923</small>
        </span>
    </a>
    <nav class="admin-nav" aria-label="Admin">
        <?php foreach ($nav as $item):
            if (count($item) === 2): ?>
                <div class="admin-nav-label"><?= e($item[1]) ?></div>
            <?php else:
                [$key, $label, $icon, $href] = $item;
                ?>
                <a href="<?= e(url($href)) ?>" class="<?= $adminSection === $key ? 'is-active' : '' ?>" title="<?= e($label) ?>">
                    <i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
                </a>
            <?php endif;
        endforeach; ?>
    </nav>
</aside>
<div class="admin-sidebar-backdrop" data-admin-nav-close hidden></div>
<div class="admin-shell">
    <header class="admin-topbar">
        <button type="button" class="admin-icon-btn admin-mobile-toggle" data-admin-nav-open aria-label="Abrir menu">
            <i data-lucide="menu"></i>
        </button>
        <button type="button" class="admin-icon-btn" data-admin-collapse title="Recolher menu">
            <i data-lucide="panel-left"></i>
        </button>
        <form class="admin-search" action="<?= e(url('/admin/busca')) ?>" method="get" role="search">
            <i data-lucide="search" aria-hidden="true"></i>
            <input type="search" name="q" placeholder="Buscar cliente, domínio, venda, DA user…" value="<?= e((string) ($_GET['q'] ?? '')) ?>">
        </form>
        <label class="admin-period">
            <select name="period" data-admin-period aria-label="Período">
                <?php
                $period = (string) ($_GET['period'] ?? '30d');
                foreach ([
                    'today' => 'Hoje',
                    'yesterday' => 'Ontem',
                    '7d' => 'Últimos 7 dias',
                    '30d' => 'Últimos 30 dias',
                    'this_month' => 'Este mês',
                    'last_month' => 'Mês anterior',
                    '3m' => 'Últimos 3 meses',
                    'year' => 'Este ano',
                ] as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $period === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="admin-topbar-end">
            <button type="button" class="admin-icon-btn" data-admin-theme title="Tema">
                <i data-lucide="moon"></i>
            </button>
            <div class="admin-account-btn" title="<?= e((string) ($adminUser['email'] ?? '')) ?>">
                <div>
                    <strong><?= e(explode(' ', $adminName)[0] ?: 'Admin') ?></strong>
                    <small><?= e($adminRole !== '' ? $adminRole : 'staff') ?></small>
                </div>
            </div>
            <a class="gh-btn" href="<?= e(url('/admin/logout')) ?>">Sair</a>
        </div>
    </header>
    <main class="admin-main">
        <div class="admin-page-head">
            <h1><?= e($adminTitle) ?></h1>
            <?php if ($adminLead !== ''): ?><p><?= e($adminLead) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($_SESSION['admin_notice']) && is_string($_SESSION['admin_notice'])): ?>
            <div class="gh-alert"><div><?= e($_SESSION['admin_notice']) ?></div></div>
            <?php unset($_SESSION['admin_notice']); ?>
        <?php endif; ?>
        <?php if (!empty($adminDenied)): ?>
            <article class="admin-card"><p>Seu acesso não inclui esta página. Peça a um super admin para liberar.</p></article>
            <?php require __DIR__ . '/_footer.php'; exit; ?>
        <?php endif; ?>
<?php else: ?>
<main>
<?php endif; ?>
