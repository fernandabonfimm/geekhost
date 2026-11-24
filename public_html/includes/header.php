<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/password-field.php';

$pageTitle = $pageTitle ?? 'Hospedagem para o seu próximo projeto';
$pageDescription = $pageDescription ?? 'Tire seu projeto do papel com a GeekHost. Conheça as opções de hospedagem e encontre o plano para o seu momento.';
$currentPage = $currentPage ?? '';
$navigation = [
    'inicio' => ['label' => 'Início', 'href' => 'index.php'],
    'planos' => ['label' => 'Planos', 'href' => 'planos.php'],
    'vantagens' => ['label' => 'Vantagens', 'href' => 'vantagens.php'],
    'quem-somos' => ['label' => 'Quem Somos', 'href' => 'quem-somos.php'],
    'contato' => ['label' => 'Contato', 'href' => 'contato.php'],
    'base' => ['label' => 'Base de conhecimento', 'href' => url('/base-de-conhecimento')],
];
$pageDocumentTitle = $pageDocumentTitle ?? 'Hospedagem de Sites: Rápida e Segura';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#1a1e24">
    <title><?= e($pageDocumentTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon.png')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/password-field.css')) ?>">
</head>
<body class="<?= e(trim((string) ($bodyClass ?? ''))) ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header<?= !empty($checkoutHeader) ? ' site-header-checkout' : '' ?>">
    <div class="header-inner page-width">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="GeekHost, página inicial">
            <img class="header-logo" src="<?= e(asset('assets/images/logo-header.png')) ?>" alt="GeekHost" width="200" height="70">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
            Menu
        </button>
        <nav class="site-navigation" id="site-navigation" aria-label="Navegação principal">
            <?php foreach ($navigation as $key => $item): ?>
                <a href="<?= e($item['href']) ?>" <?= $currentPage === $key ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <div class="account-menu" data-account-menu>
                <button class="account-menu-toggle" type="button" aria-expanded="false" aria-controls="account-menu-panel">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span>Minha Conta</span>
                    <i class="bi bi-chevron-down account-menu-chevron" aria-hidden="true"></i>
                </button>
                <div class="account-menu-panel" id="account-menu-panel" hidden>
                    <p class="account-menu-label">Área do cliente</p>
                    <a href="<?= e(url('/painel/login')) ?>">
                        <span class="account-menu-icon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></span>
                        <span><strong>Fazer Login</strong><small>Entre na sua conta</small></span>
                    </a>
                    <a href="<?= e(url('/painel/cadastro')) ?>">
                        <span class="account-menu-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                        <span><strong>Cadastre-se</strong><small>Crie seu cadastro conosco</small></span>
                    </a>
                    <a href="<?= e(url('/painel/esqueci-senha')) ?>">
                        <span class="account-menu-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                        <span><strong>Esqueci Minha Senha</strong><small>Recupere sua senha</small></span>
                    </a>
                    <a href="<?= e(url('/painel/faturas')) ?>">
                        <span class="account-menu-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                        <span><strong>Minhas Faturas</strong><small>Acesse suas faturas</small></span>
                    </a>
                    <a href="<?= e(url('/painel/segunda-via')) ?>">
                        <span class="account-menu-icon"><i class="bi bi-upc-scan" aria-hidden="true"></i></span>
                        <span><strong>2ª Via PIX e Boleto</strong><small>Gere nova via para pagamento</small></span>
                    </a>
                </div>
            </div>
            <a class="nav-tool" href="<?= $currentPage === 'inicio' ? '#planos' : 'planos.php' ?>">Ver planos <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
<main id="conteudo">
