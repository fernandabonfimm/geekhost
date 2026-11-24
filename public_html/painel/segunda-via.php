<?php

declare(strict_types=1);

$requireAuth = false;
$panelTitle = 'Segunda via de boleto e Pix';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/crypto.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';

$orders = new OrderService();
$email = strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? ($panelUser['email'] ?? ''))));
$method = (string) ($_POST['confirm_method'] ?? 'cpf');
if (!in_array($method, ['cpf', 'email'], true)) {
    $method = 'cpf';
}
$document = preg_replace('/\D+/', '', (string) ($_POST['document'] ?? '')) ?? '';
$invoices = [];
$unlocked = false;
$searched = false;
$error = '';
$notice = '';
$linkSent = false;

$signSegundaVia = static function (string $mail): string {
    $payload = rtrim(strtr(base64_encode(json_encode([
        'e' => strtolower(trim($mail)),
        'exp' => time() + 3600,
    ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload, geekhost_app_data_key());
    return $payload . '.' . $sig;
};

$readSegundaVia = static function (string $token): ?string {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload, $sig] = $parts;
    $expect = hash_hmac('sha256', $payload, geekhost_app_data_key());
    if (!hash_equals($expect, $sig)) {
        return null;
    }
    $pad = strlen($payload) % 4;
    if ($pad > 0) {
        $payload .= str_repeat('=', 4 - $pad);
    }
    $json = base64_decode(strtr($payload, '-_', '+/'), true);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    $exp = (int) ($data['exp'] ?? 0);
    $mail = strtolower(trim((string) ($data['e'] ?? '')));
    if ($exp < time() || $mail === '' || filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }
    return $mail;
};

$matchDocument = static function (array $account, string $digits): bool {
    if ($digits === '' || (strlen($digits) !== 11 && strlen($digits) !== 14)) {
        return false;
    }
    $stored = preg_replace('/\D+/', '', (string) (geekhost_decrypt((string) ($account['cpf_cnpj_enc'] ?? '')) ?? '')) ?? '';
    if ($stored !== '' && hash_equals($stored, $digits)) {
        return true;
    }
    $customerId = (int) ($account['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return false;
    }
    try {
        $pdo = geekhost_db();
        $stmt = $pdo->prepare('SELECT cpf_cnpj, cpf_hash FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $customerId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $hash = (string) ($row['cpf_hash'] ?? '');
        if ($hash !== '' && hash_equals($hash, hash('sha256', $digits))) {
            return true;
        }
        $open = geekhost_open((string) ($row['cpf_cnpj'] ?? '')) ?? '';
        $openDigits = preg_replace('/\D+/', '', $open) ?? '';
        return $openDigits !== '' && hash_equals($openDigits, $digits);
    } catch (Throwable $e) {
        return false;
    }
};

$token = (string) ($_GET['t'] ?? '');
if ($token !== '') {
    $tokenEmail = $readSegundaVia($token);
    if ($tokenEmail === null) {
        $error = 'Este link expirou ou é inválido. Solicite um novo acesso.';
    } else {
        $email = $tokenEmail;
        $unlocked = true;
        $searched = true;
        $account = $auth->findByEmail($email);
        if ($account) {
            $invoices = $orders->listInvoicesForUser((int) $account['id'], null, ['status' => 'em_aberto']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
        $searched = false;
    } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Informe um e-mail válido cadastrado na GeekHost.';
    } elseif ($method === 'email') {
        $account = $auth->findByEmail($email);
        // Always show the same message to avoid account enumeration.
        if ($account) {
            $link = geekhost_public_url('/painel/segunda-via?t=' . rawurlencode($signSegundaVia($email)));
            $html = '<!doctype html><html lang="pt-BR"><body style="font-family:Arial,sans-serif;color:#1a1e24;background:#f3f4f5;padding:24px">'
                . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:28px">'
                . '<h1 style="margin:0 0 12px;font-size:22px">Segunda via GeekHost</h1>'
                . '<p style="margin:0 0 18px;line-height:1.5">Use o link abaixo para consultar cobranças pendentes com segurança. Ele expira em 1 hora.</p>'
                . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#01e152;color:#102016;font-weight:800;text-decoration:none;padding:12px 18px;border-radius:999px">Consultar segunda via</a></p>'
                . '<p style="margin:18px 0 0;font-size:12px;color:#697078">Se você não pediu isso, ignore este e-mail.</p>'
                . '</div></body></html>';
            Mailer::send($email, 'Seu link de segunda via GeekHost', $html);
        }
        $linkSent = true;
        $notice = 'Se existir uma conta com este e-mail, enviamos um link seguro para consultar as cobranças.';
        $searched = false;
    } else {
        if (strlen($document) !== 11 && strlen($document) !== 14) {
            $error = 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.';
        } else {
            $account = $auth->findByEmail($email);
            if (!$account || !$matchDocument($account, $document)) {
                $error = 'Não encontramos uma conta com este e-mail e documento.';
            } else {
                $unlocked = true;
                $invoices = $orders->listInvoicesForUser((int) $account['id'], null, ['status' => 'em_aberto']);
            }
        }
    }
} elseif (!empty($panelUser) && !$unlocked && $token === '') {
    // Logged-in clients can open their own open invoices without re-confirming.
    $unlocked = true;
    $searched = true;
    $email = (string) ($panelUser['email'] ?? $email);
    $invoices = $orders->listInvoicesForUser((int) $panelUser['id'], null, ['status' => 'em_aberto']);
}
?>
<section class="auth-panel segunda-via-panel">
    <p class="auth-kicker">Área do cliente</p>
    <h1>Segunda via de boleto e Pix</h1>
    <p class="auth-lead">Consulte suas cobranças pendentes e acesse os dados de pagamento com segurança.</p>

    <?php if ($error): ?><div class="auth-alert is-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="auth-alert"><?= e($notice) ?></div><?php endif; ?>

    <?php if (!$unlocked): ?>
        <div class="segunda-via-card-block">
            <h2>Consulte sua segunda via</h2>
            <p>Informe o e-mail principal da sua conta e escolha como confirmar o acesso.</p>
            <form method="post" class="auth-form segunda-via-form" data-segunda-via-form>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="segunda-via-form-grid">
                    <div class="segunda-via-col">
                        <div class="auth-label-row"><span>E-mail cadastrado</span></div>
                        <label class="auth-field">
                            <i data-lucide="mail" aria-hidden="true"></i>
                            <input type="email" name="email" required autocomplete="username" placeholder="voce@exemplo.com" value="<?= e($email) ?>">
                        </label>
                        <p class="segunda-via-hint">Use o e-mail principal cadastrado na GeekHost.</p>
                    </div>
                    <div class="segunda-via-col">
                        <div class="auth-label-row"><span>Como deseja confirmar?</span></div>
                        <p class="segunda-via-hint segunda-via-hint-top">Você pode consultar agora com seu documento ou receber um link seguro por e-mail.</p>
                        <label class="segunda-via-choice<?= $method === 'cpf' ? ' is-selected' : '' ?>">
                            <input type="radio" name="confirm_method" value="cpf" <?= $method === 'cpf' ? 'checked' : '' ?> data-segunda-method>
                            <span>
                                <strong>Consultar agora com CPF/CNPJ</strong>
                                <small>Informe o documento cadastrado e veja as cobranças pendentes nesta tela.</small>
                            </span>
                        </label>
                        <label class="segunda-via-choice<?= $method === 'email' ? ' is-selected' : '' ?>">
                            <input type="radio" name="confirm_method" value="email" <?= $method === 'email' ? 'checked' : '' ?> data-segunda-method>
                            <span>
                                <strong>Receber link por e-mail</strong>
                                <small>Enviaremos um link seguro para consultar suas cobranças.</small>
                            </span>
                        </label>
                        <div class="segunda-via-doc" data-segunda-doc <?= $method === 'email' ? 'hidden' : '' ?>>
                            <div class="auth-label-row"><span>CPF ou CNPJ</span></div>
                            <label class="auth-field">
                                <i data-lucide="fingerprint" aria-hidden="true"></i>
                                <input type="text" name="document" inputmode="numeric" autocomplete="off" placeholder="Digite seu CPF ou CNPJ" value="<?= e($document) ?>">
                            </label>
                            <p class="segunda-via-hint">O documento é usado somente para confirmar seu acesso e não é armazenado nesta consulta.</p>
                        </div>
                    </div>
                </div>
                <button class="auth-submit" type="submit">Continuar <i data-lucide="arrow-right" aria-hidden="true"></i></button>
                <p class="segunda-via-secure">Seus dados são protegidos e utilizados somente para liberar esta consulta.</p>
            </form>
        </div>
    <?php else: ?>
        <div class="segunda-via-card-block">
            <h2>Cobranças em aberto</h2>
            <p>Conta: <strong><?= e($email) ?></strong></p>
            <?php if ($invoices === []): ?>
                <div class="auth-alert" style="margin-top:12px">Não encontramos faturas em aberto para este e-mail.</div>
            <?php else: ?>
                <div class="segunda-via-list">
                    <?php foreach ($invoices as $invoice): ?>
                        <article class="segunda-via-card">
                            <div>
                                <strong><?= e((string) ($invoice['planName'] ?? 'Hospedagem')) ?></strong>
                                <p>
                                    <?= e((string) ($invoice['amountFormatted'] ?? '')) ?>
                                    · vencimento <?= e((string) ($invoice['dueDate'] ?: 'a confirmar')) ?>
                                    · <?= e((string) ($invoice['statusLabel'] ?? $invoice['status'] ?? '')) ?>
                                </p>
                                <?php if (!empty($invoice['domain'])): ?>
                                    <p>Domínio: <?= e((string) $invoice['domain']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="segunda-via-actions">
                                <?php if (!empty($invoice['pix']['copyPaste'])): ?>
                                    <button class="gh-btn gh-btn-secondary" type="button" data-copy="<?= e((string) $invoice['pix']['copyPaste']) ?>">Copiar PIX</button>
                                <?php endif; ?>
                                <?php if (!empty($invoice['boleto']['url'])): ?>
                                    <a class="gh-btn gh-btn-secondary" href="<?= e((string) $invoice['boleto']['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir boleto</a>
                                <?php endif; ?>
                                <?php if (!empty($invoice['invoiceUrl'])): ?>
                                    <a class="gh-btn gh-btn-primary" href="<?= e((string) $invoice['invoiceUrl']) ?>" target="_blank" rel="noopener noreferrer">Pagar</a>
                                <?php elseif (!empty($invoice['id'])): ?>
                                    <a class="gh-btn gh-btn-primary" href="<?= e(url('/checkout?order=' . rawurlencode((string) $invoice['id']))) ?>">Gerar pagamento</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="panel-auth-links" style="margin-top:18px">
                <a href="<?= e(url('/painel/segunda-via')) ?>">Nova consulta</a>
                <a href="<?= e(url('/painel/login?next=' . rawurlencode('/painel/faturas'))) ?>">Entrar no painel</a>
            </p>
        </div>
    <?php endif; ?>
</section>
<script>
(() => {
  const form = document.querySelector('[data-segunda-via-form]');
  if (!form) return;
  const doc = form.querySelector('[data-segunda-doc]');
  const sync = () => {
    const method = form.querySelector('[data-segunda-method]:checked')?.value || 'cpf';
    form.querySelectorAll('.segunda-via-choice').forEach((el) => {
      el.classList.toggle('is-selected', el.querySelector('input')?.value === method);
    });
    if (doc) doc.hidden = method !== 'cpf';
  };
  form.querySelectorAll('[data-segunda-method]').forEach((input) => input.addEventListener('change', sync));
  sync();
})();
document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(button.getAttribute('data-copy') || '');
      button.textContent = 'PIX copiado';
    } catch (error) {
      button.textContent = 'Não foi possível copiar';
    }
  });
});
</script>
<?php require __DIR__ . '/_footer.php'; ?>
