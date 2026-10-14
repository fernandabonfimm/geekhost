<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (!preg_match('/\A[0-9]{8,15}\z/', $companyWhatsapp)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Canal de WhatsApp indisponível.';
    exit;
}

$messages = [
    '' => 'Olá! Gostaria de falar com o suporte da GeekHost.',
    'light' => 'Olá! Tenho interesse no Plano Light e gostaria de tirar algumas dúvidas.',
    'empresa' => 'Olá! Tenho interesse no Plano Empresa e gostaria de tirar algumas dúvidas.',
    'premium' => 'Olá! Tenho interesse no Plano Premium e gostaria de tirar algumas dúvidas.',
];

$subject = $_GET['assunto'] ?? '';
if (!is_string($subject) || !array_key_exists($subject, $messages)) {
    $subject = '';
}

$destination = 'https://wa.me/' . $companyWhatsapp . '?text=' . rawurlencode($messages[$subject]);
header('Location: ' . $destination, true, 302);
exit;
