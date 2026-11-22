<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/password-field.php';

$auth = new Auth();
$requireAuth = $requireAuth ?? true;
$panelUser = $requireAuth ? $auth->requireUser() : $auth->user();
$csrf = csrf_token();
$panelTitle = $panelTitle ?? 'Painel';
$panelSection = $panelSection ?? '';
$panelLead = $panelLead ?? '';
$panelActions = $panelActions ?? '';
$isAuthPage = !$requireAuth;

$panelFirstName = '';
$panelUserInitials = 'GH';
$panelHasOpenInvoice = false;
$panelPayUrl = url('/painel/faturas?status=em_aberto');
if (!empty($panelUser)) {
    $panelFirstName = explode(' ', trim((string) ($panelUser['name'] ?? '')))[0] ?: 'cliente';
    $nameParts = preg_split('/\s+/', trim((string) ($panelUser['name'] ?? ''))) ?: [];
    $panelUserInitials = strtoupper(substr((string) ($nameParts[0] ?? 'G'), 0, 1) . substr((string) ($nameParts[1] ?? 'H'), 0, 1));
    if ($requireAuth) {
        require_once dirname(__DIR__) . '/includes/OrderService.php';
        $panelOrders = new OrderService();
        $panelInvoiceCounts = $panelOrders->invoiceCounts((int) $panelUser['id']);
        $panelHasOpenInvoice = ((int) ($panelInvoiceCounts['em_aberto'] ?? 0)) > 0;
        if ($panelHasOpenInvoice) {
            $openInvoices = $panelOrders->listInvoicesForUser((int) $panelUser['id'], null, ['status' => 'em_aberto']);
            $firstOpen = $openInvoices[0] ?? null;
            if (!empty($firstOpen['invoiceUrl'])) {
                $panelPayUrl = (string) $firstOpen['invoiceUrl'];
            } elseif (!empty($firstOpen['boleto']['url'])) {
                $panelPayUrl = (string) $firstOpen['boleto']['url'];
            } elseif (!empty($firstOpen['id'])) {
                $panelPayUrl = url('/checkout?order=' . rawurlencode((string) $firstOpen['id']));
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($panelTitle) ?> | GeekHost</title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.468.0" defer></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/password-field.css')) ?>">
    <?php if ($isAuthPage): ?>
        <link rel="stylesheet" href="<?= e(asset('assets/css/auth.css')) ?>">
    <?php endif; ?>
</head>
<body
    class="panel-body<?= $isAuthPage ? ' is-auth' : '' ?>"
    data-panel
    data-csrf="<?= e($csrf) ?>"
    <?php if (!$isAuthPage && $panelHasOpenInvoice): ?>
        data-unpaid-hosting="1"
        data-pay-url="<?= e($panelPayUrl) ?>"
    <?php endif; ?>
>
<?php if (!$isAuthPage): ?>
<aside class="panel-sidebar" data-sidebar>
    <div class="panel-sidebar-glow" aria-hidden="true"></div>
    <div class="panel-sidebar-top">
        <a class="panel-sidebar-brand" href="<?= e(url('/painel/')) ?>" title="GeekHost">
            <img class="panel-brand-full" src="<?= e(asset('assets/images/LOGO-02.png')) ?>" alt="GeekHost" width="140" height="40">
            <img class="panel-brand-mark" src="<?= e(asset('assets/images/favicon.png')) ?>" alt="GeekHost" width="40" height="40">
        </a>
        <button type="button" class="panel-icon-btn panel-sidebar-toggle" data-sidebar-toggle aria-expanded="true" aria-controls="panel-sidebar-nav" title="Recolher menu">
            <i data-lucide="panel-left" aria-hidden="true"></i>
        </button>
    </div>
    <nav id="panel-sidebar-nav" class="panel-sidebar-nav" aria-label="Menu do painel">
        <a href="<?= e(url('/painel/')) ?>" class="<?= $panelSection === 'inicio' ? 'is-active' : '' ?>" data-nav-label="Início" title="Início">
            <i data-lucide="layout-dashboard" aria-hidden="true"></i><span>Início</span>
        </a>
        <a href="<?= e(url('/painel/dominios')) ?>" class="<?= $panelSection === 'dominios' ? 'is-active' : '' ?>" data-nav-label="Domínios" title="Domínios">
            <i data-lucide="globe-2" aria-hidden="true"></i><span>Domínios</span>
        </a>
        <a href="<?= e(url('/painel/sites')) ?>" class="<?= $panelSection === 'sites' ? 'is-active' : '' ?>" data-nav-label="Meus sites" title="Meus sites">
            <i data-lucide="layers" aria-hidden="true"></i><span>Meus sites</span>
        </a>
        <a href="<?= e(url('/painel/faturas')) ?>" class="<?= $panelSection === 'faturas' ? 'is-active' : '' ?><?= $panelHasOpenInvoice ? ' is-unpaid-pulse' : '' ?>" data-nav-label="Faturas" title="Faturas">
            <i data-lucide="receipt" aria-hidden="true"></i><span>Faturas</span>
        </a>
        <a href="<?= e(url('/painel/carteira')) ?>" class="<?= $panelSection === 'carteira' ? 'is-active' : '' ?>" data-nav-label="Carteira" title="Carteira">
            <i data-lucide="wallet" aria-hidden="true"></i><span>Carteira</span>
        </a>
        <a href="<?= e(url('/painel/perfil')) ?>" class="<?= $panelSection === 'perfil' ? 'is-active' : '' ?>" data-nav-label="Perfil" title="Perfil">
            <i data-lucide="user-round" aria-hidden="true"></i><span>Perfil</span>
        </a>
        <a href="<?= e(url('/painel/suporte')) ?>" class="<?= $panelSection === 'suporte' || $panelSection === 'admin-tickets' ? 'is-active' : '' ?>" data-nav-label="Suporte" title="Suporte">
            <i data-lucide="headset" aria-hidden="true"></i><span>Suporte</span>
        </a>
    </nav>
    <div class="panel-sidebar-bottom">
        <?php if (!empty($companyWhatsapp)): ?>
            <a class="panel-sidebar-link" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp">
                <i data-lucide="message-circle" aria-hidden="true"></i><span>WhatsApp</span>
            </a>
        <?php endif; ?>
        <a class="panel-sidebar-link" href="<?= e(url('/painel/logout')) ?>" title="Sair">
            <i data-lucide="log-out" aria-hidden="true"></i><span>Sair</span>
        </a>
    </div>
</aside>

<div class="panel-shell">
    <header class="panel-topbar">
        <button type="button" class="panel-icon-btn panel-mobile-toggle" data-sidebar-open aria-label="Abrir menu">
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>

        <form class="panel-search" data-panel-search role="search">
            <i data-lucide="search" aria-hidden="true"></i>
            <input type="search" name="q" placeholder="Buscar no painel…" aria-label="Buscar no painel" autocomplete="off">
            <div class="panel-search-results" data-search-results hidden></div>
        </form>

        <div class="panel-topbar-end">
            <button type="button" class="panel-icon-btn panel-notify" data-notify-toggle aria-label="Notificações" title="Notificações" aria-expanded="false">
                <i data-lucide="bell" aria-hidden="true"></i>
                <span class="panel-notify-dot" data-notify-dot hidden aria-hidden="true"></span>
            </button>
            <div class="panel-notify-menu" data-notify-menu hidden>
                <header>
                    <span>Notificações</span>
                    <button type="button" class="gh-link" data-notify-read-all>Marcar lidas</button>
                </header>
                <div class="panel-notify-list" data-notify-list>
                    <div class="panel-notify-item"><span>Carregando…</span></div>
                </div>
            </div>

            <div class="panel-account" data-account-menu>
                <button type="button" class="panel-account-btn" data-account-toggle aria-expanded="false" aria-haspopup="true">
                    <?php if (!empty($panelUser['photoPath'])): ?>
                        <img class="panel-account-avatar" src="<?= e(asset((string) $panelUser['photoPath'])) ?>" alt="" width="40" height="40">
                    <?php else: ?>
                        <span class="panel-account-avatar is-fallback" aria-hidden="true"><?= e($panelUserInitials) ?></span>
                    <?php endif; ?>
                    <span class="panel-account-meta">
                        <strong><?= e((string) ($panelUser['name'] ?: 'Cliente')) ?></strong>
                        <small>Cliente GeekHost</small>
                    </span>
                    <i data-lucide="chevron-down" aria-hidden="true"></i>
                </button>
                <div class="panel-account-dropdown" data-account-dropdown hidden>
                    <a href="<?= e(url('/painel/perfil')) ?>"><i data-lucide="user-round" aria-hidden="true"></i> Perfil</a>
                    <a href="<?= e(url('/painel/carteira')) ?>"><i data-lucide="wallet" aria-hidden="true"></i> Carteira</a>
                    <a href="<?= e(url('/painel/faturas')) ?>"><i data-lucide="receipt" aria-hidden="true"></i> Faturas</a>
                    <a href="<?= e(url('/painel/suporte')) ?>"><i data-lucide="headset" aria-hidden="true"></i> Suporte</a>
                    <a href="<?= e(url('/painel/logout')) ?>" class="is-danger"><i data-lucide="log-out" aria-hidden="true"></i> Sair</a>
                </div>
            </div>
        </div>
    </header>

    <div class="panel-page-head">
        <div>
            <p class="panel-eyebrow">Área do cliente</p>
            <h1 class="panel-page-title"><?= e($panelTitle) ?></h1>
            <?php if ($panelLead !== ''): ?>
                <p class="panel-page-lead"><?= e((string) $panelLead) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($panelActions !== ''): ?>
            <div class="panel-page-actions"><?= $panelActions /* trusted markup from page */ ?></div>
        <?php endif; ?>
    </div>

    <main class="panel-main">
        <?php if (($panelUser['hostingStatus'] ?? '') === 'suspended'): ?>
            <div class="gh-alert gh-alert-danger" role="alert">
                <i data-lucide="triangle-alert" aria-hidden="true"></i>
                <div>Serviço suspenso por atraso superior a 2 meses. Regularize as faturas em aberto.</div>
            </div>
        <?php endif; ?>
<?php else: ?>
<main class="panel-main panel-main-auth">
<aside class="auth-visual">
    <div class="auth-stars" aria-hidden="true"></div>
    <div class="auth-orbit" aria-hidden="true"></div>
    <div class="auth-visual-top">
        <div class="auth-wordmark">
            <img src="<?= e(asset('assets/images/favicon.png')) ?>" alt="" width="42" height="42">
            GeekHost
        </div>
    </div>
    <div class="auth-visual-copy">
        <h2>Seu site no ar, com gente de verdade.</h2>
        <p>Faturas, domínio e suporte humano no mesmo lugar. Sem fila de robô.</p>
        <div class="auth-float-card">
            <strong>Suporte no WhatsApp</strong>
            <span>Resposta humana enquanto você publica o projeto.</span>
        </div>
    </div>
</aside>
<?php endif; ?>
