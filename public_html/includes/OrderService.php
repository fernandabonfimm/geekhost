<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/AsaasClient.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ProvisioningStub.php';

final class OrderService
{
    private PDO $db;
    private AsaasClient $asaas;

    public function __construct(?AsaasClient $asaas = null)
    {
        $this->db = geekhost_db();
        $this->asaas = $asaas ?? new AsaasClient();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createCheckout(array $input): array
    {
        $planCode = (string) ($input['plan'] ?? '');
        $periodCode = (string) ($input['period'] ?? 'first_month');
        $billingType = strtoupper((string) ($input['billingType'] ?? ''));
        $allowedBilling = ['PIX', 'BOLETO', 'CREDIT_CARD'];
        if (!in_array($billingType, $allowedBilling, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }
        // Debit cards are not offered until Asaas account confirms support.
        if (!empty($input['debitCard'])) {
            throw new InvalidArgumentException('Cartão de débito não está disponível.');
        }

        $selection = geekhost_resolve_checkout_selection($planCode, $periodCode);
        if ($selection === null) {
            throw new InvalidArgumentException('Plano ou período inválido.');
        }

        $customerData = $this->normalizeCustomer($input);
        $idempotencyKey = (string) ($input['idempotencyKey'] ?? '');
        if ($idempotencyKey === '' || strlen($idempotencyKey) < 16) {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $existing = $this->findOrderByIdempotency($idempotencyKey);
        if ($existing) {
            return $this->presentOrder($existing);
        }

        $amount = $selection['amount'];
        $amountCents = (int) round($amount * 100);
        $now = gmdate('c');
        $publicId = $this->uuid();

        $this->db->beginTransaction();
        try {
            $customerId = $this->upsertLocalCustomer($customerData, $now);
            $asaasCustomerId = $this->ensureAsaasCustomer($customerId, $customerData);

            $stmt = $this->db->prepare(
                'INSERT INTO orders (
                    public_id, customer_id, plan_code, plan_name, billing_period, amount_cents, currency,
                    billing_type, status, idempotency_key, provisioning_status, metadata_json, created_at, updated_at
                ) VALUES (
                    :public_id, :customer_id, :plan_code, :plan_name, :billing_period, :amount_cents, :currency,
                    :billing_type, :status, :idempotency_key, :provisioning_status, :metadata_json, :created_at, :updated_at
                )'
            );
            $stmt->execute([
                ':public_id' => $publicId,
                ':customer_id' => $customerId,
                ':plan_code' => $selection['plan']['code'],
                ':plan_name' => $selection['plan']['name'],
                ':billing_period' => $periodCode,
                ':amount_cents' => $amountCents,
                ':currency' => 'BRL',
                ':billing_type' => $billingType,
                ':status' => 'awaiting_payment',
                ':idempotency_key' => $idempotencyKey,
                ':provisioning_status' => 'none',
                ':metadata_json' => json_encode([
                    'period_label' => $selection['period']['label'],
                    'asaas_env' => $this->asaas->environment(),
                    'subscription_ready' => !empty($selection['period']['subscription_ready']),
                ], JSON_UNESCAPED_UNICODE),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $orderId = (int) $this->db->lastInsertId();

            $dueDate = gmdate('Y-m-d', time() + 3 * 86400);
            $paymentPayload = [
                'customer' => $asaasCustomerId,
                'billingType' => $billingType,
                'value' => round($amountCents / 100, 2),
                'dueDate' => $dueDate,
                'description' => $selection['plan']['name'] . ' — ' . $selection['period']['label'],
                'externalReference' => $publicId,
            ];

            if ($billingType === 'CREDIT_CARD') {
                $card = $input['creditCard'] ?? null;
                $holder = $input['creditCardHolderInfo'] ?? null;
                if (!is_array($card) || !is_array($holder)) {
                    throw new InvalidArgumentException('Dados do cartão incompletos.');
                }
                $paymentPayload['creditCard'] = [
                    'holderName' => (string) ($card['holderName'] ?? ''),
                    'number' => preg_replace('/\D+/', '', (string) ($card['number'] ?? '')) ?? '',
                    'expiryMonth' => (string) ($card['expiryMonth'] ?? ''),
                    'expiryYear' => (string) ($card['expiryYear'] ?? ''),
                    'ccv' => (string) ($card['ccv'] ?? ''),
                ];
                $paymentPayload['creditCardHolderInfo'] = [
                    'name' => (string) ($holder['name'] ?? $customerData['name']),
                    'email' => (string) ($holder['email'] ?? $customerData['email']),
                    'cpfCnpj' => (string) ($holder['cpfCnpj'] ?? $customerData['cpf_cnpj']),
                    'postalCode' => (string) ($holder['postalCode'] ?? $customerData['postal_code']),
                    'addressNumber' => (string) ($holder['addressNumber'] ?? $customerData['address_number']),
                    'phone' => (string) ($holder['phone'] ?? $customerData['phone']),
                    'mobilePhone' => (string) ($holder['mobilePhone'] ?? $customerData['mobile_phone']),
                ];
                $paymentPayload['remoteIp'] = $this->clientIp();
            }

            $payment = $this->asaas->createPayment($paymentPayload, $idempotencyKey);
            // Drop card payload from memory context ASAP — never persist PAN/CVV.
            unset($paymentPayload['creditCard'], $input['creditCard']);

            $status = $this->mapAsaasPaymentStatus((string) ($payment['status'] ?? 'PENDING'), false);
            $pixQr = null;
            $pixCopy = null;
            $boletoUrl = null;
            $boletoField = null;
            $cardBrand = null;
            $cardLast4 = null;

            if ($billingType === 'PIX') {
                $paymentId = (string) ($payment['id'] ?? '');
                for ($attempt = 1; $attempt <= 4; $attempt++) {
                    try {
                        $pix = $this->asaas->getPixQrCode($paymentId);
                        $pixQr = isset($pix['encodedImage']) ? (string) $pix['encodedImage'] : null;
                        $pixCopy = isset($pix['payload']) ? (string) $pix['payload'] : null;
                        if ($pixQr || $pixCopy) {
                            break;
                        }
                    } catch (Throwable $e) {
                        geekhost_log('pix_qr_fetch_failed', [
                            'order' => $publicId,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    if ($attempt < 4) {
                        usleep(400000 * $attempt);
                    }
                }
            }

            if ($billingType === 'BOLETO') {
                $boletoUrl = isset($payment['bankSlipUrl']) ? (string) $payment['bankSlipUrl'] : (isset($payment['invoiceUrl']) ? (string) $payment['invoiceUrl'] : null);
                $boletoField = isset($payment['identificationField']) ? (string) $payment['identificationField'] : null;
            }

            if ($billingType === 'CREDIT_CARD' && isset($payment['creditCard']) && is_array($payment['creditCard'])) {
                $cardBrand = isset($payment['creditCard']['creditCardBrand']) ? (string) $payment['creditCard']['creditCardBrand'] : null;
                $cardLast4 = isset($payment['creditCard']['creditCardNumber']) ? substr((string) $payment['creditCard']['creditCardNumber'], -4) : null;
            }

            if (in_array($status, ['paid_pending_provisioning'], true)) {
                // Card may confirm synchronously — still do not mark hosting active.
            }

            $upd = $this->db->prepare(
                'UPDATE orders SET
                    asaas_payment_id = :pid,
                    status = :status,
                    asaas_invoice_url = :invoice,
                    pix_qr_code = :pix_qr,
                    pix_copy_paste = :pix_copy,
                    boleto_url = :boleto_url,
                    boleto_identification_field = :boleto_field,
                    card_brand = :card_brand,
                    card_last4 = :card_last4,
                    paid_at = :paid_at,
                    updated_at = :updated_at
                 WHERE id = :id'
            );
            $paidAt = $status === 'paid_pending_provisioning' ? $now : null;
            $upd->execute([
                ':pid' => (string) ($payment['id'] ?? ''),
                ':status' => $status,
                ':invoice' => isset($payment['invoiceUrl']) ? (string) $payment['invoiceUrl'] : null,
                ':pix_qr' => $pixQr,
                ':pix_copy' => $pixCopy,
                ':boleto_url' => $boletoUrl,
                ':boleto_field' => $boletoField,
                ':card_brand' => $cardBrand,
                ':card_last4' => $cardLast4,
                ':paid_at' => $paidAt,
                ':updated_at' => $now,
                ':id' => $orderId,
            ]);

            // Prepare subscription row placeholder for monthly/annual (not activated until recurring API is enabled).
            if (!empty($selection['period']['subscription_ready']) && in_array($periodCode, ['first_month', 'monthly', 'annual'], true)) {
                $this->prepareSubscriptionPlaceholder($customerId, $selection, $periodCode, $amountCents, $now);
            }

            $this->db->commit();

            $order = $this->findOrderByPublicId($publicId);
            if (!$order) {
                throw new RuntimeException('Pedido não encontrado após criação.');
            }

            if ($order['status'] === 'paid_pending_provisioning') {
                ProvisioningStub::queue($order);
            }

            geekhost_log('order_created', [
                'public_id' => $publicId,
                'plan' => $planCode,
                'period' => $periodCode,
                'billingType' => $billingType,
                'amount_cents' => $amountCents,
                'asaas_payment_id' => $order['asaas_payment_id'],
                'status' => $order['status'],
            ]);

            return $this->presentOrder($order);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            geekhost_log('order_create_failed', ['error' => $e->getMessage(), 'plan' => $planCode]);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleWebhook(array $payload, string $providedToken): void
    {
        $expected = $this->asaas->webhookToken();
        if ($expected === '' || !hash_equals($expected, $providedToken)) {
            throw new RuntimeException('Webhook token inválido.', 401);
        }

        $eventType = (string) ($payload['event'] ?? '');
        $paymentData = $payload['payment'] ?? null;
        if (!is_array($paymentData) || $eventType === '') {
            throw new InvalidArgumentException('Payload de webhook inválido.');
        }

        $paymentId = (string) ($paymentData['id'] ?? '');
        $eventId = (string) ($payload['id'] ?? '');
        $eventKey = $eventId !== ''
            ? $eventId
            : hash('sha256', $eventType . '|' . $paymentId . '|' . json_encode($paymentData['status'] ?? ''));

        $now = gmdate('c');
        try {
            $ins = $this->db->prepare(
                'INSERT INTO webhook_events (event_key, event_type, asaas_payment_id, payload_json, created_at)
                 VALUES (:k, :t, :p, :j, :c)'
            );
            $ins->execute([
                ':k' => $eventKey,
                ':t' => $eventType,
                ':p' => $paymentId !== '' ? $paymentId : null,
                ':j' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':c' => $now,
            ]);
        } catch (PDOException $e) {
            // Duplicate event_key → already processed; idempotent success.
            geekhost_log('webhook_duplicate', ['event_key' => $eventKey, 'event' => $eventType]);
            return;
        }

        $relevant = [
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
            'PAYMENT_OVERDUE',
            'PAYMENT_REFUNDED',
            'PAYMENT_DELETED',
            'PAYMENT_RECEIVED_IN_CASH',
        ];
        if (!in_array($eventType, $relevant, true)) {
            $this->markWebhookProcessed($eventKey, $now);
            return;
        }

        if ($paymentId === '') {
            $this->markWebhookProcessed($eventKey, $now);
            return;
        }

        // Re-fetch charge for integrity (do not trust webhook body alone).
        $fresh = $this->asaas->getPayment($paymentId);
        $order = $this->findOrderByAsaasPaymentId($paymentId);
        if (!$order && !empty($fresh['externalReference'])) {
            $order = $this->findOrderByPublicId((string) $fresh['externalReference']);
        }
        if (!$order) {
            geekhost_log('webhook_order_missing', ['payment_id' => $paymentId, 'event' => $eventType]);
            $this->markWebhookProcessed($eventKey, $now);
            return;
        }

        $newStatus = $this->mapAsaasPaymentStatus((string) ($fresh['status'] ?? ''), true, $eventType);
        $paidAt = in_array($newStatus, ['paid_pending_provisioning'], true)
            ? ($order['paid_at'] ?: $now)
            : $order['paid_at'];

        $upd = $this->db->prepare(
            'UPDATE orders SET status = :s, paid_at = :p, updated_at = :u,
                asaas_invoice_url = COALESCE(:inv, asaas_invoice_url),
                boleto_url = COALESCE(:bol, boleto_url)
             WHERE id = :id'
        );
        $upd->execute([
            ':s' => $newStatus,
            ':p' => $paidAt,
            ':u' => $now,
            ':inv' => isset($fresh['invoiceUrl']) ? (string) $fresh['invoiceUrl'] : null,
            ':bol' => isset($fresh['bankSlipUrl']) ? (string) $fresh['bankSlipUrl'] : null,
            ':id' => $order['id'],
        ]);

        $updated = $this->findOrderByPublicId((string) $order['public_id']);
        if ($updated && $updated['status'] === 'paid_pending_provisioning' && ($updated['provisioning_status'] ?? 'none') === 'none') {
            ProvisioningStub::queue($updated);
        }

        geekhost_log('webhook_processed', [
            'event' => $eventType,
            'payment_id' => $paymentId,
            'order' => $order['public_id'],
            'status' => $newStatus,
        ]);
        $this->markWebhookProcessed($eventKey, $now);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrderByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE public_id = :id LIMIT 1');
        $stmt->execute([':id' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function presentOrder(array $order): array
    {
        return [
            'id' => $order['public_id'],
            'status' => $order['status'],
            'planCode' => $order['plan_code'],
            'planName' => $order['plan_name'],
            'billingPeriod' => $order['billing_period'],
            'billingType' => $order['billing_type'],
            'amount' => round(((int) $order['amount_cents']) / 100, 2),
            'amountFormatted' => geekhost_format_brl(((int) $order['amount_cents']) / 100),
            'invoiceUrl' => $order['asaas_invoice_url'],
            'pix' => [
                'qrCodeBase64' => $order['pix_qr_code'],
                'copyPaste' => $order['pix_copy_paste'],
            ],
            'boleto' => [
                'url' => $order['boleto_url'],
                'identificationField' => $order['boleto_identification_field'],
            ],
            'card' => [
                'brand' => $order['card_brand'],
                'last4' => $order['card_last4'],
            ],
            'provisioningStatus' => $order['provisioning_status'],
            'paidAt' => $order['paid_at'],
            'environment' => $this->asaas->environment(),
        ];
    }

    private function mapAsaasPaymentStatus(string $asaasStatus, bool $fromWebhook, string $event = ''): string
    {
        $asaasStatus = strtoupper($asaasStatus);
        if ($event === 'PAYMENT_REFUNDED' || $asaasStatus === 'REFUNDED') {
            return 'refunded';
        }
        if ($event === 'PAYMENT_DELETED' || $asaasStatus === 'DELETED') {
            return 'canceled';
        }
        if ($event === 'PAYMENT_OVERDUE' || $asaasStatus === 'OVERDUE') {
            return 'overdue';
        }
        if (
            in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH'], true) ||
            in_array($asaasStatus, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true)
        ) {
            return 'paid_pending_provisioning';
        }
        if (in_array($asaasStatus, ['PENDING', 'AWAITING_RISK_ANALYSIS'], true)) {
            return 'awaiting_payment';
        }
        return $fromWebhook ? 'awaiting_payment' : 'awaiting_payment';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function normalizeCustomer(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $cpfCnpj = preg_replace('/\D+/', '', (string) ($input['cpfCnpj'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';
        $mobile = preg_replace('/\D+/', '', (string) ($input['mobilePhone'] ?? $phone)) ?? '';
        $postal = preg_replace('/\D+/', '', (string) ($input['postalCode'] ?? '')) ?? '';
        $address = trim((string) ($input['address'] ?? ''));
        $number = trim((string) ($input['addressNumber'] ?? ''));
        $complement = trim((string) ($input['complement'] ?? ''));
        $province = strtoupper(trim((string) ($input['province'] ?? '')));
        $city = trim((string) ($input['city'] ?? ''));

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Nome e e-mail válidos são obrigatórios.');
        }
        if (strlen($cpfCnpj) !== 11 && strlen($cpfCnpj) !== 14) {
            throw new InvalidArgumentException('CPF/CNPJ inválido.');
        }
        if ($phone === '' && $mobile === '') {
            throw new InvalidArgumentException('Telefone é obrigatório.');
        }
        if (strlen($postal) !== 8 || $address === '' || $number === '' || strlen($province) !== 2 || $city === '') {
            throw new InvalidArgumentException('Endereço completo é obrigatório (CEP, rua, número, UF, cidade).');
        }

        return [
            'name' => $name,
            'email' => $email,
            'cpf_cnpj' => $cpfCnpj,
            'phone' => $phone !== '' ? $phone : $mobile,
            'mobile_phone' => $mobile !== '' ? $mobile : $phone,
            'postal_code' => $postal,
            'address' => $address,
            'address_number' => $number,
            'complement' => $complement,
            'province' => $province,
            'city' => $city,
        ];
    }

    /**
     * @param array<string, string> $data
     */
    private function upsertLocalCustomer(array $data, string $now): int
    {
        $stmt = $this->db->prepare('SELECT id, asaas_customer_id FROM customers WHERE cpf_cnpj = :c LIMIT 1');
        $stmt->execute([':c' => $data['cpf_cnpj']]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = $this->db->prepare(
                'UPDATE customers SET email=:e, name=:n, phone=:p, mobile_phone=:m, postal_code=:z,
                    address=:a, address_number=:num, complement=:comp, province=:uf, city=:city, updated_at=:u
                 WHERE id=:id'
            );
            $upd->execute([
                ':e' => $data['email'],
                ':n' => $data['name'],
                ':p' => $data['phone'],
                ':m' => $data['mobile_phone'],
                ':z' => $data['postal_code'],
                ':a' => $data['address'],
                ':num' => $data['address_number'],
                ':comp' => $data['complement'],
                ':uf' => $data['province'],
                ':city' => $data['city'],
                ':u' => $now,
                ':id' => $row['id'],
            ]);
            return (int) $row['id'];
        }

        $ins = $this->db->prepare(
            'INSERT INTO customers (email, name, cpf_cnpj, phone, mobile_phone, postal_code, address, address_number, complement, province, city, created_at, updated_at)
             VALUES (:e,:n,:c,:p,:m,:z,:a,:num,:comp,:uf,:city,:c1,:u)'
        );
        $ins->execute([
            ':e' => $data['email'],
            ':n' => $data['name'],
            ':c' => $data['cpf_cnpj'],
            ':p' => $data['phone'],
            ':m' => $data['mobile_phone'],
            ':z' => $data['postal_code'],
            ':a' => $data['address'],
            ':num' => $data['address_number'],
            ':comp' => $data['complement'],
            ':uf' => $data['province'],
            ':city' => $data['city'],
            ':c1' => $now,
            ':u' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, string> $data
     */
    private function ensureAsaasCustomer(int $customerId, array $data): string
    {
        $stmt = $this->db->prepare('SELECT asaas_customer_id FROM customers WHERE id = :id');
        $stmt->execute([':id' => $customerId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['asaas_customer_id'])) {
            return (string) $row['asaas_customer_id'];
        }

        $existing = $this->asaas->findCustomerByCpfCnpj($data['cpf_cnpj']);
        if ($existing && !empty($existing['id'])) {
            $asaasId = (string) $existing['id'];
        } else {
            $created = $this->asaas->createCustomer([
                'name' => $data['name'],
                'email' => $data['email'],
                'cpfCnpj' => $data['cpf_cnpj'],
                'phone' => $data['phone'],
                'mobilePhone' => $data['mobile_phone'],
                'postalCode' => $data['postal_code'],
                'address' => $data['address'],
                'addressNumber' => $data['address_number'],
                'complement' => $data['complement'],
                'province' => $data['province'],
                'city' => $data['city'],
                'externalReference' => 'gh-customer-' . $customerId,
            ]);
            $asaasId = (string) ($created['id'] ?? '');
        }

        if ($asaasId === '') {
            throw new RuntimeException('Asaas não retornou ID de cliente.');
        }

        $upd = $this->db->prepare('UPDATE customers SET asaas_customer_id = :a, updated_at = :u WHERE id = :id');
        $upd->execute([':a' => $asaasId, ':u' => gmdate('c'), ':id' => $customerId]);
        return $asaasId;
    }

    /**
     * @param array{plan:array, period:array, amount:float} $selection
     */
    private function prepareSubscriptionPlaceholder(int $customerId, array $selection, string $periodCode, int $amountCents, string $now): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscriptions (public_id, customer_id, plan_code, billing_period, amount_cents, status, created_at, updated_at)
             VALUES (:p, :c, :plan, :bp, :amt, :st, :c1, :u)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':c' => $customerId,
            ':plan' => $selection['plan']['code'],
            ':bp' => $periodCode,
            ':amt' => $amountCents,
            ':st' => 'prepared',
            ':c1' => $now,
            ':u' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOrderByIdempotency(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE idempotency_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOrderByAsaasPaymentId(string $paymentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE asaas_payment_id = :id LIMIT 1');
        $stmt->execute([':id' => $paymentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function markWebhookProcessed(string $eventKey, string $now): void
    {
        $stmt = $this->db->prepare('UPDATE webhook_events SET processed_at = :p WHERE event_key = :k');
        $stmt->execute([':p' => $now, ':k' => $eventKey]);
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '127.0.0.1';
        if (str_contains((string) $ip, ',')) {
            $ip = trim(explode(',', (string) $ip)[0]);
        }
        return (string) $ip;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
