<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Modèle domaine de base : entité anémique hydratée depuis une ligne PDO.
 * Les règles métier vivent dans les Services, pas dans les modèles.
 */
abstract class BaseModel
{
    /**
     * @param array<string,mixed> $row
     */
    abstract public static function fromRow(array $row): static;

    /**
     * @return array<string,mixed>
     */
    abstract public function toArray(): array;

    /**
     * Conversion sûre des booléens PG (PDO peut renvoyer "t"/"f" selon drivers).
     */
    protected static function bool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true', true], true);
    }

    protected static function dateOrNull(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        return new \DateTimeImmutable($value);
    }
}
