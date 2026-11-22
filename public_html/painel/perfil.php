<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['action'] ?? '') === 'revoke_session') {
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/Auth.php';
    require_once dirname(__DIR__) . '/includes/csrf.php';
    $earlyAuth = new Auth();
    $earlyUser = $earlyAuth->user();
    if ($earlyUser && csrf_validate($_POST['csrf'] ?? null)) {
        $ended = $earlyAuth->revokeSession((int) $earlyUser['id'], (int) ($_POST['session_id'] ?? 0));
        header('Location: ' . url($ended ? '/painel/login' : '/painel/perfil?tab=seguranca&sessao=1'));
        exit;
    }
}

$panelTitle = 'Perfil';
$panelSection = 'perfil';
$panelLead = 'Dados da conta, endereço, segurança e preferências.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/NotificationService.php';

$message = '';
$error = '';
$activeTab = (string) ($_GET['tab'] ?? 'dados');
if (!in_array($activeTab, ['dados', 'endereco', 'seguranca', 'preferencias'], true)) {
    $activeTab = 'dados';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'revoke_session') {
                $activeTab = 'seguranca';
                $error = 'Sessão expirada.';
            } elseif ($action === 'password') {
                $activeTab = 'seguranca';
                $code = trim((string) ($_POST['code'] ?? ''));
                if ($code === '') {
                    $auth->requestPasswordChangeCode((int) $panelUser['id'], (string) ($_POST['current_password'] ?? ''));
                    $message = 'Enviamos um código para o seu e-mail. Digite-o no campo abaixo, junto com a nova senha, para concluir.';
                } else {
                    $auth->confirmPasswordChange(
                        (int) $panelUser['id'],
                        (string) ($_POST['current_password'] ?? ''),
                        (string) ($_POST['new_password'] ?? ''),
                        $code
                    );
                    $message = 'Senha atualizada.';
                    (new NotificationService())->notify(
                        (int) $panelUser['id'],
                        'profile_updated',
                        'Senha atualizada',
                        'A senha da sua conta GeekHost foi alterada com sucesso.',
                        url('/painel/perfil')
                    );
                }
            } else {
                $activeTab = (string) ($_POST['tab'] ?? 'dados');
                if (!in_array($activeTab, ['dados', 'endereco', 'preferencias'], true)) {
                    $activeTab = 'dados';
                }
                $photoPath = $panelUser['photoPath'] ?? null;
                if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                    $ext = strtolower(pathinfo((string) $_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        throw new InvalidArgumentException('Foto deve ser JPG, PNG ou WEBP.');
                    }
                    $dir = dirname(__DIR__) . '/assets/uploads/avatars';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = 'u' . $panelUser['id'] . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $dir . '/' . $filename;
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        throw new RuntimeException('Falha ao salvar a foto.');
                    }
                    $photoPath = 'assets/uploads/avatars/' . $filename;
                }
                $postedEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
                $emailNotice = '';
                if ($postedEmail !== '' && $postedEmail !== strtolower((string) $panelUser['email'])) {
                    $auth->requestEmailChange((int) $panelUser['id'], $postedEmail);
                    $emailNotice = 'Enviamos um código para ' . $postedEmail . '. Abra Confirmar e-mail e digite o código para concluir a troca.';
                }
                $panelUser = $auth->updateProfile((int) $panelUser['id'], [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'email' => (string) $panelUser['email'],
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'mobilePhone' => (string) ($_POST['mobilePhone'] ?? ''),
                    'postalCode' => (string) ($_POST['postalCode'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                    'addressNumber' => (string) ($_POST['addressNumber'] ?? ''),
                    'complement' => (string) ($_POST['complement'] ?? ''),
                    'province' => (string) ($_POST['province'] ?? ''),
                    'city' => (string) ($_POST['city'] ?? ''),
                ], $photoPath);
                $message = $emailNotice !== '' ? $emailNotice : 'Perfil atualizado.';
                (new NotificationService())->notify(
                    (int) $panelUser['id'],
                    'profile_updated',
                    'Perfil atualizado',
                    'Seus dados de perfil foram salvos com sucesso.',
                    url('/painel/perfil')
                );
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$nameParts = preg_split('/\s+/', trim((string) ($panelUser['name'] ?? ''))) ?: [];
$initials = strtoupper(substr((string) ($nameParts[0] ?? 'G'), 0, 1) . substr((string) ($nameParts[1] ?? 'H'), 0, 1));
?>
<section class="panel-profile" data-profile-tabs>
    <?php if ($message): ?><div class="gh-alert gh-alert-ok"><i data-lucide="check-circle-2"></i><div><?= e($message) ?></div></div><?php endif; ?>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><i data-lucide="triangle-alert"></i><div><?= e($error) ?></div></div><?php endif; ?>

    <div class="profile-tabs" role="tablist">
        <button type="button" class="profile-tab <?= $activeTab === 'dados' ? 'is-active' : '' ?>" data-profile-tab="dados">Dados pessoais</button>
        <button type="button" class="profile-tab <?= $activeTab === 'endereco' ? 'is-active' : '' ?>" data-profile-tab="endereco">Endereço</button>
        <button type="button" class="profile-tab <?= $activeTab === 'seguranca' ? 'is-active' : '' ?>" data-profile-tab="seguranca">Segurança</button>
        <button type="button" class="profile-tab <?= $activeTab === 'preferencias' ? 'is-active' : '' ?>" data-profile-tab="preferencias">Preferências</button>
    </div>

    <div class="profile-layout">
        <aside class="gh-card profile-summary">
            <?php if (!empty($panelUser['photoPath'])): ?>
                <img class="profile-avatar" src="<?= e(asset((string) $panelUser['photoPath'])) ?>" alt="" width="96" height="96">
            <?php else: ?>
                <span class="profile-avatar-fallback"><?= e($initials) ?></span>
            <?php endif; ?>
            <strong><?= e((string) ($panelUser['name'] ?: 'Cliente')) ?></strong>
            <span style="color:var(--gh-muted);font-size:13px"><?= e((string) $panelUser['email']) ?></span>
            <span class="gh-badge gh-badge-ok">Cliente GeekHost</span>
            <a class="gh-link" href="<?= e(url('/painel/carteira')) ?>">Ir para carteira →</a>
        </aside>

        <div>
            <form method="post" enctype="multipart/form-data" class="gh-card profile-panel" data-profile-panel="dados" <?= $activeTab === 'dados' ? '' : 'hidden' ?>>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="profile">
                <input type="hidden" name="tab" value="dados">
                <h2 style="margin:0 0 14px;font-size:16px">Informações pessoais</h2>
                <div class="gh-form">
                    <label>Foto de perfil<input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></label>
                    <div class="checkout-grid-2">
                        <label>Nome completo<input name="name" value="<?= e((string) $panelUser['name']) ?>" required></label>
                        <label>E-mail<input type="email" name="email" value="<?= e((string) $panelUser['email']) ?>" required></label>
                    </div>
                    <div class="checkout-grid-2">
                        <label>Telefone<input name="phone" value="<?= e((string) $panelUser['phone']) ?>"></label>
                        <label>Celular<input name="mobilePhone" value="<?= e((string) $panelUser['mobilePhone']) ?>"></label>
                    </div>
                    <div class="profile-panel-actions">
                        <button class="gh-btn gh-btn-primary" type="submit"><i data-lucide="check"></i> Salvar alterações</button>
                    </div>
                </div>
            </form>

            <form method="post" enctype="multipart/form-data" class="gh-card profile-panel" data-profile-panel="endereco" <?= $activeTab === 'endereco' ? '' : 'hidden' ?>>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="profile">
                <input type="hidden" name="tab" value="endereco">
                <input type="hidden" name="name" value="<?= e((string) $panelUser['name']) ?>">
                <input type="hidden" name="email" value="<?= e((string) $panelUser['email']) ?>">
                <input type="hidden" name="phone" value="<?= e((string) $panelUser['phone']) ?>">
                <input type="hidden" name="mobilePhone" value="<?= e((string) $panelUser['mobilePhone']) ?>">
                <h2 style="margin:0 0 14px;font-size:16px">Endereço</h2>
                <div class="gh-form">
                    <div class="checkout-grid-2">
                        <label>CEP<input name="postalCode" value="<?= e((string) $panelUser['postalCode']) ?>"></label>
                        <label>Número<input name="addressNumber" value="<?= e((string) $panelUser['addressNumber']) ?>"></label>
                    </div>
                    <label>Endereço<input name="address" value="<?= e((string) $panelUser['address']) ?>"></label>
                    <label>Complemento<input name="complement" value="<?= e((string) $panelUser['complement']) ?>"></label>
                    <div class="checkout-grid-2">
                        <label>Cidade<input name="city" value="<?= e((string) $panelUser['city']) ?>"></label>
                        <label>UF<input name="province" value="<?= e((string) $panelUser['province']) ?>" maxlength="2"></label>
                    </div>
                    <div class="profile-panel-actions">
                        <button class="gh-btn gh-btn-primary" type="submit"><i data-lucide="check"></i> Salvar endereço</button>
                    </div>
                </div>
            </form>

            <form method="post" class="gh-card profile-panel" data-profile-panel="seguranca" <?= $activeTab === 'seguranca' ? '' : 'hidden' ?>>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="password">
                <h2 style="margin:0 0 14px;font-size:16px">Segurança</h2>
                <div class="gh-form">
                    <label>Senha atual<?php geekhost_password_input('current_password', ['required' => true, 'autocomplete' => 'current-password']); ?></label>
                    <label>Nova senha<?php geekhost_password_input('new_password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']); ?></label>
                    <label>Código do e-mail<input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="Deixe em branco para receber o código"></label>
                    <p style="margin:0;color:var(--gh-muted);font-size:13px">Primeiro enviamos um código para o seu e-mail. Depois cole o código aqui e salve. O login não pede esse código.</p>
                    <p style="margin:0;color:var(--gh-muted);font-size:13px">A senha fica só como hash. Ela não pode ser lida de volta, nem pela equipe.</p>
                    <div class="profile-panel-actions">
                        <button class="gh-btn gh-btn-dark" type="submit">Atualizar senha</button>
                    </div>
                </div>
            </form>

            <div class="gh-card profile-panel" data-profile-panel="seguranca" <?= $activeTab === 'seguranca' ? '' : 'hidden' ?>>
                <h2 style="margin:0 0 8px;font-size:16px">Dispositivos com sessão</h2>
                <p style="margin:0 0 14px;color:var(--gh-muted);font-size:13px">Cada login dura 7 horas neste aparelho e depois pede a senha de novo.</p>
                <?php if (($_GET['sessao'] ?? '') === '1'): ?><div class="gh-alert gh-alert-ok"><div>Sessão encerrada.</div></div><?php endif; ?>
                <?php
                $deviceSessions = $auth->sessionsFor((int) $panelUser['id']);
                if ($deviceSessions === []): ?>
                    <p style="margin:0;color:var(--gh-muted)">Nenhuma sessão ativa.</p>
                <?php else: ?>
                    <div class="gh-form">
                        <?php foreach ($deviceSessions as $device): ?>
                            <form method="post" action="<?= e(url('/painel/perfil')) ?>" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="revoke_session">
                                <input type="hidden" name="session_id" value="<?= (int) $device['id'] ?>">
                                <div>
                                    <strong><?= e((string) $device['device']) ?><?= !empty($device['current']) ? ' · este aparelho' : '' ?></strong>
                                    <div style="color:var(--gh-muted);font-size:12px">IP <?= e((string) $device['ip']) ?> · expira <?= e((string) $device['expiresAt']) ?></div>
                                </div>
                                <button class="gh-btn gh-btn-secondary" type="submit"><?= !empty($device['current']) ? 'Sair deste aparelho' : 'Encerrar' ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gh-card profile-panel" data-profile-panel="preferencias" <?= $activeTab === 'preferencias' ? '' : 'hidden' ?>>
                <h2 style="margin:0 0 14px;font-size:16px">Preferências</h2>
                <p style="margin:0 0 12px;color:var(--gh-muted);font-size:14px;line-height:1.5">
                    Preferências avançadas de notificação serão liberadas quando o backend correspondente estiver disponível.
                    Por enquanto, use o menu da conta e o WhatsApp de suporte.
                </p>
                <?php if (!empty($companyWhatsapp)): ?>
                    <a class="gh-btn gh-btn-primary" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">Falar com suporte</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
