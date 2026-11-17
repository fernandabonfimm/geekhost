<?php

declare(strict_types=1);

/**
 * Official GeekHost hosting plan catalog — single source of truth for checkout amounts.
 * Amounts are in BRL (reais). Never trust frontend-submitted prices.
 */
function geekhost_plans(): array
{
    return [
        'light' => [
            'code' => 'light',
            'name' => 'Plano Light',
            'tagline' => 'Para colocar seu site e suas ideias no ar.',
            'checkout_enabled' => true,
            'features' => ['5 contas de e-mail', '10 GB de espaço', 'Banco de dados ilimitado'],
            'periods' => [
                'first_month' => [
                    'label' => '1º mês promocional',
                    'amount' => 19.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'monthly' => [
                    'label' => 'Mensal',
                    'amount' => 35.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'semiannual' => [
                    'label' => 'Semestral',
                    'amount' => 239.00,
                    'interval' => 'month',
                    'interval_count' => 6,
                    'subscription_ready' => false,
                ],
                'annual' => [
                    'label' => 'Anual',
                    'amount' => 299.90,
                    'interval' => 'year',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
            ],
        ],
        'empresa' => [
            'code' => 'empresa',
            'name' => 'Plano Empresa',
            'tagline' => 'Para negócios e equipes que querem crescer online.',
            'checkout_enabled' => true,
            'features' => ['10 contas de e-mail', '15 GB de espaço', 'Banco de dados ilimitado'],
            'periods' => [
                'first_month' => [
                    'label' => '1º mês promocional',
                    'amount' => 29.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'monthly' => [
                    'label' => 'Mensal',
                    'amount' => 45.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'semiannual' => [
                    'label' => 'Semestral',
                    'amount' => 299.00,
                    'interval' => 'month',
                    'interval_count' => 6,
                    'subscription_ready' => false,
                ],
                'annual' => [
                    'label' => 'Anual',
                    'amount' => 394.00,
                    'interval' => 'year',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
            ],
        ],
        'premium' => [
            'code' => 'premium',
            'name' => 'Plano Premium',
            'tagline' => 'Para projetos que precisam de uma estrutura mais completa.',
            'checkout_enabled' => true,
            'features' => ['E-mails ilimitados', '25 GB de espaço', 'Banco de dados ilimitado'],
            'periods' => [
                'first_month' => [
                    'label' => '1º mês promocional',
                    'amount' => 39.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'monthly' => [
                    'label' => 'Mensal',
                    'amount' => 55.90,
                    'interval' => 'month',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
                'semiannual' => [
                    'label' => 'Semestral',
                    'amount' => 359.00,
                    'interval' => 'month',
                    'interval_count' => 6,
                    'subscription_ready' => false,
                ],
                'annual' => [
                    'label' => 'Anual',
                    'amount' => 460.00,
                    'interval' => 'year',
                    'interval_count' => 1,
                    'subscription_ready' => true,
                ],
            ],
        ],
        'personalizado' => [
            'code' => 'personalizado',
            'name' => 'Plano Personalizado',
            'tagline' => 'Precisa de mais? Entre em contato!',
            'checkout_enabled' => false,
            'features' => [],
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
 * @return array{plan: array, period: array, amount: float}|null
 */
function geekhost_resolve_checkout_selection(string $planCode, string $periodCode): ?array
{
    $plan = geekhost_plan($planCode);
    if ($plan === null || empty($plan['checkout_enabled'])) {
        return null;
    }
    $period = $plan['periods'][$periodCode] ?? null;
    if ($period === null || !isset($period['amount'])) {
        return null;
    }
    return [
        'plan' => $plan,
        'period' => $period,
        'amount' => (float) $period['amount'],
    ];
}

function geekhost_format_brl(float $amount): string
{
    return 'R$ ' . number_format($amount, 2, ',', '.');
}
