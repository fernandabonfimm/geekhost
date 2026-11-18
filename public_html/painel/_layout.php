<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auth = new Auth();
$requireAuth = $requireAuth ?? true;
$panelUser = $requireAuth ? $auth->requireUser() : $auth->user();
$csrf = csrf_token();
$panelTitle = $panelTitle ?? 'Painel';
$panelSection = $panelSection ?? '';
$isAuthPage = !$requireAuth;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($panelTitle) ?> | GeekHost</title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gh: { lime: '#01e152', ink: '#1a1e24', paper: '#f3f4f5', line: '#dfe2e5', muted: '#697078' }
                    },
                    borderRadius: { DEFAULT: '0px', none: '0px' },
                    fontFamily: { sans: ['Galano Grotesque', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
                }
            },
            corePlugins: { preflight: false }
        };
    </script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
</head>
<script>
(() => {
    try {
        if (localStorage.getItem('gh_panel_theme') === 'dark') {
            document.documentElement.classList.add('panel-theme-dark');
        }
    } catch (_) {}
})();
</script>
<body class="panel-body<?= $isAuthPage ? ' is-auth' : '' ?>" data-panel>
<?php if (!$isAuthPage): ?>
<aside class="panel-sidebar" data-sidebar>
    <div class="panel-sidebar-top">
        <a class="panel-sidebar-brand" href="<?= e(url('/painel/')) ?>" title="GeekHost">
            <img src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="140" height="48">
        </a>
        <button type="button" class="panel-sidebar-toggle" data-sidebar-toggle aria-expanded="true" aria-controls="panel-sidebar-nav" title="Abrir ou fechar menu">
            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
        </button>
    </div>
    <nav id="panel-sidebar-nav" class="panel-sidebar-nav" aria-label="Painel">
        <a href="<?= e(url('/painel/')) ?>" class="<?= $panelSection === 'inicio' ? 'is-active' : '' ?>" title="Início">
            <i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Início</span>
        </a>
        <a href="<?= e(url('/painel/faturas')) ?>" class="<?= $panelSection === 'faturas' ? 'is-active' : '' ?>" title="Faturas">
            <i class="bi bi-receipt" aria-hidden="true"></i><span>Faturas</span>
        </a>
        <a href="<?= e(url('/painel/carteira')) ?>" class="<?= $panelSection === 'carteira' ? 'is-active' : '' ?>" title="Carteira">
            <i class="bi bi-credit-card-2-front" aria-hidden="true"></i><span>Carteira</span>
        </a>
        <a href="<?= e(url('/painel/perfil')) ?>" class="<?= $panelSection === 'perfil' ? 'is-active' : '' ?>" title="Perfil">
            <i class="bi bi-person" aria-hidden="true"></i><span>Perfil</span>
        </a>
    </nav>
    <div class="panel-sidebar-bottom">
        <p class="panel-sidebar-user" title="<?= e((string) ($panelUser['email'] ?? '')) ?>">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span><?= e(explode(' ', (string) ($panelUser['name'] ?? 'cliente'))[0] ?: 'cliente') ?></span>
        </p>
        <a class="panel-sidebar-logout" href="<?= e(url('/painel/logout')) ?>" title="Sair">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sair</span>
        </a>
    </div>
</aside>
<div class="panel-shell">
    <header class="panel-topbar">
        <button type="button" class="panel-mobile-toggle" data-sidebar-open aria-label="Abrir menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="panel-topbar-title">
            <p class="eyebrow">Área do cliente</p>
            <h1><?= e($panelTitle) ?></h1>
        </div>
        <div class="panel-topbar-actions">
            <a class="panel-topbar-site" href="<?= e(url('/')) ?>">Site</a>
            <button type="button" class="panel-theme-toggle" data-theme-toggle aria-label="Alternar tema claro ou escuro" title="Tema">
                <i class="bi bi-moon-stars panel-theme-icon-moon" aria-hidden="true"></i>
                <i class="bi bi-sun panel-theme-icon-sun" aria-hidden="true"></i>
            </button>
        </div>
    </header>
    <main class="panel-main">
        <?php if (($panelUser['hostingStatus'] ?? '') === 'suspended'): ?>
            <div class="panel-alert panel-alert-danger">Serviço suspenso por atraso superior a 2 meses. Regularize as faturas em aberto.</div>
        <?php endif; ?>
<?php else: ?>
<main class="panel-main panel-main-auth">
<?php endif; ?>
