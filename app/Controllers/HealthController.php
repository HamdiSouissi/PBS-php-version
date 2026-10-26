<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;

/**
 * Sonde de vivacité HTTP (sans authentification) pour les orchestrateurs
 * et healthchecks Docker : vérifie le démarrage de PHP et la connectivité PostgreSQL.
 */
final class HealthController
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'php' => PHP_VERSION,
            'time' => date('c'),
        ];

        try {
            $this->db->fetchScalar('SELECT 1');
            $checks['database'] = 'ok';
            $status = 200;
        } catch (\Throwable $e) {
            $checks['database'] = 'error';
            $checks['error'] = $e->getMessage();
            $status = 503;
        }

        return new JsonResponse(['success' => $status === 200, 'data' => $checks], $status);
    }
}
