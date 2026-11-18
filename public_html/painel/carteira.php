<?php

declare(strict_types=1);

$panelTitle = 'Carteira';
$panelSection = 'carteira';
$panelLead = 'Cartão de renovação Asaas. Número completo e CVV não são armazenados na GeekHost.';
$panelScripts = ['assets/js/carteira.js'];
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        try {
            $orders = new OrderService();
            $cardInfo = $orders->updateRecurringCard((int) $panelUser['id'], [
                'holderName' => (string) ($_POST['cardHolderName'] ?? ''),
                'number' => (string) ($_POST['cardNumber'] ?? ''),
                'expiryMonth' => (string) ($_POST['cardExpiryMonth'] ?? ''),
                'expiryYear' => (string) ($_POST['cardExpiryYear'] ?? ''),
                'ccv' => (string) ($_POST['cardCcv'] ?? ''),
            ], [
                'name' => (string) ($_POST['holderName'] ?? $panelUser['name']),
                'email' => (string) ($_POST['holderEmail'] ?? $panelUser['email']),
                'cpfCnpj' => (string) ($_POST['cpfCnpj'] ?? $panelUser['cpfCnpj']),
                'postalCode' => (string) ($_POST['postalCode'] ?? $panelUser['postalCode']),
                'addressNumber' => (string) ($_POST['addressNumber'] ?? $panelUser['addressNumber']),
                'phone' => (string) ($_POST['phone'] ?? $panelUser['phone']),
                'mobilePhone' => (string) ($_POST['mobilePhone'] ?? $panelUser['mobilePhone']),
            ]);
            $message = 'Cartão atualizado' . (!empty($cardInfo['last4']) ? ' · final ' . $cardInfo['last4'] : '') . '.';
            $panelUser = $auth->user() ?? $panelUser;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$brand = (string) ($panelUser['cardBrand'] ?? '');
$last4 = (string) ($panelUser['cardLast4'] ?? '');
$hasCard = $last4 !== '';
?>
<section class="panel-wallet" data-wallet>
    <?php if ($message): ?><div class="gh-alert gh-alert-ok"><i data-lucide="check-circle-2"></i><div><?= e($message) ?></div></div><?php endif; ?>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><i data-lucide="triangle-alert"></i><div><?= e($error) ?></div></div><?php endif; ?>

    <div class="wallet-layout">
        <div class="wallet-side">
            <div class="wallet-card-preview" data-card-preview data-brand="<?= e(strtolower($brand)) ?>">
                <div class="wallet-card-chip" aria-hidden="true"></div>
                <div class="wallet-card-brand" data-card-brand-label><?= e($brand !== '' ? strtoupper($brand) : 'CARTÃO') ?></div>
                <p class="wallet-card-number" data-card-number-display><?= $hasCard ? '•••• •••• •••• ' . e($last4) : '•••• •••• •••• ••••' ?></p>
                <div class="wallet-card-meta">
                    <div>
                        <span>Titular</span>
                        <strong data-card-name-display><?= e((string) ($panelUser['name'] ?: 'SEU NOME')) ?></strong>
                    </div>
                    <div>
                        <span>Validade</span>
                        <strong data-card-expiry-display>MM/AA</strong>
                    </div>
                </div>
            </div>

            <article class="gh-card wallet-info-list">
                <div class="wallet-info-row"><span>Titular</span><strong><?= e((string) ($panelUser['name'] ?: '—')) ?></strong></div>
                <div class="wallet-info-row"><span>Bandeira</span><strong><?= e($brand !== '' ? strtoupper($brand) : '—') ?></strong></div>
                <div class="wallet-info-row"><span>Final</span><strong><?= e($hasCard ? $last4 : '—') ?></strong></div>
                <div class="wallet-info-row"><span>Renovação</span><strong><?= $hasCard ? 'Cartão tokenizado' : 'Sem cartão' ?></strong></div>
            </article>

            <article class="gh-card wallet-secure">
                <strong>Pagamento seguro</strong>
                <p style="margin:0;color:var(--gh-muted);font-size:13px;line-height:1.5">
                    A GeekHost não guarda o número completo do cartão nem o CVV. A renovação usa tokenização Asaas.
                    Não há saldo fictício nesta carteira.
                </p>
            </article>
        </div>

        <article class="gh-card wallet-form-card">
            <form method="post" class="panel-form panel-form-wide" autocomplete="off" data-wallet-form>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2><?= $hasCard ? 'Atualizar cartão' : 'Cadastrar cartão' ?></h2>
                <label>Nome impresso<input name="cardHolderName" data-card-name required value="<?= e((string) $panelUser['name']) ?>"></label>
                <label>Número do cartão
                    <div class="wallet-number-row">
                        <input name="cardNumber" data-card-number inputmode="numeric" maxlength="23" required autocomplete="off" placeholder="ACCT-000003">
                        <span class="wallet-brand-pill" data-card-brand-pill>——</span>
                    </div>
                </label>
                <div class="checkout-grid-3">
                    <label>Mês<input name="cardExpiryMonth" data-card-month required maxlength="2" placeholder="MM"></label>
                    <label>Ano<input name="cardExpiryYear" data-card-year required maxlength="4" placeholder="AAAA"></label>
                    <label>CVV<input name="cardCcv" type="password" required maxlength="4" autocomplete="off"></label>
                </div>

                <hr class="panel-divider">
                <h2>Titular (Asaas)</h2>
                <div class="checkout-grid-2">
                    <label>Nome<input name="holderName" value="<?= e((string) $panelUser['name']) ?>" required></label>
                    <label>E-mail<input type="email" name="holderEmail" value="<?= e((string) $panelUser['email']) ?>" required></label>
                </div>
                <div class="checkout-grid-2">
                    <label>CPF/CNPJ<input name="cpfCnpj" value="<?= e((string) $panelUser['cpfCnpj']) ?>" required></label>
                    <label>CEP<input name="postalCode" value="<?= e((string) $panelUser['postalCode']) ?>" required></label>
                </div>
                <div class="checkout-grid-2">
                    <label>Nº endereço<input name="addressNumber" value="<?= e((string) $panelUser['addressNumber']) ?>" required></label>
                    <label>Telefone<input name="phone" value="<?= e((string) $panelUser['phone']) ?>"></label>
                </div>
                <label>Celular<input name="mobilePhone" value="<?= e((string) $panelUser['mobilePhone']) ?>"></label>
                <button class="gh-btn gh-btn-primary" type="submit">Salvar cartão</button>
            </form>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
