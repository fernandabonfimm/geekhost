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
            } elseif ($action === 'card') {
                require_once dirname(__DIR__) . '/includes/OrderService.php';
                $orders = new OrderService();
                $cardInfo = $orders->updateRecurringCard((int) $panelUser['id'], [
                    'holderName' => (string) ($_POST['cardHolderName'] ?? ''),
                    'number' => (string) ($_POST['cardNumber'] ?? ''),
                    'expiryMonth' => (string) ($_POST['cardExpiryMonth'] ?? ''),
                    'expiryYear' => (string) ($_POST['cardExpiryYear'] ?? ''),
                    'ccv' => (string) ($_POST['cardCcv'] ?? ''),
                ], [
                    'name' => (string) ($_POST['name'] ?? $panelUser['name']),
                    'email' => (string) ($_POST['email'] ?? $panelUser['email']),
                    'cpfCnpj' => (string) ($_POST['cpfCnpj'] ?? $panelUser['cpfCnpj']),
                    'postalCode' => (string) ($_POST['postalCode'] ?? $panelUser['postalCode']),
                    'addressNumber' => (string) ($_POST['addressNumber'] ?? $panelUser['addressNumber']),
                    'phone' => (string) ($_POST['phone'] ?? $panelUser['phone']),
                    'mobilePhone' => (string) ($_POST['mobilePhone'] ?? $panelUser['mobilePhone']),
                ]);
                $message = 'Cartão recorrente atualizado' . (!empty($cardInfo['last4']) ? ' (final ' . $cardInfo['last4'] . ').' : '.');
                $panelUser = $auth->user() ?? $panelUser;
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
    <h1>Meu perfil</h1>
    <?php if ($message): ?><p class="panel-alert panel-alert-ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="panel-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="profile">
        <?php if (!empty($panelUser['photoPath'])): ?>
            <img class="panel-avatar" src="<?= e(asset((string) $panelUser['photoPath'])) ?>" alt="" width="88" height="88">
        <?php endif; ?>
        <label>Foto<input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></label>
        <label>Nome<input name="name" value="<?= e((string) $panelUser['name']) ?>" required></label>
        <label>E-mail<input type="email" name="email" value="<?= e((string) $panelUser['email']) ?>" required></label>
        <label>Telefone<input name="phone" value="<?= e((string) $panelUser['phone']) ?>"></label>
        <label>Celular<input name="mobilePhone" value="<?= e((string) $panelUser['mobilePhone']) ?>"></label>
        <label>CEP<input name="postalCode" value="<?= e((string) $panelUser['postalCode']) ?>"></label>
        <label>Endereço<input name="address" value="<?= e((string) $panelUser['address']) ?>"></label>
        <label>Número<input name="addressNumber" value="<?= e((string) $panelUser['addressNumber']) ?>"></label>
        <label>Complemento<input name="complement" value="<?= e((string) $panelUser['complement']) ?>"></label>
        <label>Cidade<input name="city" value="<?= e((string) $panelUser['city']) ?>"></label>
        <label>UF<input name="province" value="<?= e((string) $panelUser['province']) ?>" maxlength="2"></label>
        <button class="button button-lime" type="submit">Salvar perfil</button>
    </form>

    <form method="post" class="panel-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="password">
        <h2>Alterar senha</h2>
        <label>Senha atual<input type="password" name="current_password" required></label>
        <label>Nova senha<input type="password" name="new_password" required minlength="8"></label>
        <button class="button button-dark" type="submit">Atualizar senha</button>
    </form>

    <form method="post" class="panel-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="card">
        <h2>Cartão para renovação</h2>
        <p class="checkout-note">Atualiza o cartão da assinatura Asaas. Número e CVV não são armazenados na GeekHost.<?= !empty($panelUser['cardLast4']) ? ' Cartão atual: ' . e((string) $panelUser['cardBrand']) . ' final ' . e((string) $panelUser['cardLast4']) . '.' : '' ?></p>
        <label>Nome no cartão<input name="cardHolderName" required></label>
        <label>Número<input name="cardNumber" inputmode="numeric" required autocomplete="off"></label>
        <div class="checkout-grid-3">
            <label>Mês<input name="cardExpiryMonth" required maxlength="2"></label>
            <label>Ano<input name="cardExpiryYear" required maxlength="4"></label>
            <label>CVV<input name="cardCcv" type="password" required maxlength="4" autocomplete="off"></label>
        </div>
        <button class="button button-lime" type="submit">Salvar cartão recorrente</button>
    </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
