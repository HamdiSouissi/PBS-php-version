<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Mini-constructeur de requêtes SQL (facultatif, réservé aux cas simples).
 * Toutes les valeurs restent des paramètres préparés.
 */
final class QueryBuilder
{
    /** @var list<string> */
    private array $select = ['*'];
    private string $table = '';
    /** @var list<string> */
    private array $joins = [];
    /** @var list<string> */
    private array $where = [];
    /** @var array<string,mixed> */
    private array $params = [];
    /** @var list<string> */
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    /** @var list<string> */
    private array $groupBy = [];

    private function __construct()
    {
    }

    public static function table(string $table): self
    {
        $builder = new self();
        $builder->table = $table;
        return $builder;
    }

    /** @param list<string> $columns */
    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    public function join(string $join): self
    {
        $this->joins[] = $join;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $key = str_replace('.', '_', $column) . '_' . count($this->params);
        $this->where[] = "{$column} {$operator} :{$key}";
        $this->params[$key] = $value;
        return $this;
    }

    public function whereRaw(string $clause): self
    {
        $this->where[] = $clause;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public function groupBy(string $column): self
    {
        $this->groupBy[] = $column;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;
        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    /** @return array<string,mixed> */
    public function params(): array
    {
        return $this->params;
    }

    /** @return list<array<string,mixed>> */
    public function get(Connection $connection): array
    {
        return $connection->fetchAll($this->toSql(), $this->params);
    }

    /** @return array<string,mixed>|null */
    public function first(Connection $connection): ?array
    {
        $this->limit(1);
        return $connection->fetchOne($this->toSql(), $this->params);
    }
}
