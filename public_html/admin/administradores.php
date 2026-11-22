<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';

$gate = new AdminGate();
try {
    $ctx = $gate->requireAdmin();
} catch (Throwable $e) {
    $ctx = null;
}
$actor = $ctx['admin'] ?? null;
$actorUser = $ctx['user'] ?? null;

if ($actor && $actorUser && $_SERVER['REQUEST_METHOD'] === 'POST' && $gate->isSuper($actor)) {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $_SESSION['admin_notice'] = 'Sessão expirada. Recarregue a página.';
        header('Location: ' . url('/admin/administradores'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $pages = isset($_POST['pages']) && is_array($_POST['pages']) ? array_map('strval', $_POST['pages']) : [];
            $result = $gate->createStaff(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                $pages,
                (int) $actor['id'],
                (int) $actorUser['id']
            );
            $mail = $result['emailed']
                ? 'E-mail com as credenciais enviado.'
                : 'O servidor não enviou o e-mail. Passe a senha por um canal seguro.';
            $_SESSION['admin_notice'] = 'Admin criado para ' . $result['email'] . '. ' . $mail . ' Senha inicial: ' . $result['password'];
        } elseif ($action === 'pages') {
            $pages = isset($_POST['pages']) && is_array($_POST['pages']) ? array_map('strval', $_POST['pages']) : [];
            $gate->updateStaffPages((int) ($_POST['admin_id'] ?? 0), $pages, (int) $actor['id'], (int) $actorUser['id']);
            $_SESSION['admin_notice'] = 'Páginas do admin atualizadas.';
        } elseif ($action === 'status') {
            $gate->setStaffStatus(
                (int) ($_POST['admin_id'] ?? 0),
                (string) ($_POST['status'] ?? ''),
                (int) $actor['id'],
                (int) $actorUser['id']
            );
            $_SESSION['admin_notice'] = 'Status do admin atualizado.';
        }
    } catch (Throwable $e) {
        $_SESSION['admin_notice'] = $e->getMessage();
    }
    header('Location: ' . url('/admin/administradores'));
    exit;
}

$adminTitle = 'Administradores';
$adminSection = 'administradores';
$adminLead = 'Crie acessos da equipe e escolha quais páginas cada um pode ver.';
require __DIR__ . '/_layout.php';

$staff = $gate->listStaff();
$isSuper = $gate->isSuper($adminRow ?? []);
?>
<?php if ($isSuper): ?>
<article class="admin-card">
    <h2>Novo admin</h2>
    <form class="admin-form" method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="admin-form-grid">
            <label>Nome
                <input name="name" required maxlength="120" autocomplete="name">
            </label>
            <label>E-mail
                <input name="email" type="email" required maxlength="190" autocomplete="off">
            </label>
            <label>Senha
                <?php geekhost_password_input('password', [
                    'required' => false,
                    'minlength' => 8,
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Deixe em branco para gerar',
                ]); ?>
            </label>
        </div>
        <p>Páginas que este admin pode visualizar</p>
        <div class="admin-checks">
            <?php foreach (AdminGate::PAGES as $key => $label): ?>
                <label>
                    <input type="checkbox" name="pages[]" value="<?= e($key) ?>" <?= $key === 'overview' ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="gh-btn gh-btn-primary" type="submit">Criar e enviar credenciais</button>
    </form>
</article>
<?php endif; ?>

<article class="admin-card">
    <h2>Equipe</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Status</th><th>Páginas</th></tr>
            </thead>
            <tbody>
            <?php if ($staff === []): ?>
                <tr><td colspan="5" class="admin-empty">Nenhum admin cadastrado.</td></tr>
            <?php else: foreach ($staff as $member):
                $pages = $gate->isSuper($member) ? array_keys(AdminGate::PAGES) : ($member['pages'] ?? $gate->pagesFor($member));
                ?>
                <tr>
                    <td><?= e((string) $member['name']) ?></td>
                    <td><?= e((string) $member['email']) ?></td>
                    <td><span class="admin-badge"><?= e((string) $member['role']) ?></span></td>
                    <td><?= e((string) $member['status']) ?></td>
                    <td>
                        <?php if ($gate->isSuper($member)): ?>
                            Acesso total
                        <?php elseif ($isSuper): ?>
                            <form method="post" class="admin-stack">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="pages">
                                <input type="hidden" name="admin_id" value="<?= (int) $member['id'] ?>">
                                <div class="admin-checks">
                                    <?php foreach (AdminGate::PAGES as $key => $label): ?>
                                        <label>
                                            <input type="checkbox" name="pages[]" value="<?= e($key) ?>" <?= in_array($key, $pages, true) ? 'checked' : '' ?>>
                                            <?= e($label) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="admin-row-actions">
                                    <button type="submit">Salvar páginas</button>
                                </div>
                            </form>
                            <form method="post" class="admin-row-actions">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="admin_id" value="<?= (int) $member['id'] ?>">
                                <input type="hidden" name="status" value="<?= ($member['status'] ?? '') === 'active' ? 'suspended' : 'active' ?>">
                                <button type="submit"><?= ($member['status'] ?? '') === 'active' ? 'Desativar' : 'Reativar' ?></button>
                            </form>
                        <?php else: ?>
                            <?= e(implode(', ', $pages)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
