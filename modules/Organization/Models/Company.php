<?php

declare(strict_types=1);

namespace Modules\Organization\Models;

use App\Core\Database\BaseModel;

final class Company extends BaseModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $legalName,
        public readonly string $commercialName,
        public readonly string $siren,
        public readonly string $siret,
        public readonly string $vatNumber,
        public readonly string $legalForm,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly bool $isDefault
    ) {
    }

    public static function fromRow(array $row): static
    {
        return new self(
            (int) $row['id'],
            (string) $row['legal_name'],
            (string) ($row['commercial_name'] ?? ''),
            (string) ($row['siren'] ?? ''),
            (string) ($row['siret'] ?? ''),
            (string) ($row['vat_number'] ?? ''),
            (string) ($row['legal_form'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['postal_code'] ?? ''),
            self::bool($row['is_default'] ?? false)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legalName,
            'commercial_name' => $this->commercialName,
            'siren' => $this->siren,
            'siret' => $this->siret,
            'vat_number' => $this->vatNumber,
            'legal_form' => $this->legalForm,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'is_default' => $this->isDefault,
        ];
    }
}
