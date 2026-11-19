<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

$map = [
    '/checkout' => '/checkout.php',
    '/checkout/' => '/checkout.php',
    '/api/checkout/create' => '/api/checkout/create.php',
    '/api/checkout/create/' => '/api/checkout/create.php',
    '/api/checkout/status' => '/api/checkout/status.php',
    '/api/checkout/status/' => '/api/checkout/status.php',
    '/api/webhooks/asaas' => '/api/webhooks/asaas.php',
    '/api/webhooks/asaas/' => '/api/webhooks/asaas.php',
    '/painel' => '/painel/index.php',
    '/painel/' => '/painel/index.php',
    '/painel/login' => '/painel/login.php',
    '/painel/login/' => '/painel/login.php',
    '/painel/logout' => '/painel/logout.php',
    '/painel/logout/' => '/painel/logout.php',
    '/painel/faturas' => '/painel/faturas.php',
    '/painel/faturas/' => '/painel/faturas.php',
    '/painel/perfil' => '/painel/perfil.php',
    '/painel/perfil/' => '/painel/perfil.php',
    '/painel/carteira' => '/painel/carteira.php',
    '/painel/carteira/' => '/painel/carteira.php',
    '/painel/dominios' => '/painel/dominios.php',
    '/painel/dominios/' => '/painel/dominios.php',
    '/painel/sites' => '/painel/sites.php',
    '/painel/sites/' => '/painel/sites.php',
    '/painel/suporte' => '/painel/suporte.php',
    '/painel/suporte/' => '/painel/suporte.php',
    '/painel/admin/tickets' => '/painel/admin/tickets.php',
    '/painel/admin/tickets/' => '/painel/admin/tickets.php',
    '/painel/criar-senha' => '/painel/criar-senha.php',
    '/painel/criar-senha/' => '/painel/criar-senha.php',
    '/api/painel/me' => '/api/painel/me.php',
    '/api/painel/me/' => '/api/painel/me.php',
    '/api/painel/faturas' => '/api/painel/faturas.php',
    '/api/painel/faturas/' => '/api/painel/faturas.php',
    '/api/painel/tickets' => '/api/painel/tickets.php',
    '/api/painel/tickets/' => '/api/painel/tickets.php',
    '/api/painel/tickets-attachment' => '/api/painel/tickets-attachment.php',
    '/api/painel/tickets-attachment/' => '/api/painel/tickets-attachment.php',
    '/api/painel/notifications' => '/api/painel/notifications.php',
    '/api/painel/notifications/' => '/api/painel/notifications.php',
    '/api/admin/tickets' => '/api/admin/tickets.php',
    '/api/admin/tickets/' => '/api/admin/tickets.php',
    '/api/admin/hosting' => '/api/admin/hosting.php',
    '/api/admin/hosting/' => '/api/admin/hosting.php',
    // Standalone GeekHost Admin (not client /painel)
    '/admin' => '/admin/index.php',
    '/admin/' => '/admin/index.php',
    '/admin/login' => '/admin/login.php',
    '/admin/login/' => '/admin/login.php',
    '/admin/logout' => '/admin/logout.php',
    '/admin/logout/' => '/admin/logout.php',
    '/admin/vendas' => '/admin/vendas.php',
    '/admin/vendas/' => '/admin/vendas.php',
    '/admin/faturamento' => '/admin/faturamento.php',
    '/admin/faturamento/' => '/admin/faturamento.php',
    '/admin/cobrancas' => '/admin/cobrancas.php',
    '/admin/cobrancas/' => '/admin/cobrancas.php',
    '/admin/clientes' => '/admin/clientes.php',
    '/admin/clientes/' => '/admin/clientes.php',
    '/admin/hospedagens' => '/admin/hospedagens.php',
    '/admin/hospedagens/' => '/admin/hospedagens.php',
    '/admin/dominios' => '/admin/dominios.php',
    '/admin/dominios/' => '/admin/dominios.php',
    '/admin/sites' => '/admin/sites.php',
    '/admin/sites/' => '/admin/sites.php',
    '/admin/planos' => '/admin/planos.php',
    '/admin/planos/' => '/admin/planos.php',
    '/admin/administradores' => '/admin/administradores.php',
    '/admin/administradores/' => '/admin/administradores.php',
    '/admin/relatorios' => '/admin/relatorios.php',
    '/admin/relatorios/' => '/admin/relatorios.php',
    '/admin/auditoria' => '/admin/auditoria.php',
    '/admin/auditoria/' => '/admin/auditoria.php',
    '/admin/configuracoes' => '/admin/configuracoes.php',
    '/admin/configuracoes/' => '/admin/configuracoes.php',
    '/admin/busca' => '/admin/busca.php',
    '/admin/busca/' => '/admin/busca.php',
];

if (isset($map[$uri])) {
    require __DIR__ . $map[$uri];
    return true;
}

$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

return false;
