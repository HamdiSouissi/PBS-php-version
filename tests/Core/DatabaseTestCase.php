<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Application;
use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;
use Database\Seeds\RbacSeeder;

/**
 * Cas de test avec base réelle.
 *
 * Deux modes selon DB_CONNECTION :
 *  - sqlite (défaut de la suite, aucun serveur requis) : schéma chargé depuis
 *    tests/fixtures/schema_sqlite.sql, miroir des migrations MySQL, dans un
 *    fichier temporaire partagé entre les classes de test d'un même processus ;
 *  - mysql : base dédiée (PBS_TEST_DB, défaut pbs_test), migrée via les
 *    migrations SQL versionnées de database/migrations.
 *
 * Chaque test repart sur un schéma vidé et un jeu de référence minimal.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $initialized = false;

    private static ?string $sqliteFile = null;

    protected Connection $db;

    protected function setUp(): void
    {
        $this->forceTestEnvironment();

        $app = new Application(base_path());
        $app->bootstrap();
        $this->db = Application::container()->get(Connection::class);

        if ($this->db->isSqlite()) {
            // Un fichier partagé (et non :memory:) garantit la persistance du
            // schéma malgré la recréation du conteneur à chaque classe de test.
            if (self::$sqliteFile === null) {
                self::$sqliteFile = sys_get_temp_dir() . '/pbs_test_' . uniqid('', true) . '.sqlite';
            }
            $this->db->reconnect(['path' => self::$sqliteFile]);
            if (!self::$initialized) {
                $this->loadSqliteFixture();
                self::$initialized = true;
            }
        } elseif (!self::$initialized) {
            $this->db->reconnect(['dbname' => getenv('PBS_TEST_DB') ?: 'pbs_test']);
            $this->migrate();
            self::$initialized = true;
        }

        $this->truncateAll();
        (new RbacSeeder(Application::container()))->run();
        (new \Database\Seeds\ReferenceSeeder(Application::container()))->run();
    }

    /**
     * Le runner maison ne charge pas forcément tests/bootstrap.php : on
     * garantit ici des variables d'environnement de test par défaut, sans
     * écraser une surcharge explicite (par exemple DB_CONNECTION=mysql).
     */
    private function forceTestEnvironment(): void
    {
        $defaults = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];
        foreach ($defaults as $key => $value) {
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    private function loadSqliteFixture(): void
    {
        $fixture = base_path('tests/fixtures/schema_sqlite.sql');
        $this->db->exec((string) file_get_contents($fixture));
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
        if ($this->db->isSqlite()) {
            $tables = $this->db->fetchAll(
                "SELECT name FROM sqlite_master
                 WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            );
            $this->db->exec('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $this->db->exec('DELETE FROM "' . $table['name'] . '"');
            }
            // sqlite_sequence n'existe qu'après usage d'AUTOINCREMENT.
            $sequence = $this->db->fetchOne(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'"
            );
            if ($sequence !== null) {
                $this->db->exec('DELETE FROM sqlite_sequence');
            }
            $this->db->exec('PRAGMA foreign_keys = ON');
            return;
        }

        $tables = $this->db->fetchAll(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\''
        );
        if ($tables === []) {
            return;
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->db->exec('TRUNCATE TABLE `' . $table['name'] . '`');
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
