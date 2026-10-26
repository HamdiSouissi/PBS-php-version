<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Connexion PDO principale (PostgreSQL). Un driver SQLite existe
 * uniquement pour la suite de tests en environnement hors-ligne.
 *
 * Toutes les requêtes passent par des requêtes préparées (injection SQL
 * impossible par construction).
 */
final class Connection
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    public function driver(): string
    {
        return (string) ($this->config['default'] ?? 'pgsql');
    }

    /**
     * Ferme la connexion courante et applique une configuration surchargée
     * (utilisé par la suite de tests pour basculer sur la base de test).
     *
     * @param array<string,mixed> $override
     */
    public function reconnect(array $override = []): void
    {
        $this->pdo = null;
        $this->transactionDepth = 0;
        if ($override !== []) {
            $connection = $this->config['connections'][$this->driver()] ?? [];
            $this->config['connections'][$this->driver()] = array_merge($connection, $override);
        }
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $driver = $this->driver();
        $settings = $this->config['connections'][$driver]
            ?? throw new RuntimeException("Driver DB inconnu : {$driver}");

        try {
            if ($driver === 'sqlite') {
                $dsn = 'sqlite:' . ($settings['path'] ?? ':memory:');
                $this->pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                    $settings['host'],
                    $settings['port'],
                    $settings['dbname'],
                    $settings['sslmode'] ?? 'prefer'
                );
                $this->pdo = new PDO($dsn, $settings['user'], $settings['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ]);
                $this->pdo->exec("SET NAMES '{$settings['charset']}'");
                if (!empty($settings['schema'])) {
                    $this->pdo->exec('SET search_path TO ' . $this->pdo->quote($settings['schema']));
                }
            }
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Connexion à la base de données impossible : ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
        return $this->pdo;
    }

    /**
     * Exécute une requête préparée et retourne le statement.
     *
     * @param array<string|int,mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($this->normalizeParams($params));
        return $statement;
    }

    /**
     * Normalise les valeurs PHP pour le driver courant.
     * PDO_PGSQL natif attend 'true'/'false' pour les booléens ; SQLite utilise
     * 1/0. Les DateTime sont convertis en chaînes ISO.
     *
     * @param array<string|int,mixed> $params
     * @return array<string|int,mixed>
     */
    private function normalizeParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $this->driver() === 'sqlite' ? ($value ? 1 : 0) : ($value ? 'true' : 'false');
            } elseif ($value instanceof \DateTimeInterface) {
                $params[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        return $params;
    }

    /**
     * Exécute un script SQL (multi-statements, DDL). Aucun paramètre lié :
     * réservé aux migrations et au DDL maîtrisé.
     */
    public function exec(string $sql): int|false
    {
        return $this->pdo()->exec($sql);
    }

    /**
     * Alias sémantique de run() pour les écritures.
     *
     * @param array<string|int,mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Une ligne, ou null.
     *
     * @param array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Toutes les lignes.
     *
     * @param array<string|int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * Une valeur scalaire.
     *
     * @param array<string|int,mixed> $params
     */
    public function fetchScalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return $this->pdo()->lastInsertId($sequence);
    }

    /**
     * Transaction avec support des points de sauvegarde imbriqués.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth <= 1) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->transactionDepth = 0;
            return;
        }
        $this->transactionDepth--;
        $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionDepth);
    }

    public function nowExpression(): string
    {
        return $this->driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    public function boolean(bool $value): bool|int
    {
        return $this->driver() === 'sqlite' ? ($value ? 1 : 0) : $value;
    }
}
