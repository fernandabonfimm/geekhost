<?php

declare(strict_types=1);

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/db.php';

/**
 * Future ValueHost / DirectAdmin provisioning hook.
 * Paid orders stay in paid_pending_provisioning — never fake "hosting active".
 */
final class ProvisioningStub
{
    /**
     * @param array<string, mixed> $order
     */
    public static function queue(array $order): void
    {
        $pdo = geekhost_db();
        $now = gmdate('c');
        $stmt = $pdo->prepare(
            'UPDATE orders SET provisioning_status = :ps, updated_at = :u WHERE id = :id AND provisioning_status = :none'
        );
        $stmt->execute([
            ':ps' => 'pending',
            ':u' => $now,
            ':id' => $order['id'],
            ':none' => 'none',
        ]);

        geekhost_log('provisioning_queued', [
            'order_public_id' => $order['public_id'] ?? null,
            'plan_code' => $order['plan_code'] ?? null,
            'note' => 'Stub only — ValueHost/DirectAdmin integration pending',
        ]);
    }
}
