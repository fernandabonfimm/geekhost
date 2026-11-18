<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';

/**
 * Central Asaas API client. Selects URL / API key / webhook token from ASAAS_ENV only.
 */
final class AsaasClient
{
    private string $env;
    private string $baseUrl;
    private string $apiKey;
    private string $webhookToken;
    private int $timeout;

    public function __construct(?string $forceEnv = null)
    {
        geekhost_load_env();
        $this->env = geekhost_resolve_asaas_env($forceEnv);
        $prefix = $this->env === 'production' ? 'ASAAS_PRODUCTION' : 'ASAAS_SANDBOX';
        $this->baseUrl = rtrim((string) env($prefix . '_URL', ''), '/');
        $this->apiKey = (string) env($prefix . '_API_KEY', '');
        $this->webhookToken = (string) env($prefix . '_WEBHOOK_TOKEN', '');
        $this->timeout = 30;

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Asaas credentials missing for environment: ' . $this->env);
        }
    }

    public function environment(): string
    {
        return $this->env;
    }

    public function webhookToken(): string
    {
        return $this->webhookToken;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status:int, body:array<string,mixed>|list<mixed>|null, raw:string}
     */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: GeekHost-Checkout/1.0',
            'access_token: ' . $this->apiKey,
        ];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to init cURL.');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $ca = $this->caBundlePath();
        if ($ca !== null) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno !== 0) {
            geekhost_log('asaas_curl_error', ['errno' => $errno, 'error' => $error, 'path' => $path]);
            throw new RuntimeException('Falha de comunicação com o gateway de pagamento.');
        }

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }

        if ($status >= 400) {
            geekhost_log('asaas_http_error', [
                'status' => $status,
                'path' => $path,
                'errors' => is_array($decoded) ? ($decoded['errors'] ?? null) : null,
            ]);
        }

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => is_string($raw) ? $raw : '',
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createCustomer(array $payload): array
    {
        $res = $this->request('POST', '/customers', $payload);
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Não foi possível criar o cliente no gateway de pagamento.'));
        }
        return $res['body'];
    }

    /** @return array<string, mixed>|null */
    public function findCustomerByCpfCnpj(string $cpfCnpj): ?array
    {
        $res = $this->request('GET', '/customers?cpfCnpj=' . rawurlencode($cpfCnpj) . '&limit=1');
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            return null;
        }
        $data = $res['body']['data'] ?? [];
        if (!is_array($data) || $data === []) {
            return null;
        }
        $first = $data[0];
        return is_array($first) ? $first : null;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $res = $this->request('POST', '/payments', $payload, $idempotencyKey);
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Não foi possível criar a cobrança.'));
        }
        return $res['body'];
    }

    /** @return array<string, mixed> */
    public function getPayment(string $paymentId): array
    {
        $res = $this->request('GET', '/payments/' . rawurlencode($paymentId));
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Cobrança não encontrada no gateway de pagamento.'));
        }
        return $res['body'];
    }

    /** @return array<string, mixed> */
    public function getPixQrCode(string $paymentId): array
    {
        $res = $this->request('GET', '/payments/' . rawurlencode($paymentId) . '/pixQrCode');
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Não foi possível obter o QR Code Pix.'));
        }
        return $res['body'];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createSubscription(array $payload, ?string $idempotencyKey = null): array
    {
        $res = $this->request('POST', '/subscriptions', $payload, $idempotencyKey);
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Não foi possível criar a assinatura.'));
        }
        return $res['body'];
    }

    /** @return array<string, mixed> */
    public function getSubscription(string $subscriptionId): array
    {
        $res = $this->request('GET', '/subscriptions/' . rawurlencode($subscriptionId));
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Assinatura não encontrada.'));
        }
        return $res['body'];
    }

    /**
     * Update subscription credit card (tokenized via card data sent once to Asaas).
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSubscriptionCreditCard(string $subscriptionId, array $payload): array
    {
        $res = $this->request('PUT', '/subscriptions/' . rawurlencode($subscriptionId), $payload);
        if ($res['status'] >= 400 || !is_array($res['body'])) {
            throw new RuntimeException($this->errorMessage($res, 'Não foi possível atualizar o cartão da assinatura.'));
        }
        return $res['body'];
    }

    /** @param array{status:int, body:array<string,mixed>|list<mixed>|null, raw:string} $res */
    private function errorMessage(array $res, string $fallback): string
    {
        $body = $res['body'];
        if (is_array($body) && isset($body['errors'][0]['description'])) {
            return (string) $body['errors'][0]['description'];
        }
        return $fallback;
    }

    private function caBundlePath(): ?string
    {
        $configured = env('SSL_CERT_FILE', env('ASAAS_CA_BUNDLE'));
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }
        $local = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cacert.pem';
        if (is_readable($local)) {
            return $local;
        }
        return null;
    }
}
