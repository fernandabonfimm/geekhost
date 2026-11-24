<?php

declare(strict_types=1);

$adminTitle = 'Base de conhecimento';
$adminSection = 'conhecimento';
$adminLead = 'Artigos públicos. Edite, remova ou inclua conteúdo.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/KnowledgeBase.php';

$kb = new KnowledgeBase();
$notice = '';
$error = '';
$editId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        try {
            if ($action === 'delete') {
                $kb->delete((int) ($_POST['id'] ?? 0));
                $notice = 'Artigo removido.';
                $editId = 0;
            } else {
                $editId = $kb->save([
                    'title' => (string) ($_POST['title'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'summary' => (string) ($_POST['summary'] ?? ''),
                    'body' => (string) ($_POST['body'] ?? ''),
                    'category_id' => (int) ($_POST['category_id'] ?? 0),
                    'published' => isset($_POST['published']),
                ], (int) ($_POST['id'] ?? 0) ?: null);
                $notice = 'Artigo salvo.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$editing = $editId > 0 ? $kb->findById($editId) : null;
$categories = $kb->categories();
$articles = $kb->adminArticles();
?>
<?php if ($notice): ?><div class="admin-card" style="margin-bottom:12px"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-card" style="margin-bottom:12px;color:#b42318"><?= e($error) ?></div><?php endif; ?>
<div class="admin-grid-2">
    <article class="admin-card">
        <h2><?= $editing ? 'Editar artigo' : 'Novo artigo' ?></h2>
        <form method="post" class="admin-form" style="display:grid;gap:10px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <label>Título<input name="title" required value="<?= e((string) ($editing['title'] ?? '')) ?>"></label>
            <label>Slug<input name="slug" value="<?= e((string) ($editing['slug'] ?? '')) ?>" placeholder="gerado do título se vazio"></label>
            <label>Categoria
                <select name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Resumo<input name="summary" value="<?= e((string) ($editing['summary'] ?? '')) ?>"></label>
            <label>Conteúdo<textarea name="body" rows="10" required><?= e((string) ($editing['body'] ?? '')) ?></textarea></label>
            <label><input type="checkbox" name="published" <?= !isset($editing['published']) || (int) $editing['published'] === 1 ? 'checked' : '' ?>> Publicado</label>
            <button class="gh-btn gh-btn-primary" type="submit">Salvar</button>
        </form>
    </article>
    <article class="admin-card">
        <h2>Artigos</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Título</th><th>Categoria</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($articles as $a): ?>
                    <tr>
                        <td><?= e((string) $a['title']) ?><br><small><?= (int) $a['published'] === 1 ? 'publicado' : 'rascunho' ?></small></td>
                        <td><?= e((string) $a['category_title']) ?></td>
                        <td>
                            <a href="<?= e(url('/admin/conhecimento?id=' . (int) $a['id'])) ?>">Editar</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Remover este artigo?')">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button type="submit">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><a href="<?= e(url('/base-de-conhecimento')) ?>" target="_blank" rel="noopener">Abrir página pública</a></p>
    </article>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
