<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';
require_once dirname(__DIR__) . '/includes/ProvisioningService.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$gate = new AdminGate();
try {
    $ctx = $gate->requireAdmin();
} catch (Throwable $e) {
    $ctx = null;
}
if ($ctx && in_array('clientes', $gate->pagesFor($ctx['admin']), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $_SESSION['admin_notice'] = 'Sessão expirada. Recarregue a página.';
    } else {
        try {
            $svc = new ProvisioningService();
            $created = $svc->createClientForAdmin(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['plan'] ?? 'light'),
                (string) ($_POST['domain'] ?? '')
            );
            $notice = 'Cliente ' . $created['daUsername'] . ' (' . $created['status'] . ').';
            if (!empty($created['panelPassword'])) {
                $login = geekhost_public_url('/painel/login');
                $html = '<p>Sua hospedagem GeekHost foi aberta.</p>'
                    . '<p>Painel: ' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '<br>E-mail: '
                    . htmlspecialchars($created['email'], ENT_QUOTES, 'UTF-8') . '<br>Senha: '
                    . htmlspecialchars((string) $created['panelPassword'], ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>Se você ainda não tem domínio, compre em qualquer registrador e cadastre em Domínios no painel.</p>';
                $sent = Mailer::send($created['email'], 'Sua conta GeekHost', $html, false);
                $notice .= $sent
                    ? ' E-mail enviado.'
                    : ' O e-mail não saiu. Senha do painel: ' . $created['panelPassword'];
                if ($sent) {
                    $notice .= ' Senha do painel: ' . $created['panelPassword'];
                }
            } else {
                $notice .= ' Conta já existia; a senha do cliente não foi alterada.';
            }
            if ($created['status'] === 'blocked_no_domain') {
                $notice .= ' A hospedagem no DirectAdmin começa quando o domínio for cadastrado.';
            }
            $_SESSION['admin_notice'] = $notice;
            $gate->audit((int) $ctx['admin']['id'], (int) $created['userId'], 'client_created', 'user', (string) $created['userId'], 'ok', [
                'email' => $created['email'],
                'status' => $created['status'],
            ]);
        } catch (Throwable $e) {
            $_SESSION['admin_notice'] = $e->getMessage();
        }
    }
    header('Location: ' . url('/admin/clientes'));
    exit;
}

$adminTitle = 'Clientes';
$adminSection = 'clientes';
$adminLead = 'Usuários do painel GeekHost. Crie a conta e a hospedagem; o domínio pode entrar depois.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = geekhost_db();
$q = trim((string) ($_GET['q'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? ''));

$sql = 'SELECT u.public_id, u.email, u.hosting_status, u.created_at, u.suspended_at,
               (SELECT COUNT(*) FROM hosting_accounts h WHERE h.user_id = u.id) AS hosting_count,
               (SELECT COALESCE(SUM(amount_cents),0) FROM orders o WHERE o.user_id = u.id AND o.status = \'paid_pending_provisioning\') AS spent_cents
        FROM users u WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND u.email LIKE :q';
    $params[':q'] = '%' . $q . '%';
}
if ($filter === 'active') {
    $sql .= " AND u.hosting_status = 'active'";
} elseif ($filter === 'suspended') {
    $sql .= " AND u.hosting_status = 'suspended'";
} elseif ($filter === 'with_hosting') {
    $sql .= ' AND EXISTS (SELECT 1 FROM hosting_accounts h WHERE h.user_id = u.id AND h.status = \'active\')';
} elseif ($filter === 'without_hosting') {
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM hosting_accounts h WHERE h.user_id = u.id)';
}
$sql .= ' ORDER BY u.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
$fmt = static fn (int $c) => 'R$ ' . number_format($c / 100, 2, ',', '.');
?>
<article class="admin-card">
    <h2>Novo cliente</h2>
    <form class="admin-form" method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="admin-form-grid">
            <label>Nome
                <input name="name" required maxlength="120">
            </label>
            <label>E-mail
                <input name="email" type="email" required maxlength="190" autocomplete="off">
            </label>
            <label>Senha do painel
                <?php geekhost_password_input('password', [
                    'minlength' => 8,
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Em branco gera uma senha',
                ]); ?>
            </label>
            <label>Plano
                <select name="plan">
                    <?php foreach (['light', 'empresa', 'premium'] as $code):
                        $plan = geekhost_plan($code);
                        if (!$plan) continue; ?>
                        <option value="<?= e($code) ?>"><?= e((string) $plan['name']) ?> · <?= (int) geekhost_plan_domain_limit($code) ?> domínio(s)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Domínio (opcional)
                <input name="domain" placeholder="meusite.com.br" maxlength="180">
            </label>
        </div>
        <button class="gh-btn gh-btn-primary" type="submit">Criar cliente</button>
    </form>
</article>
<form class="admin-filters" method="get">
    <input type="search" name="q" placeholder="E-mail…" value="<?= e($q) ?>">
    <select name="filter">
        <option value="">Todos</option>
        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Ativos</option>
        <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspensos</option>
        <option value="with_hosting" <?= $filter === 'with_hosting' ? 'selected' : '' ?>>Com hospedagem</option>
        <option value="without_hosting" <?= $filter === 'without_hosting' ? 'selected' : '' ?>>Sem hospedagem</option>
    </select>
    <button class="gh-btn gh-btn-primary" type="submit">Filtrar</button>
</form>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr><th>E-mail</th><th>Status</th><th>Hospedagens</th><th>Total pago</th><th>Cadastro</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="admin-empty">Nenhum cliente.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['email']) ?></td>
                    <td><span class="admin-badge"><?= e((string) $row['hosting_status']) ?></span></td>
                    <td><?= (int) $row['hosting_count'] ?></td>
                    <td><?= e($fmt((int) $row['spent_cents'])) ?></td>
                    <td><?= e((string) $row['created_at']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
