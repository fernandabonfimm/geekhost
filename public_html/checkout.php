<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/plans.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/Auth.php';

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
$bodyClass = 'checkout-body';
$checkoutHeader = true;
$csrf = csrf_token();
$periodsJson = json_encode($selection['plan']['periods'], JSON_UNESCAPED_UNICODE);
$nameservers = function_exists('geekhost_nameservers') ? geekhost_nameservers() : ['ns1.geekhost.com.br', 'ns2.geekhost.com.br'];

$authUser = null;
try {
    $authUser = (new Auth())->user();
} catch (Throwable $e) {
    $authUser = null;
}

$prefill = [
    'name' => (string) ($authUser['name'] ?? ''),
    'email' => (string) ($authUser['email'] ?? ''),
    'cpfCnpj' => (string) ($authUser['cpfCnpj'] ?? ''),
    'mobilePhone' => (string) ($authUser['mobilePhone'] ?? $authUser['phone'] ?? ''),
    'postalCode' => (string) ($authUser['postalCode'] ?? ''),
    'address' => (string) ($authUser['address'] ?? ''),
    'addressNumber' => (string) ($authUser['addressNumber'] ?? ''),
    'complement' => (string) ($authUser['complement'] ?? ''),
    'city' => (string) ($authUser['city'] ?? ''),
    'province' => (string) ($authUser['province'] ?? ''),
];

require __DIR__ . '/includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@0.468.0" defer></script>
<link rel="stylesheet" href="<?= e(asset('assets/css/checkout.css')) ?>">

<section class="checkout-page" data-checkout
    data-plan="<?= e($planCode) ?>"
    data-period="<?= e($periodCode) ?>"
    data-amount="<?= e((string) $selection['amount']) ?>"
    data-renew="<?= e((string) $selection['renew_amount']) ?>"
    data-csrf="<?= e($csrf) ?>"
    data-create-url="<?= e(url('/api/checkout/create')) ?>"
    data-status-url="<?= e(url('/api/checkout/status')) ?>"
    data-panel-url="<?= e(url('/painel/')) ?>"
    data-login-url="<?= e(url('/painel/login')) ?>"
    data-periods='<?= e((string) $periodsJson) ?>'>

    <div class="co-hero">
        <img class="co-hero-bg" src="<?= e(asset('assets/images/banner-checkout-para-letras-claras.png')) ?>" alt="" width="1600" height="420">
        <div class="co-hero-inner page-width">
            <div class="co-hero-copy">
                <p class="co-hero-eyebrow" data-hero-eyebrow>Checkout GeekHost</p>
                <h1 data-hero-title>Contratar <?= e($selection['plan']['name']) ?></h1>
                <p class="co-hero-sub" data-hero-sub>Seu projeto merece um bom começo.</p>
            </div>
        </div>
        <div class="co-stepper-wrap page-width">
            <ol class="co-stepper" data-steps aria-label="Etapas do checkout">
                <li class="is-active" data-step-indicator="1"><span class="co-step-dot" data-step-dot>1</span><span class="co-step-label">Domínio</span></li>
                <li data-step-indicator="2"><span class="co-step-dot" data-step-dot>2</span><span class="co-step-label">Configurar</span></li>
                <li data-step-indicator="3"><span class="co-step-dot" data-step-dot>3</span><span class="co-step-label">Pagamento</span></li>
                <li data-step-indicator="4"><span class="co-step-dot" data-step-dot>4</span><span class="co-step-label">Pagar / concluir</span></li>
            </ol>
        </div>
    </div>

    <div class="co-layout page-width">
        <div class="co-main">
            <form class="co-form" data-checkout-form novalidate>
                <!-- Step 1: Domain -->
                <section class="co-card co-step" data-step="1">
                    <div class="co-card-head">
                        <span class="co-card-icon"><i data-lucide="globe-2"></i></span>
                        <div>
                            <h2>1. Qual domínio você vai usar?</h2>
                            <p>Informe o domínio que apontará para a GeekHost.</p>
                        </div>
                    </div>

                    <div class="co-choice-grid" role="radiogroup" aria-label="Tipo de domínio">
                        <button type="button" class="co-choice is-selected" data-domain-mode="own" aria-pressed="true">
                            <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                            <strong>Já tenho um domínio</strong>
                            <small>Use um domínio que você já possui</small>
                        </button>
                        <button type="button" class="co-choice" data-domain-mode="later" aria-pressed="false">
                            <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                            <strong>Ainda não tenho um domínio</strong>
                            <small>Contrate agora e cadastre o domínio depois no painel</small>
                        </button>
                    </div>

                    <p class="co-note" data-domain-later hidden>
                        A GeekHost não vende domínio. Você finaliza a contratação da hospedagem agora.
                        Depois de comprar o domínio onde preferir, cadastre em Minha Conta, na área Domínios.
                    </p>

                    <div data-domain-own>
                    <label class="co-field">
                        <span>Digite o seu domínio</span>
                        <div class="co-input-wrap">
                            <i data-lucide="globe" aria-hidden="true"></i>
                            <input name="domain" type="text" placeholder="meusite.com.br" required maxlength="180" autocomplete="url" data-domain-input>
                            <i class="co-input-ok" data-domain-ok data-lucide="circle-check" hidden aria-hidden="true"></i>
                        </div>
                        <span class="co-field-error" data-domain-error hidden></span>
                    </label>
                    </div>

                    <div class="co-actions">
                        <button type="button" class="co-btn co-btn-primary" data-next-step="2">Continuar <i data-lucide="arrow-right"></i></button>
                    </div>
                </section>

                <!-- Step 2: Configure -->
                <section class="co-card co-step" data-step="2" hidden>
                    <div class="co-card-head">
                        <span class="co-card-icon"><i data-lucide="server"></i></span>
                        <div>
                            <h2>2. Configure seu plano</h2>
                            <p><?= e($selection['plan']['name']) ?> · recursos reais do catálogo GeekHost</p>
                        </div>
                    </div>

                    <div class="co-specs">
                        <?php foreach ($selection['plan']['specs'] as $label => $value): ?>
                            <div class="co-spec">
                                <span class="co-spec-icon"><i data-lucide="<?= e(match ($label) {
                                    'Disco' => 'hard-drive',
                                    'E-mails' => 'mail',
                                    'Sites' => 'layers',
                                    default => 'monitor',
                                }) ?>"></i></span>
                                <div>
                                    <strong><?= e($value) ?></strong>
                                    <small><?= e($label) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="co-section-title">Escolha o período de contratação</h3>
                    <div class="co-period-grid" data-period-grid>
                        <?php foreach ($selection['plan']['periods'] as $code => $period):
                            $months = max(1, (int) $period['interval_months']);
                            $monthlyEq = ((float) $period['amount']) / $months;
                            $showPromo = !empty($period['promo']) && (float) $period['renew_amount'] > (float) $period['amount'];
                            $savePct = $showPromo
                                ? (int) round((1 - ((float) $period['amount'] / (float) $period['renew_amount'])) * 100)
                                : 0;
                            ?>
                            <label class="co-period <?= $code === $periodCode ? 'is-selected' : '' ?>">
                                <input type="radio" name="period" value="<?= e($code) ?>" <?= $code === $periodCode ? 'checked' : '' ?>>
                                <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                                <?php if ($showPromo && $savePct > 0): ?>
                                    <span class="co-period-badge"><?= (int) $savePct ?>% OFF no 1º mês</span>
                                <?php endif; ?>
                                <strong><?= e($period['label']) ?></strong>
                                <span class="co-period-price"><?= e(geekhost_format_brl((float) $period['amount'])) ?></span>
                                <?php if ($months > 1): ?>
                                    <small>≈ <?= e(geekhost_format_brl($monthlyEq)) ?>/mês · <?= (int) $months ?> meses</small>
                                <?php elseif ($showPromo): ?>
                                    <small>Depois <?= e(geekhost_format_brl((float) $period['renew_amount'])) ?>/mês</small>
                                <?php else: ?>
                                    <small>Renovação mensal</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="co-actions co-actions-split">
                        <button type="button" class="co-btn co-btn-ghost" data-prev-step="1"><i data-lucide="arrow-left"></i> Voltar</button>
                        <button type="button" class="co-btn co-btn-primary" data-next-step="3">Continuar <i data-lucide="arrow-right"></i></button>
                    </div>
                </section>

                <!-- Step 3: Payment -->
                <section class="co-card co-step" data-step="3" hidden>
                    <div class="co-card-head">
                        <span class="co-card-icon"><i data-lucide="credit-card"></i></span>
                        <div>
                            <h2>3. Conta e pagamento</h2>
                            <p>Dados da conta e forma de pagamento. Cartão/CVV não ficam salvos na GeekHost.</p>
                        </div>
                    </div>

                    <h3 class="co-section-title">Informações pessoais</h3>
                    <div class="co-grid-2">
                        <label class="co-field"><span>Nome completo</span><input name="name" type="text" autocomplete="name" required maxlength="120" value="<?= e($prefill['name']) ?>"></label>
                        <label class="co-field"><span>E-mail (login do painel)</span><input name="email" type="email" autocomplete="email" required maxlength="180" value="<?= e($prefill['email']) ?>"></label>
                        <label class="co-field"><span>CPF ou CNPJ</span><input name="cpfCnpj" type="text" inputmode="numeric" required maxlength="18" data-mask="doc" value="<?= e($prefill['cpfCnpj']) ?>"></label>
                        <label class="co-field"><span>Celular / WhatsApp</span><input name="mobilePhone" type="tel" required maxlength="20" data-mask="phone" value="<?= e($prefill['mobilePhone']) ?>"></label>
                    </div>
                    <p class="co-hint">Depois do pagamento você cria a senha do painel em uma página segura.</p>

                    <h3 class="co-section-title">Endereço</h3>
                    <div class="co-grid-2">
                        <label class="co-field"><span>CEP</span><input name="postalCode" type="text" inputmode="numeric" required maxlength="9" data-mask="cep" value="<?= e($prefill['postalCode']) ?>"></label>
                        <label class="co-field"><span>Número</span><input name="addressNumber" type="text" required maxlength="20" value="<?= e($prefill['addressNumber']) ?>"></label>
                    </div>
                    <label class="co-field"><span>Rua / Avenida</span><input name="address" type="text" required maxlength="180" value="<?= e($prefill['address']) ?>"></label>
                    <label class="co-field"><span>Complemento</span><input name="complement" type="text" maxlength="120" value="<?= e($prefill['complement']) ?>"></label>
                    <div class="co-grid-2">
                        <label class="co-field"><span>Cidade</span><input name="city" type="text" required maxlength="120" value="<?= e($prefill['city']) ?>"></label>
                        <label class="co-field"><span>UF</span><input name="province" type="text" required maxlength="2" style="text-transform:uppercase" value="<?= e($prefill['province']) ?>"></label>
                    </div>

                    <h3 class="co-section-title">Forma de pagamento</h3>
                    <div class="co-pay-grid" role="radiogroup">
                        <label class="co-pay is-selected">
                            <input type="radio" name="billingType" value="PIX" checked>
                            <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                            <strong>PIX</strong>
                            <small>QR Code Pix</small>
                        </label>
                        <label class="co-pay">
                            <input type="radio" name="billingType" value="CREDIT_CARD">
                            <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                            <strong>Cartão de crédito</strong>
                            <small>7 dias grátis</small>
                        </label>
                        <label class="co-pay">
                            <input type="radio" name="billingType" value="BOLETO">
                            <span class="co-choice-check" aria-hidden="true"><i data-lucide="check"></i></span>
                            <strong>Boleto bancário</strong>
                            <small>Compensação pode não ser imediata</small>
                        </label>
                    </div>

                    <div class="co-pay-detail" data-pay-detail="PIX">
                        <i data-lucide="qr-code"></i>
                        <div>
                            <strong>Pagamento via PIX</strong>
                            <p>Ao finalizar, geramos o QR Code e o código copia e cola. O status atualiza automaticamente quando o pagamento for confirmado.</p>
                            <?php if (function_exists('geekhost_resolve_asaas_env') && geekhost_resolve_asaas_env() === 'sandbox'): ?>
                                <p>Ambiente local usa o Asaas sandbox. O QR só sai se a conta sandbox tiver uma chave Pix cadastrada.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="co-pay-detail" data-pay-detail="CREDIT_CARD" hidden>
                        <div class="co-card-fields" data-card-fields>
                            <label class="co-field"><span>Nome no cartão</span><input name="cardHolderName" type="text" autocomplete="cc-name" maxlength="120"></label>
                            <label class="co-field"><span>Número do cartão</span><input name="cardNumber" type="text" inputmode="numeric" autocomplete="cc-number" maxlength="19" data-mask="card"></label>
                            <div class="co-grid-3">
                                <label class="co-field"><span>Mês</span><input name="cardExpiryMonth" type="text" inputmode="numeric" maxlength="2" placeholder="MM"></label>
                                <label class="co-field"><span>Ano</span><input name="cardExpiryYear" type="text" inputmode="numeric" maxlength="4" placeholder="AAAA"></label>
                                <label class="co-field"><span>CVV</span><?php geekhost_password_input('cardCcv', ['maxlength' => 4, 'autocomplete' => 'cc-csc', 'inputmode' => 'numeric']); ?></label>
                            </div>
                            <p class="co-hint">O cartão é só confirmado e salvo agora. A primeira cobrança sai depois de 7 dias grátis. PIX, boleto e débito são cobrados na hora e não têm esses 7 dias. O CVV não fica gravado.</p>
                            <?php if (function_exists('geekhost_resolve_asaas_env') && geekhost_resolve_asaas_env() === 'sandbox'): ?>
                                <div class="co-sandbox-card">
                                    <strong>Cartão de teste do sandbox</strong>
                                    <p>Neste computador a cobrança vai para o Asaas sandbox, sem valor real. Use um cartão fictício, validade futura e CVV fictício.</p>
                                    <ul>
                                        <li>Aprovado Visa <code>4539 6560 4839 2220</code></li>
                                        <li>Aprovado Mastercard <code>5263 8372 5327 8275</code></li>
                                        <li>Recusado Visa <code>4916 5613 5824 0741</code></li>
                                    </ul>
                                    <p>CVV: qualquer código de 3 dígitos (4 na American Express). Nome: qualquer nome fictício.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="co-pay-detail" data-pay-detail="BOLETO" hidden>
                        <i data-lucide="barcode"></i>
                        <div>
                            <strong>Boleto bancário</strong>
                            <p>Você receberá o link/documento do boleto. A liberação ocorre após a compensação.</p>
                        </div>
                    </div>

                    <label class="co-terms">
                        <input type="checkbox" name="terms" required>
                        <span>Li e concordo com os <a href="<?= e(url('/termos-de-uso.php')) ?>" target="_blank" rel="noopener">Termos de Uso</a> e a Política de Privacidade.</span>
                    </label>

                    <p class="co-error" data-error hidden></p>

                    <div class="co-actions co-actions-split">
                        <button type="button" class="co-btn co-btn-ghost" data-prev-step="2"><i data-lucide="arrow-left"></i> Voltar</button>
                        <button class="co-btn co-btn-primary" type="submit" data-submit>
                            <i data-lucide="lock"></i> <span data-submit-label>Finalizar pedido</span>
                        </button>
                    </div>
                </section>
            </form>

            <!-- Step 4: Pay / confirmation -->
            <section class="co-card co-confirm co-step" data-step="4" data-result hidden>
                <div class="co-confirm-grid">
                    <img class="co-confirm-art" src="<?= e(asset('assets/images/mascotes-highfive.png')) ?>" alt="" width="280" height="220">
                    <div class="co-confirm-copy">
                        <div class="co-confirm-icon" data-result-icon><i data-lucide="circle-check"></i></div>
                        <h2 data-result-title>Pedido recebido</h2>
                        <p data-result-status></p>
                        <p class="co-hint">Pedido: <code data-order-id></code></p>

                        <div data-result-pix hidden class="co-result-box co-pix-box">
                            <strong class="co-pix-title"><i data-lucide="qr-code"></i> Pague com Pix para liberar sua hospedagem</strong>
                            <p>Escaneie o QR Code ou copie o código Pix abaixo. Esta página atualiza sozinha quando o pagamento for confirmado.</p>
                            <div class="co-pix-layout">
                                <img data-pix-image alt="QR Code Pix" width="220" height="220">
                                <div class="co-pix-copy-wrap">
                                    <label class="co-field">
                                        <span>Pix copia e cola</span>
                                        <textarea data-pix-copy readonly rows="5" placeholder="Gerando código Pix…"></textarea>
                                    </label>
                                    <button type="button" class="co-btn co-btn-primary" data-copy-pix>
                                        <i data-lucide="copy"></i> Copiar código Pix
                                    </button>
                                    <p class="co-hint" data-pix-waiting hidden>Ainda gerando o QR Code… aguarde alguns segundos.</p>
                                </div>
                            </div>
                        </div>
                        <div data-result-boleto hidden class="co-result-box">
                            <strong>Boleto gerado</strong>
                            <p>Abra o boleto para pagar. A liberação ocorre após a compensação bancária.</p>
                            <a class="co-btn co-btn-primary" data-boleto-link href="#" target="_blank" rel="noopener noreferrer">Abrir boleto</a>
                            <p data-boleto-line></p>
                        </div>
                        <div data-result-card hidden class="co-result-box"><p data-card-msg></p></div>

                        <div class="co-actions co-actions-split" style="margin-top:18px">
                            <a class="co-btn co-btn-primary" data-setup-link hidden href="#">Criar senha do painel</a>
                            <a class="co-btn co-btn-ghost" data-panel-cta href="<?= e(url('/painel/login')) ?>">Acessar área do cliente</a>
                        </div>
                        <p class="co-hint" data-setup-hint hidden>Depois do pagamento, use “Criar senha do painel” para definir o acesso.</p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="co-summary" data-summary>
            <h2>Resumo do pedido</h2>
            <div class="co-summary-plan">
                <span class="co-summary-plan-icon"><i data-lucide="server"></i></span>
                <div>
                    <strong><?= e($selection['plan']['name']) ?></strong>
                    <small data-summary-period><?= e($selection['period']['label']) ?></small>
                </div>
            </div>
            <p class="co-summary-domain" data-summary-domain>Domínio: —</p>
            <ul class="co-summary-features">
                <?php foreach ($selection['plan']['features'] as $feature): ?>
                    <li><i data-lucide="check"></i><?= e($feature) ?></li>
                <?php endforeach; ?>
                <?php foreach ($selection['plan']['specs'] as $label => $value): ?>
                    <li><i data-lucide="check"></i><?= e($label) ?>: <?= e($value) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="co-summary-total">
                <span>Total hoje</span>
                <strong data-summary-total><?= e(geekhost_format_brl($selection['amount'])) ?></strong>
            </div>
            <p class="co-summary-renew" data-summary-renew>
                <?php if (!empty($selection['period']['promo'])): ?>
                    Renova depois por <?= e(geekhost_format_brl($selection['renew_amount'])) ?>/mês via assinatura automática.
                <?php else: ?>
                    Renova em <?= (int) $selection['period']['interval_months'] ?> meses por <?= e(geekhost_format_brl($selection['renew_amount'])) ?>.
                <?php endif; ?>
            </p>
            <div class="co-summary-paid" data-summary-paid hidden>
                <div><span>Valor pago</span><strong data-paid-amount>—</strong></div>
                <div><span>Status</span><span class="co-badge-ok" data-paid-status>Pago</span></div>
            </div>
        </aside>
    </div>
</section>

<div class="co-toast-host" data-toast-host aria-live="polite"></div>
<script src="<?= e(asset('assets/js/checkout.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
