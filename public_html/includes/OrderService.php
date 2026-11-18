<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/AsaasClient.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ProvisioningStub.php';
require_once __DIR__ . '/Auth.php';

final class OrderService
{
    private PDO $db;
    private AsaasClient $asaas;
    private Auth $auth;

    public function __construct(?AsaasClient $asaas = null)
    {
        $this->db = geekhost_db();
        $this->asaas = $asaas ?? new AsaasClient();
        $this->auth = new Auth();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createCheckout(array $input): array
    {
        $planCode = (string) ($input['plan'] ?? '');
        $periodCode = geekhost_normalize_period((string) ($input['period'] ?? 'mensal'));
        $billingType = strtoupper((string) ($input['billingType'] ?? ''));
        $domain = strtolower(trim((string) ($input['domain'] ?? '')));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if (!in_array($billingType, ['PIX', 'BOLETO', 'CREDIT_CARD'], true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }
        if ($domain === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException('Informe um domínio válido (ex: meuscript.com.br).');
        }

        $selection = geekhost_resolve_checkout_selection($planCode, $periodCode);
        if ($selection === null) {
            throw new InvalidArgumentException('Plano ou período inválido.');
        }

        $customerData = $this->normalizeCustomer($input);
        $idempotencyKey = (string) ($input['idempotencyKey'] ?? '');
        if (strlen($idempotencyKey) < 16) {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $existing = $this->findOrderByIdempotency($idempotencyKey);
        if ($existing) {
            return $this->presentOrder($existing);
        }

        $amount = $selection['amount'];
        $renewAmount = $selection['renew_amount'];
        $amountCents = (int) round($amount * 100);
        $renewCents = (int) round($renewAmount * 100);
        $now = gmdate('c');
        $publicId = $this->uuid();
        $dueDate = gmdate('Y-m-d', time() + 3 * 86400);

        $this->db->beginTransaction();
        try {
            $customerId = $this->upsertLocalCustomer($customerData, $now);
            $asaasCustomerId = $this->ensureAsaasCustomer($customerId, $customerData);

            $registered = $this->auth->registerFromCheckout($customerId, $customerData['email'], $customerData);
            $user = $registered['user'];
            $setupToken = $registered['setupToken'];
            $userId = (int) ($user['id'] ?? 0);

            $invoiceNumber = $this->nextInvoiceNumber();

            $stmt = $this->db->prepare(
                'INSERT INTO orders (
                    public_id, invoice_number, customer_id, user_id, plan_code, plan_name, billing_period, domain,
                    amount_cents, renew_amount_cents, currency, billing_type, status, due_date,
                    idempotency_key, provisioning_status, metadata_json, created_at, updated_at
                ) VALUES (
                    :public_id, :inv, :customer_id, :user_id, :plan_code, :plan_name, :billing_period, :domain,
                    :amount_cents, :renew_cents, :currency, :billing_type, :status, :due_date,
                    :idempotency_key, :provisioning_status, :metadata_json, :created_at, :updated_at
                )'
            );
            $stmt->execute([
                ':public_id' => $publicId,
                ':inv' => $invoiceNumber,
                ':customer_id' => $customerId,
                ':user_id' => $userId > 0 ? $userId : null,
                ':plan_code' => $selection['plan']['code'],
                ':plan_name' => $selection['plan']['name'],
                ':billing_period' => $periodCode,
                ':domain' => $domain,
                ':amount_cents' => $amountCents,
                ':renew_cents' => $renewCents,
                ':currency' => 'BRL',
                ':billing_type' => $billingType,
                ':status' => 'awaiting_payment',
                ':due_date' => $dueDate,
                ':idempotency_key' => $idempotencyKey,
                ':provisioning_status' => 'none',
                ':metadata_json' => json_encode([
                    'period_label' => $selection['period']['label'],
                    'asaas_cycle' => $selection['period']['cycle'],
                    'renew_amount' => $renewAmount,
                    'asaas_env' => $this->asaas->environment(),
                ], JSON_UNESCAPED_UNICODE),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $orderId = (int) $this->db->lastInsertId();

            $paymentPayload = [
                'customer' => $asaasCustomerId,
                'billingType' => $billingType,
                'value' => round($amountCents / 100, 2),
                'dueDate' => $dueDate,
                'description' => $selection['plan']['name'] . ' — ' . $selection['period']['label'] . ' — ' . $domain,
                'externalReference' => $publicId,
            ];

            $cardPayload = null;
            $holderPayload = null;
            if ($billingType === 'CREDIT_CARD') {
                $card = $input['creditCard'] ?? null;
                $holder = $input['creditCardHolderInfo'] ?? null;
                if (!is_array($card) || !is_array($holder)) {
                    throw new InvalidArgumentException('Dados do cartão incompletos.');
                }
                $cardPayload = [
                    'holderName' => (string) ($card['holderName'] ?? ''),
                    'number' => preg_replace('/\D+/', '', (string) ($card['number'] ?? '')) ?? '',
                    'expiryMonth' => (string) ($card['expiryMonth'] ?? ''),
                    'expiryYear' => (string) ($card['expiryYear'] ?? ''),
                    'ccv' => (string) ($card['ccv'] ?? ''),
                ];
                $holderPayload = [
                    'name' => (string) ($holder['name'] ?? $customerData['name']),
                    'email' => (string) ($holder['email'] ?? $customerData['email']),
                    'cpfCnpj' => (string) ($holder['cpfCnpj'] ?? $customerData['cpf_cnpj']),
                    'postalCode' => (string) ($holder['postalCode'] ?? $customerData['postal_code']),
                    'addressNumber' => (string) ($holder['addressNumber'] ?? $customerData['address_number']),
                    'phone' => (string) ($holder['phone'] ?? $customerData['phone']),
                    'mobilePhone' => (string) ($holder['mobilePhone'] ?? $customerData['mobile_phone']),
                ];
                $paymentPayload['creditCard'] = $cardPayload;
                $paymentPayload['creditCardHolderInfo'] = $holderPayload;
                $paymentPayload['remoteIp'] = $this->clientIp();
            }

            $payment = $this->asaas->createPayment($paymentPayload, $idempotencyKey);
            unset($paymentPayload['creditCard'], $input['creditCard']);

            $status = $this->mapAsaasPaymentStatus((string) ($payment['status'] ?? 'PENDING'));
            $pixQr = null;
            $pixCopy = null;
            $boletoUrl = null;
            $boletoField = null;
            $cardBrand = null;
            $cardLast4 = null;
            $creditCardToken = null;

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
                        geekhost_log('pix_qr_fetch_failed', ['order' => $publicId, 'attempt' => $attempt, 'error' => $e->getMessage()]);
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
                $creditCardToken = isset($payment['creditCard']['creditCardToken']) ? (string) $payment['creditCard']['creditCardToken'] : null;
            }

            $localSubId = $this->insertLocalSubscription([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'plan_code' => $selection['plan']['code'],
                'billing_period' => $periodCode,
                'domain' => $domain,
                'amount_cents' => $amountCents,
                'renew_amount_cents' => $renewCents,
                'status' => 'pending',
                'asaas_credit_card_token' => $creditCardToken,
            ], $now);

            $asaasSubscriptionId = null;
            if ($status === 'paid_pending_provisioning') {
                $asaasSubscriptionId = $this->ensureAsaasSubscription(
                    $asaasCustomerId,
                    $selection,
                    $periodCode,
                    $domain,
                    $billingType,
                    $creditCardToken,
                    $cardPayload,
                    $holderPayload,
                    $publicId
                );
                $this->activateLocalSubscription($localSubId, $asaasSubscriptionId, $periodCode, $now);
            }

            $upd = $this->db->prepare(
                'UPDATE orders SET
                    asaas_payment_id = :pid,
                    asaas_subscription_id = :sid,
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
                ':sid' => $asaasSubscriptionId,
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

            if ($userId > 0 && ($cardBrand || $cardLast4 || $creditCardToken)) {
                $u = $this->db->prepare('UPDATE users SET card_brand = COALESCE(:b, card_brand), card_last4 = COALESCE(:l, card_last4), asaas_credit_card_token = COALESCE(:t, asaas_credit_card_token), updated_at = :u WHERE id = :id');
                $u->execute([':b' => $cardBrand, ':l' => $cardLast4, ':t' => $creditCardToken, ':u' => $now, ':id' => $userId]);
            }

            if ($status === 'paid_pending_provisioning') {
                $this->createServiceForOrder($orderId, $userId, $localSubId, $selection['plan']['code'], $domain, $now);
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
                'domain' => $domain,
                'status' => $order['status'],
            ]);

            $presented = $this->presentOrder($order);
            $presented['panelLoginHint'] = $customerData['email'];
            $presented['passwordSetupRequired'] = $setupToken !== null;
            if ($setupToken !== null) {
                $presented['passwordSetupToken'] = $setupToken;
                $presented['passwordSetupUrl'] = (function_exists('url') ? url('/painel/criar-senha') : '/painel/criar-senha')
                    . '?token=' . rawurlencode($setupToken);
            }
            return $presented;
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
            geekhost_log('webhook_duplicate', ['event_key' => $eventKey, 'event' => $eventType]);
            return;
        }

        $relevant = [
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE',
            'PAYMENT_REFUNDED', 'PAYMENT_DELETED', 'PAYMENT_RECEIVED_IN_CASH',
        ];
        if (!in_array($eventType, $relevant, true) || $paymentId === '') {
            $this->markWebhookProcessed($eventKey, $now);
            return;
        }

        $fresh = $this->asaas->getPayment($paymentId);
        $order = $this->findOrderByAsaasPaymentId($paymentId);
        if (!$order && !empty($fresh['externalReference'])) {
            $order = $this->findOrderByPublicId((string) $fresh['externalReference']);
        }

        // Renewal payments from subscription may not match initial order externalReference.
        if (!$order && !empty($fresh['subscription'])) {
            $order = $this->findLatestOrderBySubscription((string) $fresh['subscription']);
            if ($order && in_array($eventType, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH', 'PAYMENT_OVERDUE', 'PAYMENT_REFUNDED'], true)) {
                $order = $this->spawnRenewalInvoiceFromSubscription($order, $fresh, $now) ?? $order;
            }
        }

        if (!$order) {
            geekhost_log('webhook_order_missing', ['payment_id' => $paymentId, 'event' => $eventType]);
            $this->markWebhookProcessed($eventKey, $now);
            return;
        }

        $newStatus = $this->mapAsaasPaymentStatus((string) ($fresh['status'] ?? ''), $eventType);
        $paidAt = $newStatus === 'paid_pending_provisioning' ? ($order['paid_at'] ?: $now) : $order['paid_at'];

        $upd = $this->db->prepare(
            'UPDATE orders SET status = :s, paid_at = :p, updated_at = :u,
                asaas_invoice_url = COALESCE(:inv, asaas_invoice_url),
                boleto_url = COALESCE(:bol, boleto_url),
                asaas_payment_id = COALESCE(asaas_payment_id, :pid)
             WHERE id = :id'
        );
        $upd->execute([
            ':s' => $newStatus,
            ':p' => $paidAt,
            ':u' => $now,
            ':inv' => isset($fresh['invoiceUrl']) ? (string) $fresh['invoiceUrl'] : null,
            ':bol' => isset($fresh['bankSlipUrl']) ? (string) $fresh['bankSlipUrl'] : null,
            ':pid' => $paymentId,
            ':id' => $order['id'],
        ]);

        $updated = $this->findOrderByPublicId((string) $order['public_id']);
        if ($updated && $updated['status'] === 'paid_pending_provisioning') {
            $this->onOrderPaid($updated, $now);
        }

        if ($updated && $updated['status'] === 'overdue') {
            $this->markSubscriptionOverdue((string) ($updated['asaas_subscription_id'] ?? ''), (int) ($updated['user_id'] ?? 0), $now);
            $this->enforceOverdueSuspension((int) ($updated['user_id'] ?? 0), $now);
        }

        if ($updated && $updated['status'] === 'paid_pending_provisioning') {
            $this->clearOverdue((int) ($updated['user_id'] ?? 0), (string) ($updated['asaas_subscription_id'] ?? ''), $now);
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
     * @param array<string, mixed> $card
     * @param array<string, mixed> $holder
     * @return array<string, mixed>
     */
    public function updateRecurringCard(int $userId, array $card, array $holder): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM subscriptions WHERE user_id = :u AND asaas_subscription_id IS NOT NULL AND status IN ('active','pending','overdue') ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $sub = $stmt->fetch();
        if (!$sub || empty($sub['asaas_subscription_id'])) {
            throw new InvalidArgumentException('Nenhuma assinatura ativa para atualizar o cartão.');
        }

        $payload = [
            'billingType' => 'CREDIT_CARD',
            'creditCard' => [
                'holderName' => (string) ($card['holderName'] ?? ''),
                'number' => preg_replace('/\D+/', '', (string) ($card['number'] ?? '')) ?? '',
                'expiryMonth' => (string) ($card['expiryMonth'] ?? ''),
                'expiryYear' => (string) ($card['expiryYear'] ?? ''),
                'ccv' => (string) ($card['ccv'] ?? ''),
            ],
            'creditCardHolderInfo' => [
                'name' => (string) ($holder['name'] ?? ''),
                'email' => (string) ($holder['email'] ?? ''),
                'cpfCnpj' => preg_replace('/\D+/', '', (string) ($holder['cpfCnpj'] ?? '')) ?? '',
                'postalCode' => preg_replace('/\D+/', '', (string) ($holder['postalCode'] ?? '')) ?? '',
                'addressNumber' => (string) ($holder['addressNumber'] ?? ''),
                'phone' => preg_replace('/\D+/', '', (string) ($holder['phone'] ?? '')) ?? '',
                'mobilePhone' => preg_replace('/\D+/', '', (string) ($holder['mobilePhone'] ?? $holder['phone'] ?? '')) ?? '',
            ],
            'remoteIp' => $this->clientIp(),
        ];

        $updated = $this->asaas->updateSubscriptionCreditCard((string) $sub['asaas_subscription_id'], $payload);
        $brand = null;
        $last4 = null;
        $token = null;
        if (isset($updated['creditCard']) && is_array($updated['creditCard'])) {
            $brand = isset($updated['creditCard']['creditCardBrand']) ? (string) $updated['creditCard']['creditCardBrand'] : null;
            $last4 = isset($updated['creditCard']['creditCardNumber']) ? substr((string) $updated['creditCard']['creditCardNumber'], -4) : null;
            $token = isset($updated['creditCard']['creditCardToken']) ? (string) $updated['creditCard']['creditCardToken'] : null;
        }

        $now = gmdate('c');
        $this->db->prepare('UPDATE subscriptions SET asaas_credit_card_token = COALESCE(:t, asaas_credit_card_token), updated_at = :u WHERE id = :id')
            ->execute([':t' => $token, ':u' => $now, ':id' => $sub['id']]);
        $this->db->prepare('UPDATE users SET card_brand = COALESCE(:b, card_brand), card_last4 = COALESCE(:l, card_last4), asaas_credit_card_token = COALESCE(:t, asaas_credit_card_token), updated_at = :u WHERE id = :id')
            ->execute([':b' => $brand, ':l' => $last4, ':t' => $token, ':u' => $now, ':id' => $userId]);

        return ['brand' => $brand, 'last4' => $last4];
    }

    /** @return list<array<string, mixed>> */
    /**
     * @param array{status?:?string,q?:?string,from?:?string,to?:?string}|null $filters
     * @return list<array<string, mixed>>
     */
    public function listInvoicesForUser(int $userId, ?string $filter = null, ?array $filters = null): array
    {
        $filters = $filters ?? [];
        if ($filter !== null && $filter !== '' && empty($filters['status'])) {
            $filters['status'] = $filter;
        }

        $sql = 'SELECT * FROM orders WHERE user_id = :u';
        $params = [':u' => $userId];
        $map = [
            'pago' => ['paid_pending_provisioning'],
            'em_aberto' => ['awaiting_payment', 'overdue'],
            'cancelado' => ['canceled'],
            'reembolsado' => ['refunded'],
        ];
        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ($status !== '' && isset($map[$status])) {
            $placeholders = [];
            foreach ($map[$status] as $i => $st) {
                $key = ':s' . $i;
                $placeholders[] = $key;
                $params[$key] = $st;
            }
            $sql .= ' AND status IN (' . implode(',', $placeholders) . ')';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                CAST(invoice_number AS CHAR) LIKE :q
                OR plan_name LIKE :q
                OR domain LIKE :q
                OR billing_type LIKE :q
                OR public_id LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        $dateExpr = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'COALESCE(due_date, DATE(created_at))'
            : "COALESCE(due_date, substr(created_at, 1, 10))";

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $sql .= ' AND (' . $dateExpr . ' >= :from)';
            $params[':from'] = $from;
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $sql .= ' AND (' . $dateExpr . ' <= :to)';
            $params[':to'] = $to;
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($row) => $this->presentOrder($row), $rows ?: []);
    }

    /** @return array<string, int> */
    public function invoiceCounts(int $userId): array
    {
        $rows = $this->db->prepare('SELECT status, COUNT(*) AS c FROM orders WHERE user_id = :u GROUP BY status');
        $rows->execute([':u' => $userId]);
        $counts = ['pago' => 0, 'em_aberto' => 0, 'cancelado' => 0, 'reembolsado' => 0, 'total' => 0];
        foreach ($rows->fetchAll() ?: [] as $row) {
            $c = (int) $row['c'];
            $counts['total'] += $c;
            $status = (string) $row['status'];
            if ($status === 'paid_pending_provisioning') {
                $counts['pago'] += $c;
            } elseif (in_array($status, ['awaiting_payment', 'overdue'], true)) {
                $counts['em_aberto'] += $c;
            } elseif ($status === 'canceled') {
                $counts['cancelado'] += $c;
            } elseif ($status === 'refunded') {
                $counts['reembolsado'] += $c;
            }
        }
        return $counts;
    }

    /** @return array<string, mixed>|null */
    public function findOrderByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE public_id = :id LIMIT 1');
        $stmt->execute([':id' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $order @return array<string, mixed> */
    public function presentOrder(array $order): array
    {
        $status = (string) $order['status'];
        $badge = match ($status) {
            'paid_pending_provisioning' => 'Pago',
            'awaiting_payment' => 'Em aberto',
            'overdue' => 'Em aberto',
            'canceled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => $status,
        };
        $periodCode = geekhost_normalize_period((string) ($order['billing_period'] ?? 'mensal'));
        $selection = geekhost_resolve_checkout_selection((string) ($order['plan_code'] ?? ''), $periodCode);
        $periodLabel = $selection['period']['label'] ?? match ($periodCode) {
            'semestral' => 'Semestral',
            'anual' => 'Anual',
            default => 'Mensal',
        };
        $billingCycle = $selection['period']['cycle'] ?? match ($periodCode) {
            'semestral' => 'SEMIANNUALLY',
            'anual' => 'YEARLY',
            default => 'MONTHLY',
        };
        $meta = [];
        if (!empty($order['metadata_json'])) {
            $decoded = json_decode((string) $order['metadata_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
                if (!empty($meta['period_label'])) {
                    $periodLabel = (string) $meta['period_label'];
                }
                if (!empty($meta['asaas_cycle'])) {
                    $billingCycle = (string) $meta['asaas_cycle'];
                }
            }
        }
        return [
            'id' => $order['public_id'],
            'invoiceNumber' => $order['invoice_number'] ?? null,
            'status' => $status,
            'statusLabel' => $badge,
            'planCode' => $order['plan_code'],
            'planName' => $order['plan_name'],
            'billingPeriod' => $periodCode,
            'periodLabel' => $periodLabel,
            'billingCycle' => $billingCycle,
            'domain' => $order['domain'] ?? null,
            'billingType' => $order['billing_type'],
            'amount' => round(((int) $order['amount_cents']) / 100, 2),
            'amountFormatted' => geekhost_format_brl(((int) $order['amount_cents']) / 100),
            'renewAmount' => isset($order['renew_amount_cents']) ? round(((int) $order['renew_amount_cents']) / 100, 2) : null,
            'dueDate' => $order['due_date'] ?? null,
            'createdAt' => $order['created_at'] ?? null,
            'paidAt' => $order['paid_at'] ?? null,
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
            'environment' => $this->asaas->environment(),
        ];
    }

    /** @param array<string, mixed> $order */
    private function onOrderPaid(array $order, string $now): void
    {
        if (($order['provisioning_status'] ?? 'none') === 'none') {
            ProvisioningStub::queue($order);
        }

        $customerId = (int) $order['customer_id'];
        $userId = (int) ($order['user_id'] ?? 0);
        $periodCode = geekhost_normalize_period((string) $order['billing_period']);
        $selection = geekhost_resolve_checkout_selection((string) $order['plan_code'], $periodCode);
        if ($selection === null) {
            return;
        }

        $stmt = $this->db->prepare('SELECT asaas_customer_id FROM customers WHERE id = :id');
        $stmt->execute([':id' => $customerId]);
        $asaasCustomerId = (string) ($stmt->fetchColumn() ?: '');

        if ($asaasCustomerId !== '' && empty($order['asaas_subscription_id'])) {
            try {
                $subId = $this->ensureAsaasSubscription(
                    $asaasCustomerId,
                    $selection,
                    $periodCode,
                    (string) ($order['domain'] ?? ''),
                    (string) $order['billing_type'],
                    null,
                    null,
                    null,
                    (string) $order['public_id']
                );
                $this->db->prepare('UPDATE orders SET asaas_subscription_id = :s, updated_at = :u WHERE id = :id')
                    ->execute([':s' => $subId, ':u' => $now, ':id' => $order['id']]);

                $local = $this->db->prepare('SELECT id FROM subscriptions WHERE user_id = :u AND plan_code = :p ORDER BY id DESC LIMIT 1');
                $local->execute([':u' => $userId, ':p' => $order['plan_code']]);
                $localId = (int) ($local->fetchColumn() ?: 0);
                if ($localId > 0) {
                    $this->activateLocalSubscription($localId, $subId, $periodCode, $now);
                }
            } catch (Throwable $e) {
                geekhost_log('subscription_create_failed', ['order' => $order['public_id'], 'error' => $e->getMessage()]);
            }
        }

        $this->createServiceForOrder((int) $order['id'], $userId, null, (string) $order['plan_code'], (string) ($order['domain'] ?? ''), $now);
        if ($userId > 0) {
            $this->db->prepare("UPDATE users SET hosting_status = CASE WHEN hosting_status = 'suspended' THEN hosting_status ELSE 'pending' END, updated_at = :u WHERE id = :id")
                ->execute([':u' => $now, ':id' => $userId]);
        }
    }

    /**
     * @param array{plan:array,period:array,amount:float,renew_amount:float} $selection
     * @param array<string,mixed>|null $card
     * @param array<string,mixed>|null $holder
     */
    private function ensureAsaasSubscription(
        string $asaasCustomerId,
        array $selection,
        string $periodCode,
        string $domain,
        string $billingType,
        ?string $creditCardToken,
        ?array $card,
        ?array $holder,
        string $externalReference
    ): string {
        $nextDue = geekhost_period_end_date($periodCode);
        $payload = [
            'customer' => $asaasCustomerId,
            'billingType' => $billingType === 'CREDIT_CARD' ? 'CREDIT_CARD' : $billingType,
            'value' => round($selection['renew_amount'], 2),
            'nextDueDate' => $nextDue,
            'cycle' => $selection['period']['cycle'],
            'description' => $selection['plan']['name'] . ' renovação — ' . $domain,
            'externalReference' => 'sub-' . $externalReference,
        ];

        if ($billingType === 'CREDIT_CARD') {
            if ($creditCardToken) {
                $payload['creditCardToken'] = $creditCardToken;
            } elseif ($card && $holder) {
                $payload['creditCard'] = $card;
                $payload['creditCardHolderInfo'] = $holder;
                $payload['remoteIp'] = $this->clientIp();
            }
        }

        $created = $this->asaas->createSubscription($payload, 'sub-' . $externalReference);
        return (string) ($created['id'] ?? '');
    }

    /** @param array<string, mixed> $data */
    private function insertLocalSubscription(array $data, string $now): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscriptions (
                public_id, customer_id, user_id, plan_code, billing_period, domain,
                amount_cents, renew_amount_cents, status, asaas_credit_card_token, created_at, updated_at
            ) VALUES (
                :p, :c, :u, :plan, :bp, :d, :a, :r, :st, :tok, :c1, :u1
            )'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':c' => $data['customer_id'],
            ':u' => $data['user_id'] ?: null,
            ':plan' => $data['plan_code'],
            ':bp' => $data['billing_period'],
            ':d' => $data['domain'],
            ':a' => $data['amount_cents'],
            ':r' => $data['renew_amount_cents'],
            ':st' => $data['status'],
            ':tok' => $data['asaas_credit_card_token'],
            ':c1' => $now,
            ':u1' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function activateLocalSubscription(int $localId, ?string $asaasId, string $periodCode, string $now): void
    {
        if ($localId <= 0) {
            return;
        }
        $this->db->prepare(
            'UPDATE subscriptions SET asaas_subscription_id = COALESCE(:a, asaas_subscription_id), status = :st, next_due_date = :n, updated_at = :u WHERE id = :id'
        )->execute([
            ':a' => $asaasId,
            ':st' => 'active',
            ':n' => geekhost_period_end_date($periodCode),
            ':u' => $now,
            ':id' => $localId,
        ]);
    }

    private function createServiceForOrder(int $orderId, int $userId, ?int $subscriptionId, string $planCode, string $domain, string $now): void
    {
        if ($userId <= 0) {
            return;
        }
        $check = $this->db->prepare('SELECT id FROM services WHERE order_id = :o LIMIT 1');
        $check->execute([':o' => $orderId]);
        if ($check->fetch()) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO services (public_id, user_id, order_id, subscription_id, plan_code, domain, status, created_at, updated_at)
             VALUES (:p, :u, :o, :s, :plan, :d, :st, :c, :up)'
        )->execute([
            ':p' => $this->uuid(),
            ':u' => $userId,
            ':o' => $orderId,
            ':s' => $subscriptionId,
            ':plan' => $planCode,
            ':d' => $domain,
            ':st' => 'paid_pending_provisioning',
            ':c' => $now,
            ':up' => $now,
        ]);
    }

    private function markSubscriptionOverdue(string $asaasSubscriptionId, int $userId, string $now): void
    {
        if ($asaasSubscriptionId === '') {
            return;
        }
        $this->db->prepare(
            "UPDATE subscriptions SET status = 'overdue', overdue_since = COALESCE(overdue_since, :o), updated_at = :u
             WHERE asaas_subscription_id = :id"
        )->execute([':o' => $now, ':u' => $now, ':id' => $asaasSubscriptionId]);
    }

    private function clearOverdue(int $userId, string $asaasSubscriptionId, string $now): void
    {
        if ($asaasSubscriptionId !== '') {
            $this->db->prepare("UPDATE subscriptions SET status = 'active', overdue_since = NULL, updated_at = :u WHERE asaas_subscription_id = :id")
                ->execute([':u' => $now, ':id' => $asaasSubscriptionId]);
        }
    }

    private function enforceOverdueSuspension(int $userId, string $now): void
    {
        if ($userId <= 0) {
            return;
        }
        $stmt = $this->db->prepare(
            "SELECT overdue_since FROM subscriptions WHERE user_id = :u AND status = 'overdue' AND overdue_since IS NOT NULL ORDER BY overdue_since ASC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $since = $stmt->fetchColumn();
        if (!$since) {
            return;
        }
        $threshold = strtotime((string) $since . ' +2 months');
        if ($threshold === false || $threshold > time()) {
            return;
        }

        $user = $this->auth->findById($userId);
        if ($user && ($user['hosting_status'] ?? '') === 'suspended') {
            return; // idempotent
        }

        $this->db->prepare(
            "UPDATE users SET hosting_status = 'suspended', suspended_at = COALESCE(suspended_at, :s), suspend_reason = :r, updated_at = :u WHERE id = :id"
        )->execute([
            ':s' => $now,
            ':r' => 'Pagamento em atraso há mais de 2 meses',
            ':u' => $now,
            ':id' => $userId,
        ]);
        $this->db->prepare(
            "UPDATE services SET status = 'suspended', updated_at = :u WHERE user_id = :id AND status != 'canceled'"
        )->execute([':u' => $now, ':id' => $userId]);
        geekhost_log('hosting_suspended_overdue', ['user_id' => $userId]);
    }

    /**
     * @param array<string, mixed> $parentOrder
     * @param array<string, mixed> $payment
     * @return array<string, mixed>|null
     */
    private function spawnRenewalInvoiceFromSubscription(array $parentOrder, array $payment, string $now): ?array
    {
        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            return null;
        }
        $existing = $this->findOrderByAsaasPaymentId($paymentId);
        if ($existing) {
            return $existing;
        }

        $amount = (float) ($payment['value'] ?? 0);
        $publicId = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO orders (
                public_id, invoice_number, customer_id, user_id, plan_code, plan_name, billing_period, domain,
                amount_cents, renew_amount_cents, currency, billing_type, status, due_date,
                asaas_payment_id, asaas_subscription_id, asaas_invoice_url, idempotency_key,
                provisioning_status, metadata_json, created_at, updated_at
            ) VALUES (
                :pid, :inv, :cid, :uid, :pc, :pn, :bp, :d,
                :ac, :rc, :cur, :bt, :st, :due,
                :ap, :asub, :url, :idem,
                :prov, :meta, :c, :u
            )'
        );
        $stmt->execute([
            ':pid' => $publicId,
            ':inv' => $this->nextInvoiceNumber(),
            ':cid' => $parentOrder['customer_id'],
            ':uid' => $parentOrder['user_id'],
            ':pc' => $parentOrder['plan_code'],
            ':pn' => $parentOrder['plan_name'],
            ':bp' => $parentOrder['billing_period'],
            ':d' => $parentOrder['domain'],
            ':ac' => (int) round($amount * 100),
            ':rc' => $parentOrder['renew_amount_cents'],
            ':cur' => 'BRL',
            ':bt' => $payment['billingType'] ?? $parentOrder['billing_type'],
            ':st' => 'awaiting_payment',
            ':due' => $payment['dueDate'] ?? gmdate('Y-m-d'),
            ':ap' => $paymentId,
            ':asub' => $payment['subscription'] ?? $parentOrder['asaas_subscription_id'],
            ':url' => $payment['invoiceUrl'] ?? null,
            ':idem' => 'renew-' . $paymentId,
            ':prov' => 'none',
            ':meta' => json_encode(['renewal' => true], JSON_UNESCAPED_UNICODE),
            ':c' => $now,
            ':u' => $now,
        ]);
        return $this->findOrderByPublicId($publicId);
    }

    private function mapAsaasPaymentStatus(string $asaasStatus, string $event = ''): string
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
        return 'awaiting_payment';
    }

    /** @param array<string, mixed> $input @return array<string, string> */
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

    /** @param array<string, string> $data */
    private function upsertLocalCustomer(array $data, string $now): int
    {
        $stmt = $this->db->prepare('SELECT id FROM customers WHERE cpf_cnpj = :c LIMIT 1');
        $stmt->execute([':c' => $data['cpf_cnpj']]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = $this->db->prepare(
                'UPDATE customers SET email=:e, name=:n, phone=:p, mobile_phone=:m, postal_code=:z,
                    address=:a, address_number=:num, complement=:comp, province=:uf, city=:city, updated_at=:u
                 WHERE id=:id'
            );
            $upd->execute([
                ':e' => $data['email'], ':n' => $data['name'], ':p' => $data['phone'], ':m' => $data['mobile_phone'],
                ':z' => $data['postal_code'], ':a' => $data['address'], ':num' => $data['address_number'],
                ':comp' => $data['complement'], ':uf' => $data['province'], ':city' => $data['city'],
                ':u' => $now, ':id' => $row['id'],
            ]);
            return (int) $row['id'];
        }

        $ins = $this->db->prepare(
            'INSERT INTO customers (email, name, cpf_cnpj, phone, mobile_phone, postal_code, address, address_number, complement, province, city, created_at, updated_at)
             VALUES (:e,:n,:c,:p,:m,:z,:a,:num,:comp,:uf,:city,:c1,:u)'
        );
        $ins->execute([
            ':e' => $data['email'], ':n' => $data['name'], ':c' => $data['cpf_cnpj'], ':p' => $data['phone'],
            ':m' => $data['mobile_phone'], ':z' => $data['postal_code'], ':a' => $data['address'],
            ':num' => $data['address_number'], ':comp' => $data['complement'], ':uf' => $data['province'],
            ':city' => $data['city'], ':c1' => $now, ':u' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, string> $data */
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
        $this->db->prepare('UPDATE customers SET asaas_customer_id = :a, updated_at = :u WHERE id = :id')
            ->execute([':a' => $asaasId, ':u' => gmdate('c'), ':id' => $customerId]);
        return $asaasId;
    }

    private function nextInvoiceNumber(): int
    {
        $max = (int) ($this->db->query('SELECT MAX(invoice_number) FROM orders')->fetchColumn() ?: 100000);
        return max(100000, $max) + 1;
    }

    /** @return array<string, mixed>|null */
    private function findOrderByIdempotency(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE idempotency_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findOrderByAsaasPaymentId(string $paymentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE asaas_payment_id = :id LIMIT 1');
        $stmt->execute([':id' => $paymentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findLatestOrderBySubscription(string $subscriptionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE asaas_subscription_id = :id ORDER BY id DESC LIMIT 1');
        $stmt->execute([':id' => $subscriptionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function markWebhookProcessed(string $eventKey, string $now): void
    {
        $this->db->prepare('UPDATE webhook_events SET processed_at = :p WHERE event_key = :k')
            ->execute([':p' => $now, ':k' => $eventKey]);
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
