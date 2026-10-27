<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Exécute les migrations SQL versionnées (database/migrations/*.sql).
 * Chaque migration est jouée dans une transaction, avec table de suivi
 * schema_migrations. Attention : MySQL/MariaDB provoquent un commit implicite
 * sur le DDL — en cas d'échec d'une migration, les objets déjà créés peuvent
 * rester présents et doivent être corrigés manuellement avant nouvelle tentative.
 * SQLite, utilisé par la suite de tests, conserve un DDL transactionnel.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $migrationsPath,
        private readonly string $table = 'schema_migrations'
    ) {
    }

    public function ensureTable(): void
    {
        if ($this->db->driver() === 'sqlite') {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    checksum TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
                )"
            );
            return;
        }
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Applique les migrations en attente.
     *
     * @return list<string> migrations jouées
     */
    public function run(): array
    {
        $this->ensureTable();
        $applied = $this->db->fetchAll("SELECT migration FROM {$this->table}");
        $appliedNames = array_map(static fn (array $row) => $row['migration'], $applied);

        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files);
        $executed = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $appliedNames, true)) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            $checksum = hash('sha256', $sql);
            $this->db->transaction(function (Connection $connection) use ($sql, $name, $checksum): void {
                // PDO MySQL (mysqlnd) et SQLite acceptent les scripts multi-statements.
                $connection->pdo()->exec($sql);
                $connection->execute(
                    "INSERT INTO {$this->table} (migration, checksum) VALUES (:m, :c)",
                    ['m' => $name, 'c' => $checksum]
                );
            });
            $executed[] = $name;
        }
        return $executed;
    }

    /**
     * Annule la dernière migration via son fichier .down.sql s'il existe.
     */
    public function rollbackLast(): ?string
    {
        $this->ensureTable();
        $last = $this->db->fetchOne("SELECT migration FROM {$this->table} ORDER BY id DESC LIMIT 1");
        if ($last === null) {
            return null;
        }
        $name = $last['migration'];
        $downFile = $this->migrationsPath . '/' . preg_replace('/\.sql$/', '.down.sql', $name);
        if (!is_file($downFile)) {
            throw new \RuntimeException("Aucun script down pour {$name}");
        }
        $this->db->transaction(function (Connection $connection) use ($downFile, $name): void {
            $connection->pdo()->exec((string) file_get_contents($downFile));
            $connection->execute("DELETE FROM {$this->table} WHERE migration = :m", ['m' => $name]);
        });
        return $name;
    }

    /** @return list<array<string,mixed>> */
    public function status(): array
    {
        $this->ensureTable();
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY id");
    }
}
