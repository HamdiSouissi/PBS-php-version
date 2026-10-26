<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Application;
use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;
use Database\Seeds\RbacSeeder;

/**
 * Cas de test avec base PostgreSQL réelle (base dédiée pbs_test).
 *
 * Chaque test repart sur un schéma migré et un jeu de référence minimal.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $initialized = false;

    protected Connection $db;

    protected function setUp(): void
    {
        $app = new Application(base_path());
        $app->bootstrap();
        $this->db = Application::container()->get(Connection::class);

        if (!self::$initialized) {
            $this->db->reconnect(['dbname' => getenv('PBS_TEST_DB') ?: 'pbs_test']);
            $this->migrate();
            self::$initialized = true;
        }
        $this->truncateAll();
        (new RbacSeeder(Application::container()))->run();
        (new \Database\Seeds\ReferenceSeeder(Application::container()))->run();
    }

    private function migrate(): void
    {
        $runner = new MigrationRunner(
            $this->db,
            base_path('database/migrations'),
            (string) config('database.migrations_table')
        );
        $runner->run();
    }

    protected function truncateAll(): void
    {
        $tables = $this->db->fetchAll(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'schema_migrations'"
        );
        if ($tables === []) {
            return;
        }
        $names = implode(', ', array_map(static fn (array $row) => '"' . $row['tablename'] . '"', $tables));
        $this->db->exec("TRUNCATE TABLE {$names} RESTART IDENTITY CASCADE");
    }
}
