<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Auth\AuthManager;
use App\Core\Database\Connection;
use App\Core\Logger\Logger;

/**
 * Journal d'audit : toute action sensible est tracée (qui, quoi, quand, d'où).
 */
final class AuditLogger
{
    public function __construct(
        private readonly Connection $db,
        private readonly AuthManager $auth,

    ) {
    }

    /**
     * @param array<string,mixed> $changes
     */
    public function log(string $action, string $entityType, int|string|null $entityId = null, array $changes = []): void
    {
        try {
            $this->db->execute(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes, ip_address, created_at)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :changes, :ip, ' . $this->db->nowExpression() . ')',
                [
                    'user_id' => $this->auth->id(),
                    'action' => substr($action, 0, 100),
                    'entity_type' => substr($entityType, 0, 80),
                    'entity_id' => $entityId === null ? null : (string) $entityId,
                    'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'cli'),
                ]
            );
        } catch (\Throwable $exception) {
            // L'audit ne doit jamais casser le flux métier, mais est journalisé.
            \App\Core\Application::container()->get(Logger::class)->warning(
                'Échec écriture journal audit : ' . $exception->getMessage()
            );
        }
    }
}
