<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondJson(405, ['error' => 'Método não permitido.']);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    respondJson(413, ['error' => 'Requisição muito grande.']);
}

$secret = (string) (getenv('GEEKHOST_LINK_SECRET') ?: '');
$publicUrl = rtrim((string) (getenv('GEEKHOST_PUBLIC_URL') ?: ''), '/');
$urlParts = parse_url($publicUrl);
$scheme = is_array($urlParts) ? strtolower((string) ($urlParts['scheme'] ?? '')) : '';
$host = is_array($urlParts) ? strtolower((string) ($urlParts['host'] ?? '')) : '';
$isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$isSecureUrl = $scheme === 'https' || ($scheme === 'http' && $isLocalHost);
$hasUrlCredentials = is_array($urlParts) && (isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['query']) || isset($urlParts['fragment']));

if (strlen($secret) < 32 || $host === '' || !$isSecureUrl || $hasUrlCredentials) {
    respondJson(503, ['error' => 'Links temporários indisponíveis. Confira a configuração do servidor.']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || strlen($rawBody) > 4096) {
    respondJson(400, ['error' => 'Não foi possível ler os dados enviados.']);
}

try {
    $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    respondJson(400, ['error' => 'Dados inválidos.']);
}

if (!is_array($data)) {
    respondJson(400, ['error' => 'Dados inválidos.']);
}

$number = $data['number'] ?? null;
$message = $data['message'] ?? '';
if (!is_string($number) || !preg_match('/\A[0-9]{4,15}\z/', $number) || !is_string($message)) {
    respondJson(422, ['error' => 'Informe um número de WhatsApp válido.']);
}

$messageLength = preg_match_all('/./us', $message);
if ($messageLength === false || $messageLength > 500) {
    respondJson(422, ['error' => 'A mensagem deve ter até 500 caracteres.']);
}

$expiresAt = time() + (7 * 24 * 60 * 60);
$payload = json_encode([
    'number' => $number,
    'message' => $message,
    'expires_at' => $expiresAt,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    respondJson(500, ['error' => 'Não foi possível criar o link.']);
}

$encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
$signature = hash_hmac('sha256', $encodedPayload, $secret);
$link = $publicUrl . '/ir.php?t=' . rawurlencode($encodedPayload . '.' . $signature);

respondJson(201, ['url' => $link, 'expiresAt' => $expiresAt]);
