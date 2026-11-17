<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/plans.php';
require_once __DIR__ . '/includes/csrf.php';

$planCode = isset($_GET['plan']) ? strtolower(trim((string) $_GET['plan'])) : '';
$periodCode = isset($_GET['period']) ? strtolower(trim((string) $_GET['period'])) : 'first_month';
$selection = geekhost_resolve_checkout_selection($planCode, $periodCode);

if ($selection === null) {
    header('Location: /planos.php');
    exit;
}

$pageTitle = 'Checkout';
$pageDocumentTitle = 'Checkout | ' . $selection['plan']['name'] . ' | GeekHost';
$pageDescription = 'Finalize a contratação do ' . $selection['plan']['name'] . ' com Pix, cartão ou boleto.';
$currentPage = 'planos';
$hideSupportCenter = true;
$csrf = csrf_token();

require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= e(asset('assets/css/checkout.css')) ?>">

<section class="checkout-page page-width" data-checkout
    data-plan="<?= e($planCode) ?>"
    data-period="<?= e($periodCode) ?>"
    data-amount="<?= e((string) $selection['amount']) ?>"
    data-csrf="<?= e($csrf) ?>"
    data-create-url="/api/checkout/create.php"
    data-status-url="/api/checkout/status.php">
    <div class="checkout-heading">
        <p class="eyebrow">Checkout seguro</p>
        <h1>Contratar <?= e($selection['plan']['name']) ?></h1>
        <p>Preencha seus dados. O valor cobrado é o oficial do plano — nunca alterado pelo navegador.</p>
    </div>

    <div class="checkout-layout">
        <form class="checkout-form" data-checkout-form novalidate>
            <fieldset>
                <legend>Seus dados</legend>
                <label>Nome completo<input name="name" type="text" autocomplete="name" required maxlength="120"></label>
                <label>E-mail<input name="email" type="email" autocomplete="email" required maxlength="180"></label>
                <label>CPF ou CNPJ<input name="cpfCnpj" type="text" inputmode="numeric" autocomplete="off" required maxlength="18" data-mask="doc"></label>
                <label>Celular / WhatsApp<input name="mobilePhone" type="tel" autocomplete="tel" required maxlength="20" data-mask="phone"></label>
            </fieldset>

            <fieldset>
                <legend>Endereço</legend>
                <div class="checkout-grid-2">
                    <label>CEP<input name="postalCode" type="text" inputmode="numeric" autocomplete="postal-code" required maxlength="9" data-mask="cep"></label>
                    <label>Número<input name="addressNumber" type="text" autocomplete="address-line2" required maxlength="20"></label>
                </div>
                <label>Rua / Avenida<input name="address" type="text" autocomplete="address-line1" required maxlength="180"></label>
                <label>Complemento<input name="complement" type="text" maxlength="120"></label>
                <div class="checkout-grid-2">
                    <label>Cidade<input name="city" type="text" autocomplete="address-level2" required maxlength="120"></label>
                    <label>UF<input name="province" type="text" autocomplete="address-level1" required maxlength="2" pattern="[A-Za-z]{2}" style="text-transform:uppercase"></label>
                </div>
            </fieldset>

            <fieldset>
                <legend>Pagamento</legend>
                <div class="checkout-methods" role="radiogroup" aria-label="Forma de pagamento">
                    <label class="checkout-method"><input type="radio" name="billingType" value="PIX" checked> Pix</label>
                    <label class="checkout-method"><input type="radio" name="billingType" value="CREDIT_CARD"> Cartão de crédito</label>
                    <label class="checkout-method"><input type="radio" name="billingType" value="BOLETO"> Boleto</label>
                </div>
                <p class="checkout-note">Cartão de débito não está disponível. Dados do cartão são enviados direto ao Asaas e não ficam armazenados na GeekHost.</p>

                <div class="checkout-card-fields" data-card-fields hidden>
                    <label>Nome no cartão<input name="cardHolderName" type="text" autocomplete="cc-name" maxlength="120"></label>
                    <label>Número do cartão<input name="cardNumber" type="text" inputmode="numeric" autocomplete="cc-number" maxlength="19" data-mask="card"></label>
                    <div class="checkout-grid-3">
                        <label>Mês<input name="cardExpiryMonth" type="text" inputmode="numeric" autocomplete="cc-exp-month" maxlength="2" placeholder="MM"></label>
                        <label>Ano<input name="cardExpiryYear" type="text" inputmode="numeric" autocomplete="cc-exp-year" maxlength="4" placeholder="AAAA"></label>
                        <label>CVV<input name="cardCcv" type="password" inputmode="numeric" autocomplete="cc-csc" maxlength="4"></label>
                    </div>
                </div>
            </fieldset>

            <button class="button button-lime checkout-submit" type="submit" data-submit>Pagar <?= e(geekhost_format_brl($selection['amount'])) ?></button>
            <p class="checkout-error" data-error hidden></p>
        </form>

        <aside class="checkout-summary">
            <h2>Resumo do pedido</h2>
            <p class="checkout-summary-plan"><?= e($selection['plan']['name']) ?></p>
            <p class="checkout-summary-period"><?= e($selection['period']['label']) ?></p>
            <ul class="checkout-summary-features">
                <?php foreach ($selection['plan']['features'] as $feature): ?>
                    <li><?= e($feature) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="checkout-summary-total">
                <span>Total agora</span>
                <strong><?= e(geekhost_format_brl($selection['amount'])) ?></strong>
            </div>
            <p class="checkout-summary-note">7 dias grátis na contratação promocional, conforme condições do plano. Após o pagamento, a liberação da hospedagem entra em fila de provisionamento.</p>
            <p class="checkout-summary-env">Ambiente: <?= e(strtolower((string) env('ASAAS_ENV', 'sandbox'))) ?></p>
        </aside>
    </div>

    <div class="checkout-result" data-result hidden>
        <h2 data-result-title>Pedido criado</h2>
        <p data-result-status></p>
        <div data-result-pix hidden>
            <p>Escaneie o QR Code Pix ou copie o código:</p>
            <img data-pix-image alt="QR Code Pix" width="220" height="220">
            <textarea data-pix-copy readonly rows="4"></textarea>
            <button type="button" class="button button-dark" data-copy-pix>Copiar código Pix</button>
        </div>
        <div data-result-boleto hidden>
            <p>Boleto gerado. Pague até o vencimento:</p>
            <a class="button button-lime" data-boleto-link href="#" target="_blank" rel="noopener noreferrer">Abrir boleto</a>
            <p data-boleto-line></p>
        </div>
        <div data-result-card hidden>
            <p data-card-msg></p>
        </div>
        <p class="checkout-result-id">Pedido: <code data-order-id></code></p>
        <a class="text-link" href="/planos.php">Voltar aos planos</a>
    </div>
</section>

<script src="<?= e(asset('assets/js/checkout.js')) ?>" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
