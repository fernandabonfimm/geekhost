<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/KnowledgeBase.php';

$kb = new KnowledgeBase();
$slug = trim((string) ($_GET['slug'] ?? ''));
$cat = trim((string) ($_GET['cat'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$article = $slug !== '' ? $kb->article($slug) : null;
$categories = $kb->categories();
$articles = $article ? [] : $kb->articles($cat !== '' ? $cat : null, $q);
$pageDocumentTitle = $article ? ((string) $article['title'] . ' | Base de conhecimento') : 'Base de conhecimento | GeekHost';
$currentPage = 'base';
require __DIR__ . '/includes/header.php';
?>
<section class="page-width" style="padding:32px 0 64px">
    <p style="color:#697078;font-size:13px;margin:0 0 8px"><a href="<?= e(url('/')) ?>">Início</a> / Base de conhecimento<?= $article ? ' / ' . e((string) $article['title']) : '' ?></p>
    <h1 style="margin:0 0 8px">Base de conhecimento</h1>
    <p style="margin:0 0 20px;color:#697078">Como usar o painel, pagar, cadastrar domínio, criar site, entrar no DirectAdmin e criar banco de dados.</p>
    <form method="get" action="<?= e(url('/base-de-conhecimento')) ?>" style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar um assunto" style="flex:1;min-width:220px;min-height:44px;border:1px solid #e3e7ec;border-radius:12px;padding:0 14px">
        <button class="gh-btn gh-btn-primary" type="submit" style="background:#01e152;border:0;border-radius:999px;padding:0 18px;font-weight:800">Pesquisar</button>
    </form>
    <?php if ($article): ?>
        <article style="background:#fff;border:1px solid #e6e8eb;border-radius:16px;padding:24px">
            <p style="margin:0 0 8px;color:#008f39;font-weight:800;font-size:12px;letter-spacing:.08em;text-transform:uppercase"><?= e((string) $article['category_title']) ?></p>
            <h2 style="margin:0 0 12px"><?= e((string) $article['title']) ?></h2>
            <div style="white-space:pre-wrap;line-height:1.6"><?= e((string) $article['body']) ?></div>
            <p style="margin-top:20px"><a href="<?= e(url('/base-de-conhecimento')) ?>">Voltar aos tópicos</a></p>
        </article>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:28px">
            <?php foreach ($categories as $c): ?>
                <a href="<?= e(url('/base-de-conhecimento?cat=' . rawurlencode((string) $c['slug']))) ?>" style="display:block;background:#fff;border:1px solid #e6e8eb;border-radius:16px;padding:16px;color:inherit">
                    <strong><?= e((string) $c['title']) ?></strong>
                    <p style="margin:6px 0 0;color:#697078;font-size:13px"><?= e((string) $c['summary']) ?></p>
                    <small><?= (int) $c['article_count'] ?> artigos</small>
                </a>
            <?php endforeach; ?>
        </div>
        <div style="display:grid;gap:10px">
            <?php if ($articles === []): ?>
                <p>Nenhum artigo encontrado.</p>
            <?php endif; ?>
            <?php foreach ($articles as $a): ?>
                <a href="<?= e(url('/base-de-conhecimento?slug=' . rawurlencode((string) $a['slug']))) ?>" style="display:block;background:#fff;border:1px solid #e6e8eb;border-radius:14px;padding:14px 16px;color:inherit">
                    <strong><?= e((string) $a['title']) ?></strong>
                    <p style="margin:4px 0 0;color:#697078;font-size:13px"><?= e((string) $a['summary']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
