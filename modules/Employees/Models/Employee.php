<?php

declare(strict_types=1);

namespace Modules\Employees\Models;

use App\Core\Database\BaseModel;

final class Employee extends BaseModel
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $employeeNumber,
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly ?\DateTimeImmutable $birthDate,
        public readonly string $personalEmail,
        public readonly string $professionalEmail,
        public readonly string $jobTitle,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $hireDate,
        public readonly ?\DateTimeImmutable $terminationDate,
        public readonly bool $isPortalEnabled
    ) {
    }

    public static function fromRow(array $row): static
    {
        return new self(
            (int) $row['id'],
            (int) $row['company_id'],
            (string) $row['employee_number'],
            (string) $row['last_name'],
            (string) $row['first_name'],
            self::dateOrNull($row['birth_date'] ?? null),
            (string) ($row['personal_email'] ?? ''),
            (string) ($row['professional_email'] ?? ''),
            (string) ($row['job_title'] ?? ''),
            (string) ($row['status'] ?? 'candidate'),
            self::dateOrNull($row['hire_date'] ?? null),
            self::dateOrNull($row['termination_date'] ?? null),
            self::bool($row['is_portal_enabled'] ?? false)
        );
    }

    public function fullName(): string
    {
        return mb_strtoupper($this->lastName) . ' ' . $this->firstName;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employeeNumber,
            'last_name' => $this->lastName,
            'first_name' => $this->firstName,
            'full_name' => $this->fullName(),
            'job_title' => $this->jobTitle,
            'status' => $this->status,
            'hire_date' => $this->hireDate?->format('Y-m-d'),
            'is_portal_enabled' => $this->isPortalEnabled,
        ];
    }
}
