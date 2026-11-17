<?php

declare(strict_types=1);

$siteName = 'GeekHost';
$companyEmail = filter_var(getenv('GEEKHOST_CONTACT_EMAIL') ?: 'contato@geekhost.com.br', FILTER_VALIDATE_EMAIL) ?: '';
$companyWhatsapp = preg_replace('/\D+/', '', getenv('GEEKHOST_WHATSAPP') ?: '5516997399174');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Root-absolute URL for a file under public_html (e.g. assets/images/logo.png).
 * Survives pretty URLs and DirectAdmin document-root deploys; local php -S -t public_html too.
 */
function asset(string $path): string
{
    return '/' . ltrim(str_replace('\\', '/', $path), '/');
}
