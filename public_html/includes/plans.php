<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Official GeekHost hosting plan catalog — single source of truth for checkout amounts.
 * Checkout periods: mensal | semestral | anual.
 * DA packages: Plano-Light-GeekHost / Plano-Empresa-GeekHost / Plano-Premium-GeekHost.
 */
function geekhost_plans(): array
{
    return [
        'light' => [
            'code' => 'light',
            'name' => 'Plano Light',
            'tagline' => 'Para colocar seu site e suas ideias no ar.',
            'checkout_enabled' => true,
            'da_package' => 'Plano-Light-GeekHost',
            'max_domains' => 1,
            'max_sites' => 1,
            'features' => ['5 contas de e-mail', '10 GB de espaço', 'Banco de dados ilimitado'],
            'specs' => [
                'Disco' => '10 GB',
                'E-mails' => '5 contas',
                'Sites' => '1 site',
                'Painel' => 'cPanel',
            ],
            'periods' => [
                'mensal' => [
                    'label' => 'Mensal',
                    'amount' => 19.90,
                    'renew_amount' => 35.90,
                    'cycle' => 'MONTHLY',
                    'interval_months' => 1,
                    'promo' => true,
                ],
                'semestral' => [
                    'label' => 'Semestral',
                    'amount' => 239.00,
                    'renew_amount' => 239.00,
                    'cycle' => 'SEMIANNUALLY',
                    'interval_months' => 6,
                    'promo' => false,
                ],
                'anual' => [
                    'label' => 'Anual',
                    'amount' => 299.90,
                    'renew_amount' => 299.90,
                    'cycle' => 'YEARLY',
                    'interval_months' => 12,
                    'promo' => false,
                ],
            ],
        ],
        'empresa' => [
            'code' => 'empresa',
            'name' => 'Plano Empresa',
            'tagline' => 'Para negócios e equipes que querem crescer online.',
            'checkout_enabled' => true,
            'da_package' => 'Plano-Empresa-GeekHost',
            'max_domains' => 3,
            'max_sites' => 3,
            'features' => ['10 contas de e-mail', '15 GB de espaço', 'Banco de dados ilimitado'],
            'specs' => [
                'Disco' => '15 GB',
                'E-mails' => '10 contas',
                'Sites' => 'Até 3 sites',
                'Painel' => 'cPanel',
            ],
            'periods' => [
                'mensal' => [
                    'label' => 'Mensal',
                    'amount' => 29.90,
                    'renew_amount' => 45.90,
                    'cycle' => 'MONTHLY',
                    'interval_months' => 1,
                    'promo' => true,
                ],
                'semestral' => [
                    'label' => 'Semestral',
                    'amount' => 299.00,
                    'renew_amount' => 299.00,
                    'cycle' => 'SEMIANNUALLY',
                    'interval_months' => 6,
                    'promo' => false,
                ],
                'anual' => [
                    'label' => 'Anual',
                    'amount' => 394.00,
                    'renew_amount' => 394.00,
                    'cycle' => 'YEARLY',
                    'interval_months' => 12,
                    'promo' => false,
                ],
            ],
        ],
        'premium' => [
            'code' => 'premium',
            'name' => 'Plano Premium',
            'tagline' => 'Para projetos que precisam de uma estrutura mais completa.',
            'checkout_enabled' => true,
            'da_package' => 'Plano-Premium-GeekHost',
            'max_domains' => 10,
            'max_sites' => 10,
            'features' => ['E-mails ilimitados', '25 GB de espaço', 'Banco de dados ilimitado'],
            'specs' => [
                'Disco' => '25 GB',
                'E-mails' => 'Ilimitados',
                'Sites' => 'Até 10 sites',
                'Painel' => 'cPanel',
            ],
            'periods' => [
                'mensal' => [
                    'label' => 'Mensal',
                    'amount' => 39.90,
                    'renew_amount' => 55.90,
                    'cycle' => 'MONTHLY',
                    'interval_months' => 1,
                    'promo' => true,
                ],
                'semestral' => [
                    'label' => 'Semestral',
                    'amount' => 359.00,
                    'renew_amount' => 359.00,
                    'cycle' => 'SEMIANNUALLY',
                    'interval_months' => 6,
                    'promo' => false,
                ],
                'anual' => [
                    'label' => 'Anual',
                    'amount' => 460.00,
                    'renew_amount' => 460.00,
                    'cycle' => 'YEARLY',
                    'interval_months' => 12,
                    'promo' => false,
                ],
            ],
        ],
        'personalizado' => [
            'code' => 'personalizado',
            'name' => 'Plano Personalizado',
            'tagline' => 'Precisa de mais? Entre em contato!',
            'checkout_enabled' => false,
            'features' => [],
            'specs' => [],
            'periods' => [],
        ],
    ];
}

function geekhost_plan(string $code): ?array
{
    $plans = geekhost_plans();
    return $plans[$code] ?? null;
}

/**
 * Normalize legacy period codes from older links.
 */
function geekhost_normalize_period(string $periodCode): string
{
    $map = [
        'first_month' => 'mensal',
        'monthly' => 'mensal',
        'semiannual' => 'semestral',
        'annual' => 'anual',
        'mensal' => 'mensal',
        'semestral' => 'semestral',
        'anual' => 'anual',
    ];
    return $map[strtolower($periodCode)] ?? strtolower($periodCode);
}

/**
 * @return array{plan: array, period: array, amount: float, renew_amount: float}|null
 */
function geekhost_resolve_checkout_selection(string $planCode, string $periodCode): ?array
{
    $plan = geekhost_plan($planCode);
    if ($plan === null || empty($plan['checkout_enabled'])) {
        return null;
    }
    $periodCode = geekhost_normalize_period($periodCode);
    $period = $plan['periods'][$periodCode] ?? null;
    if ($period === null || !isset($period['amount'])) {
        return null;
    }
    return [
        'plan' => $plan,
        'period' => $period + ['code' => $periodCode],
        'amount' => (float) $period['amount'],
        'renew_amount' => (float) ($period['renew_amount'] ?? $period['amount']),
    ];
}

function geekhost_format_brl(float $amount): string
{
    return 'R$ ' . number_format($amount, 2, ',', '.');
}

function geekhost_period_end_date(string $periodCode, ?int $fromTs = null): string
{
    $fromTs = $fromTs ?? time();
    $periodCode = geekhost_normalize_period($periodCode);
    $months = match ($periodCode) {
        'semestral' => 6,
        'anual' => 12,
        default => 1,
    };
    return gmdate('Y-m-d', strtotime('+' . $months . ' month', $fromTs));
}

/** @return list<string> */
function geekhost_nameservers(): array
{
    return ['ns1.geekhost.com.br', 'ns2.geekhost.com.br'];
}

/** @return array<string, int> */
function geekhost_plan_limit_overrides(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!function_exists('geekhost_db')) {
        return $cache;
    }
    try {
        $pdo = geekhost_db();
        $stmt = $pdo->prepare('SELECT value_json FROM app_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => 'plans.domain_limits']);
        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $code => $limit) {
                if (is_string($code) && is_numeric($limit)) {
                    $cache[$code] = max(1, (int) $limit);
                }
            }
        }
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function geekhost_plan_domain_limit(string $planCode): int
{
    $overrides = geekhost_plan_limit_overrides();
    if (isset($overrides[$planCode])) {
        return (int) $overrides[$planCode];
    }
    $plan = geekhost_plan($planCode);
    return (int) ($plan['max_domains'] ?? 1);
}

function geekhost_plan_site_limit(string $planCode): int
{
    $plan = geekhost_plan($planCode);
    return (int) ($plan['max_sites'] ?? 1);
}

/**
 * DirectAdmin reseller package linked to a GeekHost checkout plan.
 * Env overrides (DIRECTADMIN_PACKAGE_*) win when set; otherwise uses plans.php da_package.
 */
function geekhost_plan_da_package(string $planCode): string
{
    geekhost_load_env();
    $envKey = match (strtolower($planCode)) {
        'light' => 'DIRECTADMIN_PACKAGE_LIGHT',
        'empresa' => 'DIRECTADMIN_PACKAGE_EMPRESA',
        'premium' => 'DIRECTADMIN_PACKAGE_PREMIUM',
        default => 'DIRECTADMIN_PACKAGE_DEFAULT',
    };
    $fromEnv = (string) env($envKey, '');
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    $plan = geekhost_plan($planCode);
    $fromPlan = (string) ($plan['da_package'] ?? '');
    if ($fromPlan !== '') {
        return $fromPlan;
    }
    $fallback = (string) env('DIRECTADMIN_PACKAGE_DEFAULT', '');
    return $fallback !== '' ? $fallback : 'Plano-Light-GeekHost';
}
