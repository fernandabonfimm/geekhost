<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/plans.php';
require_once __DIR__ . '/includes/csrf.php';

$planCode = isset($_GET['plan']) ? strtolower(trim((string) $_GET['plan'])) : '';
$periodCode = geekhost_normalize_period(isset($_GET['period']) ? (string) $_GET['period'] : 'mensal');
$selection = geekhost_resolve_checkout_selection($planCode, $periodCode);

if ($selection === null) {
    header('Location: ' . url('/planos.php'));
    exit;
}

$pageTitle = 'Checkout';
$pageDocumentTitle = 'Checkout | ' . $selection['plan']['name'] . ' | GeekHost';
$pageDescription = 'Contrate o ' . $selection['plan']['name'] . ' em etapas: domínio, período e pagamento.';
$currentPage = 'planos';
$hideSupportCenter = true;
$csrf = csrf_token();
$periodsJson = json_encode($selection['plan']['periods'], JSON_UNESCAPED_UNICODE);

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: { gh: { lime: '#01e152', ink: '#1a1e24', paper: '#f3f4f5', line: '#dfe2e5' } },
                borderRadius: { DEFAULT: '0px', none: '0px' },
                fontFamily: { sans: ['Galano Grotesque', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
            }
        },
        corePlugins: { preflight: false }
    };
</script>
<link rel="stylesheet" href="<?= e(asset('assets/css/checkout.css')) ?>">

<section class="checkout-page page-width font-sans" data-checkout
    data-plan="<?= e($planCode) ?>"
    data-period="<?= e($periodCode) ?>"
    data-amount="<?= e((string) $selection['amount']) ?>"
    data-renew="<?= e((string) $selection['renew_amount']) ?>"
    data-csrf="<?= e($csrf) ?>"
    data-create-url="<?= e(url('/api/checkout/create')) ?>"
    data-status-url="<?= e(url('/api/checkout/status')) ?>"
    data-periods='<?= e((string) $periodsJson) ?>'>

    <div class="checkout-heading">
        <p class="eyebrow">Checkout GeekHost</p>
        <h1>Contratar <?= e($selection['plan']['name']) ?></h1>
        <ol class="checkout-steps" data-steps>
            <li class="is-active" data-step-indicator="1">Domínio</li>
            <li data-step-indicator="2">Configurar</li>
            <li data-step-indicator="3">Pagamento</li>
        </ol>
    </div>

    <div class="checkout-layout">
        <div class="checkout-main">
            <form class="checkout-form" data-checkout-form novalidate>
                <section class="checkout-step" data-step="1">
                    <h2>1. Qual domínio você vai usar?</h2>
                    <div class="checkout-domain-tabs" role="tablist">
                        <button type="button" class="is-active" data-domain-mode="own">Já tenho um domínio</button>
                        <button type="button" data-domain-mode="later" disabled title="Em breve">Registrar novo (em breve)</button>
                    </div>
                    <p class="checkout-note">Digite o domínio que você já possui. Após o pagamento, a hospedagem entra em fila de provisionamento (não fica ativa automaticamente).</p>
                    <label>Domínio<input name="domain" type="text" placeholder="meusite.com.br" required maxlength="180" autocomplete="url"></label>
                    <button type="button" class="button button-lime" data-next-step="2">Usar domínio →</button>
                </section>

                <section class="checkout-step" data-step="2" hidden>
                    <h2>2. Configurar período</h2>
                    <p class="checkout-product-title"><?= e($selection['plan']['name']) ?></p>
                    <div class="checkout-specs">
                        <?php foreach ($selection['plan']['specs'] as $label => $value): ?>
                            <div><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkout-period-grid" data-period-grid>
                        <?php foreach ($selection['plan']['periods'] as $code => $period): ?>
                            <label class="checkout-period-card <?= $code === $periodCode ? 'is-selected' : '' ?>">
                                <input type="radio" name="period" value="<?= e($code) ?>" <?= $code === $periodCode ? 'checked' : '' ?>>
                                <strong><?= e($period['label']) ?></strong>
                                <span class="checkout-period-price"><?= e(geekhost_format_brl((float) $period['amount'])) ?></span>
                                <?php if (!empty($period['promo'])): ?>
                                    <small>1º mês · depois <?= e(geekhost_format_brl((float) $period['renew_amount'])) ?>/mês</small>
                                <?php else: ?>
                                    <small>Renova a cada <?= (int) $period['interval_months'] ?> meses</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkout-step-actions">
                        <button type="button" class="button button-outline-dark" data-prev-step="1">Voltar</button>
                        <button type="button" class="button button-lime" data-next-step="3">Continuar →</button>
                    </div>
                </section>

                <section class="checkout-step" data-step="3" hidden>
                    <h2>3. Conta e pagamento</h2>
                    <fieldset>
                        <legend>Dados da conta</legend>
                        <label>Nome completo<input name="name" type="text" autocomplete="name" required maxlength="120"></label>
                        <label>E-mail (login do painel)<input name="email" type="email" autocomplete="email" required maxlength="180"></label>
                        <p class="checkout-note">Depois do pagamento você cria a senha do painel em uma página segura.</p>
                        <label>CPF ou CNPJ<input name="cpfCnpj" type="text" inputmode="numeric" required maxlength="18" data-mask="doc"></label>
                        <label>Celular / WhatsApp<input name="mobilePhone" type="tel" required maxlength="20" data-mask="phone"></label>
                    </fieldset>
                    <fieldset>
                        <legend>Endereço</legend>
                        <div class="checkout-grid-2">
                            <label>CEP<input name="postalCode" type="text" inputmode="numeric" required maxlength="9" data-mask="cep"></label>
                            <label>Número<input name="addressNumber" type="text" required maxlength="20"></label>
                        </div>
                        <label>Rua / Avenida<input name="address" type="text" required maxlength="180"></label>
                        <label>Complemento<input name="complement" type="text" maxlength="120"></label>
                        <div class="checkout-grid-2">
                            <label>Cidade<input name="city" type="text" required maxlength="120"></label>
                            <label>UF<input name="province" type="text" required maxlength="2" style="text-transform:uppercase"></label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Pagamento</legend>
                        <div class="checkout-methods" role="radiogroup">
                            <label class="checkout-method"><input type="radio" name="billingType" value="PIX" checked> Pix</label>
                            <label class="checkout-method"><input type="radio" name="billingType" value="CREDIT_CARD"> Cartão de crédito</label>
                            <label class="checkout-method"><input type="radio" name="billingType" value="BOLETO"> Boleto</label>
                        </div>
                        <div class="checkout-card-fields" data-card-fields hidden>
                            <label>Nome no cartão<input name="cardHolderName" type="text" autocomplete="cc-name" maxlength="120"></label>
                            <label>Número do cartão<input name="cardNumber" type="text" inputmode="numeric" autocomplete="cc-number" maxlength="19" data-mask="card"></label>
                            <div class="checkout-grid-3">
                                <label>Mês<input name="cardExpiryMonth" type="text" inputmode="numeric" maxlength="2" placeholder="MM"></label>
                                <label>Ano<input name="cardExpiryYear" type="text" inputmode="numeric" maxlength="4" placeholder="AAAA"></label>
                                <label>CVV<input name="cardCcv" type="password" inputmode="numeric" maxlength="4" autocomplete="cc-csc"></label>
                            </div>
                        </div>
                        <p class="checkout-note">Cartão e CVV são enviados ao Asaas e não ficam armazenados na GeekHost. Débito não está disponível.</p>
                        <label class="checkout-terms"><input type="checkbox" name="terms" required> Li e concordo com os <a href="<?= e(url('/termos-de-uso.php')) ?>" target="_blank" rel="noopener">Termos de Uso</a>.</label>
                    </fieldset>
                    <div class="checkout-step-actions">
                        <button type="button" class="button button-outline-dark" data-prev-step="2">Voltar</button>
                        <button class="button button-lime checkout-submit" type="submit" data-submit>Completar pedido →</button>
                    </div>
                    <p class="checkout-error" data-error hidden></p>
                </section>
            </form>

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
                    <a class="button button-lime" data-boleto-link href="#" target="_blank" rel="noopener noreferrer">Abrir boleto</a>
                    <p data-boleto-line></p>
                </div>
                <div data-result-card hidden><p data-card-msg></p></div>
                <p class="checkout-result-id">Pedido: <code data-order-id></code></p>
                <p class="checkout-note" data-setup-hint hidden>Após a confirmação do pagamento, você será direcionado para criar a senha do painel.</p>
                <a class="button button-lime" data-setup-link hidden href="#">Criar senha do painel →</a>
                <a class="button button-dark" data-panel-link href="<?= e(url('/painel/login')) ?>">Já tenho senha · Entrar</a>
            </div>
        </div>

        <aside class="checkout-summary">
            <h2>Resumo do pedido</h2>
            <p class="checkout-summary-plan"><?= e($selection['plan']['name']) ?></p>
            <p class="checkout-summary-period" data-summary-period><?= e($selection['period']['label']) ?></p>
            <p class="checkout-summary-domain" data-summary-domain>Domínio: —</p>
            <ul class="checkout-summary-features">
                <?php foreach ($selection['plan']['features'] as $feature): ?>
                    <li><?= e($feature) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="checkout-summary-total">
                <span>Total hoje</span>
                <strong data-summary-total><?= e(geekhost_format_brl($selection['amount'])) ?></strong>
            </div>
            <p class="checkout-summary-note" data-summary-renew>
                <?php if (!empty($selection['period']['promo'])): ?>
                    Renova depois por <?= e(geekhost_format_brl($selection['renew_amount'])) ?>/mês via Assinatura Asaas.
                <?php else: ?>
                    Renova em <?= (int) $selection['period']['interval_months'] ?> meses por <?= e(geekhost_format_brl($selection['renew_amount'])) ?>.
                <?php endif; ?>
            </p>
            <p class="checkout-summary-env">Ambiente: <?= e(strtolower((string) env('ASAAS_ENV', 'sandbox'))) ?></p>
        </aside>
    </div>
</section>

<script src="<?= e(asset('assets/js/checkout.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
