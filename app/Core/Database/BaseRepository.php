<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Pagination\Paginator;

/**
 * Société de dépôt (repository) de base : accès PDO préparé, pagination.
 * Aucun ORM : les requêtes SQL restent explicites dans les sous-classes.
 */
abstract class BaseRepository
{
    public function __construct(protected readonly Connection $db)
    {
    }

    abstract protected function table(): string;

    public function find(int|string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->table() . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function findOrFail(int|string $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new \App\Core\Exception\NotFoundException(
                'Enregistrement introuvable (' . $this->table() . ' #' . $id . ').'
            );
        }
        return $row;
    }

    public function countAll(string $where = '1=1', array $params = []): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE ' . $where,
            $params
        );
    }

    /**
     * @param array<string,mixed> $params
     */
    protected function paginate(string $sql, array $params, int $page, int $perPage): Paginator
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $items = $this->db->fetchAll($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
        return new Paginator($items, $this->countAll(), $page, $perPage);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column) => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $this->db->execute($sql, $data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $conditions
     */
    public function update(string $table, array $data, array $conditions): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = :set_{$column}";
        }
        $wheres = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $wheres[] = "{$column} = :where_{$column}";
            $params['where_' . $column] = $value;
        }
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            implode(' AND ', $wheres)
        );
        return $this->db->execute($sql, $params);
    }
}
