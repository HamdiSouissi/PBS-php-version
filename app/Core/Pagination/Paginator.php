<?php

declare(strict_types=1);

namespace App\Core\Pagination;

/**
 * Résultat de pagination transmise aux vues et à l'API.
 */
final class Paginator
{
    /**
     * @param list<array<string,mixed>> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total' => $this->total,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total_pages' => $this->totalPages(),
            ],
        ];
    }
}
