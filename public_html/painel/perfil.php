<?php

declare(strict_types=1);

$panelTitle = 'Perfil';
$panelSection = 'perfil';
require __DIR__ . '/_layout.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'password') {
                $auth->changePassword((int) $panelUser['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''));
                $message = 'Senha atualizada.';
            } else {
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
                $panelUser = $auth->updateProfile((int) $panelUser['id'], [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'mobilePhone' => (string) ($_POST['mobilePhone'] ?? ''),
                    'postalCode' => (string) ($_POST['postalCode'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                    'addressNumber' => (string) ($_POST['addressNumber'] ?? ''),
                    'complement' => (string) ($_POST['complement'] ?? ''),
                    'province' => (string) ($_POST['province'] ?? ''),
                    'city' => (string) ($_POST['city'] ?? ''),
                ], $photoPath);
                $message = 'Perfil atualizado.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<section class="panel-profile">
    <p class="panel-lead">Dados da conta. Cartão de renovação fica em <a href="<?= e(url('/painel/carteira')) ?>">Carteira</a>.</p>
    <?php if ($message): ?><p class="panel-alert panel-alert-ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>

    <div class="panel-profile-grid">
        <form method="post" enctype="multipart/form-data" class="panel-form panel-form-wide">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="profile">
            <h2>Dados pessoais</h2>
            <?php if (!empty($panelUser['photoPath'])): ?>
                <img class="panel-avatar" src="<?= e(asset((string) $panelUser['photoPath'])) ?>" alt="" width="88" height="88">
            <?php endif; ?>
            <label>Foto<input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></label>
            <div class="checkout-grid-2">
                <label>Nome<input name="name" value="<?= e((string) $panelUser['name']) ?>" required></label>
                <label>E-mail<input type="email" name="email" value="<?= e((string) $panelUser['email']) ?>" required></label>
            </div>
            <div class="checkout-grid-2">
                <label>Telefone<input name="phone" value="<?= e((string) $panelUser['phone']) ?>"></label>
                <label>Celular<input name="mobilePhone" value="<?= e((string) $panelUser['mobilePhone']) ?>"></label>
            </div>
            <hr class="panel-divider">
            <h2>Endereço</h2>
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
            <button class="button button-lime" type="submit">Salvar perfil</button>
        </form>

        <form method="post" class="panel-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="password">
            <h2>Senha</h2>
            <label>Senha atual<input type="password" name="current_password" required></label>
            <label>Nova senha<input type="password" name="new_password" required minlength="8"></label>
            <button class="button button-dark" type="submit">Atualizar senha</button>
            <a class="button button-outline-dark" href="<?= e(url('/painel/carteira')) ?>">Ir para carteira</a>
        </form>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
