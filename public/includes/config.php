<?php

declare(strict_types=1);

$siteName = 'GeekHost';
$companyEmail = filter_var(getenv('GEEKHOST_CONTACT_EMAIL') ?: '', FILTER_VALIDATE_EMAIL) ?: '';
$companyWhatsapp = preg_replace('/\D+/', '', getenv('GEEKHOST_WHATSAPP') ?: '');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
