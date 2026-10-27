<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Connexion PDO principale (MySQL 8 / MariaDB). Un driver SQLite existe
 * uniquement pour la suite de tests en environnement hors-ligne.
 *
 * Toutes les requêtes passent par des requêtes préparées (injection SQL
 * impossible par construction). Les quelques différences syntaxiques entre
 * MySQL et SQLite sont isolées dans les méthodes d'expression de cette classe
 * (nowExpression, groupConcat, yearMonth, upsert...) afin qu'aucun code métier
 * ne dépende du dialecte.
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
        return (string) ($this->config['default'] ?? 'mysql');
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
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
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $settings['host'],
                    $settings['port'],
                    $settings['dbname'],
                    $settings['charset'] ?? 'utf8mb4'
                );
                $this->pdo = new PDO($dsn, $settings['user'], $settings['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ]);
                $this->pdo->exec("SET NAMES " . $this->pdo->quote($settings['charset'] ?? 'utf8mb4'));
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
     * MySQL (TINYINT(1)) comme SQLite attendent 1/0 pour les booléens ;
     * les DateTime sont convertis en chaînes ISO.
     *
     * @param array<string|int,mixed> $params
     * @return array<string|int,mixed>
     */
    private function normalizeParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 1 : 0;
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
     * Note MySQL : un DDL provoque un commit implicite, cette mécanique
     * transactionnelle ne protège donc que le DML (cf. MigrationRunner).
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

    /**
     * MODIFIÉ : si le compteur indique une transaction ouverte alors que PDO
     * n'en a plus (commit implicite d'un DDL MySQL), on repart de zéro au lieu
     * de poser un SAVEPOINT sur une transaction qui n'existe plus.
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth > 0 && !$this->pdo()->inTransaction()) {
            $this->transactionDepth = 0;
        }

        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    /**
     * MODIFIÉ : ne plante plus si un DDL (CREATE/ALTER/DROP TABLE) a déjà
     * validé implicitement la transaction côté MySQL.
     */
    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        // Commit implicite déjà effectué par MySQL (DDL) : plus rien à valider.
        if (!$this->pdo()->inTransaction()) {
            $this->transactionDepth = 0;
            return;
        }

        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    /**
     * MODIFIÉ : ne lève jamais « There is no active transaction », afin de ne
     * pas masquer l'exception d'origine (erreur SQL de la migration).
     */
    public function rollBack(): void
    {
        // Niveau racine, ou transaction déjà fermée par un commit implicite.
        if ($this->transactionDepth <= 1 || !$this->pdo()->inTransaction()) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->transactionDepth = 0;
            return;
        }
        $this->transactionDepth--;
        $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionDepth);
    }

    /**
     * Expression date/heure courante du dialecte.
     */
    public function nowExpression(): string
    {
        return $this->isSqlite() ? "datetime('now')" : 'NOW()';
    }

    /**
     * Expression booléenne portable (1/0 en MySQL TINYINT comme en SQLite).
     */
    public function boolean(bool $value): int
    {
        return $value ? 1 : 0;
    }

    /**
     * Concaténation agrégée (GROUP_CONCAT MySQL, aussi disponible en SQLite).
     * L'expression peut contenir DISTINCT, ex : groupConcat('DISTINCT r.code').
     */
    public function groupConcat(string $expression, string $separator = ','): string
    {
        $separator = str_replace("'", "''", $separator);
        if ($this->isSqlite()) {
            return "GROUP_CONCAT({$expression}, '{$separator}')";
        }
        return "GROUP_CONCAT({$expression} SEPARATOR '{$separator}')";
    }

    /**
     * Mois-année d'une colonne date au format 'YYYY-MM'.
     */
    public function yearMonth(string $column): string
    {
        return $this->isSqlite()
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Date/heure courante augmentée d'un intervalle (ex : verrou 15 minutes).
     * Unités supportées : minute, hour, day, month, year.
     */
    public function nowPlus(int $value, string $unit): string
    {
        if ($this->isSqlite()) {
            return "datetime('now', '+{$value} {$unit}s')";
        }
        return "DATE_ADD(NOW(), INTERVAL {$value} " . strtoupper($unit) . ')';
    }

    /**
     * Soustraction d'un intervalle à une colonne/expression date.
     * Unités supportées : day, month, year.
     */
    public function dateSub(string $expression, int $value, string $unit): string
    {
        if ($this->isSqlite()) {
            $modifier = str_contains($expression, 'CURRENT_DATE') || $expression === 'CURRENT_DATE'
                ? "date('now', '-{$value} {$unit}s')"
                : "date({$expression}, '-{$value} {$unit}s')";
            return $modifier;
        }
        return "DATE_SUB({$expression}, INTERVAL {$value} " . strtoupper($unit) . ')';
    }

    /**
     * Prédicat de recherche insensible à la casse, portable MySQL/SQLite.
     * Le paramètre lié doit contenir les jokers (ex : '%terme%').
     */
    public function like(string $column): string
    {
        return "LOWER({$column}) LIKE";
    }

    /**
     * Verrou de ligne pessimiste. SQLite (tests, mono-processus) n'en a pas :
     * la clause est omise ; MySQL/MariaDB reçoivent bien FOR UPDATE.
     */
    public function rowLockClause(): string
    {
        return $this->isSqlite() ? '' : ' FOR UPDATE';
    }

    /**
     * Verrou de ligne avec saut des lignes déjà verrouillées (planification
     * concurrente). Inopérant et omis sous SQLite.
     */
    public function skipLockedClause(): string
    {
        return $this->isSqlite() ? '' : ' FOR UPDATE SKIP LOCKED';
    }

    /**
     * Insertion idempotente (upsert) sans mise à jour en cas de conflit.
     *
     * @param array<string,mixed> $data
     * @param list<string>        $conflictColumns colonnes de la clé unique (SQLite)
     */
    public function insertIgnore(string $table, array $data, array $conflictColumns = []): int
    {
        return $this->upsert($table, $data, $conflictColumns, []);
    }

    /**
     * Référence la valeur entrante d'une colonne lors d'un conflit d'upsert
     * (excluded.col en SQLite, VALUES(col) en MySQL).
     */
    public function conflictValue(string $column): string
    {
        return $this->isSqlite() ? "excluded.{$column}" : "VALUES({$column})";
    }

    /**
     * Insertion idempotente (upsert) dialectale.
     *
     * MySQL  : INSERT IGNORE / INSERT ... ON DUPLICATE KEY UPDATE
     * SQLite : INSERT ... ON CONFLICT (colonnes) DO NOTHING / DO UPDATE
     *
     * @param array<string,mixed>  $data             colonne => valeur paramétrée
     * @param list<string>         $conflictColumns  colonnes de la contrainte unique
     * @param array<string,string> $updateColumns    colonnes à recopier en conflit
     *                                                (vide = ignorer la ligne)
     * @param array<string,string> $rawExpressions   expressions SET brutes (colonne => SQL),
     *                                                utilisables avec conflictValue()
     */
    public function upsert(
        string $table,
        array $data,
        array $conflictColumns,
        array $updateColumns = [],
        array $rawExpressions = []
    ): int {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $sets = [];
        foreach ($updateColumns as $column) {
            $sets[] = $this->isSqlite()
                ? "{$column} = excluded.{$column}"
                : "{$column} = VALUES({$column})";
        }
        foreach ($rawExpressions as $column => $expression) {
            $sets[] = "{$column} = {$expression}";
        }

        if ($sets === []) {
            if ($this->isSqlite()) {
                $target = $conflictColumns === [] ? '' : ' (' . implode(', ', $conflictColumns) . ')';
                $sql .= ' ON CONFLICT' . $target . ' DO NOTHING';
            } else {
                $sql = 'INSERT IGNORE INTO ' . substr($sql, 12);
            }
        } elseif ($this->isSqlite()) {
            $sql .= ' ON CONFLICT (' . implode(', ', $conflictColumns) . ') DO UPDATE SET ' . implode(', ', $sets);
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
        }

        return $this->execute($sql, $data);
    }
}