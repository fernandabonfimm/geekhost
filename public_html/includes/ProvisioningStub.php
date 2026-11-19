<?php

declare(strict_types=1);

require_once __DIR__ . '/ProvisioningService.php';

/**
 * Backward-compatible entry point used by OrderService.
 * Delegates to ProvisioningService (DirectAdmin) — never fakes "hosting active".
 */
final class ProvisioningStub
{
    /**
     * @param array<string, mixed> $order
     */
    public static function queue(array $order): void
    {
        (new ProvisioningService())->queueFromOrder($order);
    }
}
