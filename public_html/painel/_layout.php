<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auth = new Auth();
$panelUser = $auth->user();
$requireAuth = $requireAuth ?? true;
if ($requireAuth && !$panelUser) {
    header('Location: ' . url('/painel/login'));
    exit;
}
$csrf = csrf_token();
$panelTitle = $panelTitle ?? 'Painel';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($panelTitle) ?> | GeekHost</title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
</head>
<body class="panel-body">
<header class="panel-header">
    <div class="panel-header-inner page-width">
        <a class="brand" href="<?= e(url('/painel/')) ?>"><img class="header-logo" src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="160" height="56"></a>
        <?php if ($panelUser): ?>
            <nav class="panel-nav" aria-label="Painel">
                <a href="<?= e(url('/painel/')) ?>">Início</a>
                <a href="<?= e(url('/painel/faturas')) ?>" <?= ($panelSection ?? '') === 'faturas' ? 'aria-current="page"' : '' ?>>Faturamento</a>
                <a href="<?= e(url('/painel/perfil')) ?>" <?= ($panelSection ?? '') === 'perfil' ? 'aria-current="page"' : '' ?>>Perfil</a>
            </nav>
            <div class="panel-user">
                <span>Olá, <?= e(explode(' ', (string) $panelUser['name'])[0] ?: 'cliente') ?>!</span>
                <a class="text-link" href="<?= e(url('/painel/logout')) ?>">Sair</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<main class="panel-main page-width">
    <?php if ($panelUser && ($panelUser['hostingStatus'] ?? '') === 'suspended'): ?>
        <div class="panel-alert panel-alert-danger">Serviço suspenso por atraso superior a 2 meses. Regularize as faturas em aberto.</div>
    <?php endif; ?>
