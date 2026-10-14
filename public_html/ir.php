<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function invalidTemporaryLink(int $status = 404): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Link temporário inválido ou indisponível.';
    exit;
}

$secret = (string) (getenv('GEEKHOST_LINK_SECRET') ?: '');
if (strlen($secret) < 32) {
    invalidTemporaryLink(503);
}

$token = $_GET['t'] ?? '';
if (!is_string($token) || strlen($token) > 4096 || !preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{64})\z/', $token, $matches)) {
    invalidTemporaryLink();
}

$encodedPayload = $matches[1];
$signature = $matches[2];
$expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    invalidTemporaryLink();
}

$base64 = strtr($encodedPayload, '-_', '+/');
$base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
$decodedPayload = base64_decode($base64, true);
if ($decodedPayload === false) {
    invalidTemporaryLink();
}

try {
    $payload = json_decode($decodedPayload, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    invalidTemporaryLink();
}

if (
    !is_array($payload)
    || !is_string($payload['number'] ?? null)
    || !preg_match('/\A[0-9]{4,15}\z/', $payload['number'])
    || !is_string($payload['message'] ?? null)
    || !is_int($payload['expires_at'] ?? null)
) {
    invalidTemporaryLink();
}

if ($payload['expires_at'] <= time()) {
    header('Location: planos.php?motivo=link-expirado', true, 302);
    exit;
}

$whatsappUrl = 'https://wa.me/' . $payload['number'];
if ($payload['message'] !== '') {
    $whatsappUrl .= '?text=' . rawurlencode($payload['message']);
}

header('Location: ' . $whatsappUrl, true, 302);
exit;
