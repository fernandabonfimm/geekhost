<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';
require_once dirname(__DIR__) . '/includes/ProvisioningService.php';
require_once dirname(__DIR__) . '/includes/DirectAdminClient.php';

$gate = new AdminGate();
try {
    $ctx = $gate->requireAdmin();
} catch (Throwable $e) {
    $ctx = null;
}
$svc = new ProvisioningService();
$da = new DirectAdminClient();

$targets = [
    'open_da' => '/',
    'open_pma' => '/CMD_DB',
    'open_files' => '/CMD_FILE_MANAGER',
];

if ($ctx && in_array('hospedagens', $gate->pagesFor($ctx['admin']), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $_SESSION['admin_notice'] = 'Sessão expirada. Recarregue a página.';
        header('Location: ' . url('/admin/hospedagens'));
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $publicId = (string) ($_POST['account'] ?? '');
    try {
        if (isset($targets[$action])) {
            $login = $svc->directAdminLoginUrl($publicId, $targets[$action]);
            if ($login === null) {
                $_SESSION['admin_notice'] = 'DirectAdmin está em dry-run ou sem credencial. Desligue DIRECTADMIN_DRY_RUN para abrir o painel do cliente.';
                header('Location: ' . url('/admin/hospedagens'));
                exit;
            }
            header('Location: ' . $login);
            exit;
        }
        $accounts = $svc->listAccountsForAdmin(500);
        $match = null;
        foreach ($accounts as $row) {
            if ((string) ($row['id'] ?? '') === $publicId) {
                $match = $row;
                break;
            }
        }
        if (!$match) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        $userId = (int) $match['userId'];
        if ($action === 'suspend') {
            $svc->adminSuspendAccount($publicId);
            $_SESSION['admin_notice'] = 'Conta ' . $match['daUsername'] . ' suspensa no DirectAdmin.';
        } elseif ($action === 'unsuspend') {
            $svc->adminUnsuspendAccount($publicId);
            $_SESSION['admin_notice'] = 'Conta ' . $match['daUsername'] . ' reativada no DirectAdmin.';
        } elseif ($action === 'backup') {
            $svc->backupUserAccount($userId);
            $_SESSION['admin_notice'] = 'Backup da conta ' . $match['daUsername'] . ' solicitado no servidor da revenda.';
        } elseif ($action === 'ssl') {
            $domain = trim((string) ($match['primaryDomain'] ?? ''));
            if ($domain === '') {
                throw new InvalidArgumentException('Cadastre um domínio antes de emitir o SSL.');
            }
            $da->provisionSsl($domain);
            $_SESSION['admin_notice'] = 'Pedido de SSL enviado para ' . $domain . '.';
        } elseif ($action === 'domain') {
            $svc->adminAttachDomain($publicId, (string) ($_POST['domain'] ?? ''));
            $_SESSION['admin_notice'] = 'Domínio vinculado. Se a conta ainda não existia no DirectAdmin, o provisionamento entra na fila.';
        } else {
            throw new InvalidArgumentException('Ação desconhecida.');
        }
        $gate->audit((int) $ctx['admin']['id'], $userId, 'hosting_' . $action, 'hosting_account', $publicId, 'ok', []);
    } catch (Throwable $e) {
        $_SESSION['admin_notice'] = $e->getMessage();
    }
    header('Location: ' . url('/admin/hospedagens'));
    exit;
}

$adminTitle = 'Hospedagens';
$adminSection = 'hospedagens';
$adminLead = 'Contas DirectAdmin da revenda: painel do cliente, phpMyAdmin, arquivos, bancos, SSL e backup.';
require __DIR__ . '/_layout.php';

$accounts = $svc->listAccountsForAdmin(200);
$probe = $da->isConfigured() ? $da->probe() : ['ok' => false, 'message' => 'DA não configurado no .env'];
$dbAccount = trim((string) ($_GET['bancos'] ?? ''));
$dbList = [];
$dbError = '';
if ($dbAccount !== '') {
    try {
        $dbList = $svc->databasesForAccount($dbAccount);
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}
$resellerUsers = [];
$resellerError = '';
if ($da->isConfigured() && !$da->isDryRun()) {
    try {
        $rawUsers = $da->listUsers();
        if (isset($rawUsers['users']) && is_array($rawUsers['users'])) {
            $list = $rawUsers['users'];
            $resellerUsers = array_is_list($list) ? array_map('strval', $list) : array_map('strval', array_keys($list));
        } else {
            foreach ($rawUsers as $key => $value) {
                if (!is_string($key) || in_array($key, ['error', 'text', 'details', '_http_status', 'raw'], true)) {
                    continue;
                }
                $resellerUsers[] = $key;
            }
        }
    } catch (Throwable $e) {
        $resellerError = $e->getMessage();
    }
}
?>
<div class="gh-alert">
    <div>
        DirectAdmin: <?= !empty($probe['ok']) ? 'conectado' : 'indisponível' ?>
        — <?= e((string) ($probe['message'] ?? '')) ?>
        · Provisioning: <?= ProvisioningService::provisioningEnabled() ? 'ON' : 'OFF' ?>
        · Dry-run: <?= $da->isDryRun() ? 'ON' : 'OFF' ?>
    </div>
</div>
<?php if ($dbAccount !== ''): ?>
<article class="admin-card">
    <h2>Bancos de <?= e($dbAccount) ?></h2>
    <?php if ($dbError !== ''): ?>
        <p><?= e($dbError) ?></p>
    <?php elseif ($da->isDryRun()): ?>
        <p>Dry-run ligado: a lista de bancos só aparece com DIRECTADMIN_DRY_RUN=0. O botão phpMyAdmin abre o gerenciador no DirectAdmin.</p>
    <?php elseif ($dbList === []): ?>
        <p>Nenhum banco retornado por esta conta.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($dbList as $name): ?><li><code><?= e($name) ?></code></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
<?php endif; ?>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>DA user</th><th>Cliente</th><th>Plano</th><th>Domínio</th><th>Status</th><th>Ações da revenda</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($accounts === []): ?>
                <tr><td colspan="6" class="admin-empty">Nenhuma conta provisionada ainda.</td></tr>
            <?php else: foreach ($accounts as $a): ?>
                <tr>
                    <td><code><?= e((string) $a['daUsername']) ?></code></td>
                    <td><?= e((string) ($a['userEmail'] ?? $a['userId'])) ?></td>
                    <td><?= e((string) $a['planCode']) ?></td>
                    <td><?= e((string) ($a['primaryDomain'] ?? '—')) ?></td>
                    <td><span class="admin-badge"><?= e((string) $a['status']) ?></span></td>
                    <td>
                        <form method="post" class="admin-row-actions">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="account" value="<?= e((string) $a['id']) ?>">
                            <button name="action" value="open_da" type="submit">DirectAdmin</button>
                            <button name="action" value="open_pma" type="submit">phpMyAdmin</button>
                            <button name="action" value="open_files" type="submit">Arquivos</button>
                            <button name="action" value="backup" type="submit">Backup</button>
                            <button name="action" value="ssl" type="submit">SSL</button>
                            <?php if ((string) $a['status'] === 'suspended'): ?>
                                <button name="action" value="unsuspend" type="submit">Reativar</button>
                            <?php else: ?>
                                <button name="action" value="suspend" type="submit">Suspender</button>
                            <?php endif; ?>
                        </form>
                        <p><a href="<?= e(url('/admin/hospedagens?bancos=' . rawurlencode((string) $a['id']))) ?>">Ver bancos</a></p>
                        <form method="post" class="admin-row-actions">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="account" value="<?= e((string) $a['id']) ?>">
                            <input type="hidden" name="action" value="domain">
                            <input name="domain" placeholder="novodominio.com.br" maxlength="180" required>
                            <button type="submit">Cadastrar domínio</button>
                        </form>
                        <?php if (!empty($a['lastError'])): ?><small><?= e((string) $a['lastError']) ?></small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<article class="admin-card">
    <h2>Usuários na revenda DirectAdmin</h2>
    <?php if ($da->isDryRun()): ?>
        <p>Dry-run ligado: a lista ao vivo da revenda fica desligada até DIRECTADMIN_DRY_RUN=0.</p>
    <?php elseif ($resellerError !== ''): ?>
        <p><?= e($resellerError) ?></p>
    <?php elseif ($resellerUsers === []): ?>
        <p>Nenhum usuário retornado pela API.</p>
    <?php else: ?>
        <p><?= count($resellerUsers) ?> usuário(s): <?= e(implode(', ', $resellerUsers)) ?></p>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
