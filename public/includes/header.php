<?php

require_once __DIR__ . '/config.php';

$pageTitle = $pageTitle ?? 'Hospedagem para o seu próximo projeto';
$pageDescription = $pageDescription ?? 'Tire seu projeto do papel com a GeekHost. Conheça as opções de hospedagem e encontre o plano para o seu momento.';
$currentPage = $currentPage ?? '';
$navigation = [
    'inicio' => ['label' => 'Início', 'href' => 'index.php'],
    'planos' => ['label' => 'Planos', 'href' => 'planos.php'],
    'contato' => ['label' => 'Contato', 'href' => 'contato.php'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#1a1e24">
    <title>Geekhost - Hospedagem de Sites: Rápida e Segura</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link rel="stylesheet" href="assets/css/site.css">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="GeekHost, página inicial">
            <img class="header-logo" src="assets/images/logo-header.png" alt="" width="400" height="240">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation">
            Menu
        </button>
        <nav class="site-navigation" id="site-navigation" aria-label="Navegação principal">
            <?php foreach ($navigation as $key => $item): ?>
                <a href="<?= e($item['href']) ?>" <?= $currentPage === $key ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a class="nav-tool" href="<?= $currentPage === 'inicio' ? '#planos' : 'planos.php' ?>">Ver planos <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
<main id="conteudo">