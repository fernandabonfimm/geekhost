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
    <p class="kb-crumb"><a href="<?= e(url('/')) ?>">Início</a> / <a href="<?= e(url('/base-de-conhecimento')) ?>">Base de conhecimento</a><?php if ($article): ?> / <?= e((string) $article['category_title']) ?><?php endif; ?></p>
    <?php if (!$article): ?>
    <h1 style="margin:0 0 8px">Base de conhecimento</h1>
    <p style="margin:0 0 20px;color:#697078">Como entrar no painel do cliente, pagar, cadastrar domínio, criar site, entrar no DirectAdmin e criar banco de dados.</p>
    <form method="get" action="<?= e(url('/base-de-conhecimento')) ?>" style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar um assunto" style="flex:1;min-width:220px;min-height:44px;border:1px solid #e3e7ec;border-radius:12px;padding:0 14px">
        <button class="gh-btn gh-btn-primary" type="submit" style="background:#01e152;border:0;border-radius:999px;padding:0 18px;font-weight:800">Pesquisar</button>
    </form>
    <?php endif; ?>
    <?php if ($article):
        $presented = $kb->presentBody((string) $article['body']);
        $minuteLabel = (int) $presented['minutes'] === 1 ? '1 min de leitura' : ((int) $presented['minutes'] . ' min de leitura');
        ?>
        <article class="kb-doc">
            <header class="kb-doc-head">
                <h1><?= e((string) $article['title']) ?></h1>
                <div class="kb-doc-meta">
                    <span class="kb-doc-pill"><?= e((string) $article['category_title']) ?></span>
                    <span class="kb-doc-time"><?= e($minuteLabel) ?></span>
                    <button type="button" class="kb-doc-print" onclick="window.print()">Imprimir</button>
                </div>
            </header>
            <div class="kb-doc-layout">
                <div class="kb-doc-card">
                    <div class="kb-doc-body"><?= $presented['html'] ?></div>
                    <footer class="kb-feedback" data-kb-feedback>
                        <p>Este artigo foi útil?</p>
                        <div class="kb-feedback-actions">
                            <button type="button" data-kb-yes>Sim</button>
                            <button type="button" data-kb-no>Não</button>
                        </div>
                        <p class="kb-feedback-thanks" data-kb-thanks hidden>Obrigado. Isso ajuda a melhorar os artigos do painel do cliente.</p>
                        <div class="kb-feedback-ticket" data-kb-ticket hidden>
                            <p>Se não resolveu, abra um ticket no painel do cliente e conte em que passo parou.</p>
                            <a class="button button-lime" href="<?= e(url('/painel/suporte')) ?>">Abrir ticket</a>
                        </div>
                    </footer>
                </div>
                <?php if ($presented['toc'] !== []): ?>
                    <aside class="kb-doc-toc">
                        <p>Nesta página</p>
                        <ol>
                            <?php foreach ($presented['toc'] as $item): ?>
                                <li><a href="#<?= e($item['id']) ?>"><?= e($item['title']) ?></a></li>
                            <?php endforeach; ?>
                        </ol>
                    </aside>
                <?php endif; ?>
            </div>
        </article>
        <script>
            (function () {
                var box = document.querySelector('[data-kb-feedback]');
                if (!box) return;
                var thanks = box.querySelector('[data-kb-thanks]');
                var ticket = box.querySelector('[data-kb-ticket]');
                box.querySelector('[data-kb-yes]').addEventListener('click', function () {
                    thanks.hidden = false;
                    ticket.hidden = true;
                });
                box.querySelector('[data-kb-no]').addEventListener('click', function () {
                    thanks.hidden = true;
                    ticket.hidden = false;
                });
            }());
        </script>
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
