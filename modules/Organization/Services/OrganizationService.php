<?php

declare(strict_types=1);

namespace Modules\Organization\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;

/**
 * Gestion de la société, des établissements et des départements.
 */
final class OrganizationService
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function defaultCompany(): ?array
    {
        return $this->db->fetchOne('SELECT * FROM companies ORDER BY is_default DESC, id ASC LIMIT 1');
    }

    public function companyOrFail(int $id): array
    {
        $company = $this->db->fetchOne('SELECT * FROM companies WHERE id = :id', ['id' => $id]);
        if ($company === null) {
            throw new NotFoundException('Société introuvable.');
        }
        return $company;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateCompany(int $id, array $data): void
    {
        $this->companyOrFail($id);
        $fields = [
            'legal_name', 'commercial_name', 'siren', 'siret', 'vat_number', 'legal_form',
            'address', 'postal_code', 'city', 'country', 'phone', 'email', 'iban', 'bic',
            'urssaf_number', 'collective_agreement',
        ];
        $update = array_intersect_key($data, array_flip($fields));
        if ($update === []) {
            return;
        }
        $update['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        $params = [];
        foreach ($update as $column => $value) {
            $sets[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        $params['id'] = $id;
        $this->db->execute('UPDATE companies SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        audit('organization.update', 'Company', $id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function establishments(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM establishments WHERE company_id = :c ORDER BY head_office DESC, id',
            ['c' => $companyId]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addEstablishment(int $companyId, array $data): int
    {
        $id = $this->db->lastInsertId();
        $this->db->execute(
            'INSERT INTO establishments (company_id, name, siret, address, postal_code, city, head_office)
             VALUES (:c, :n, :siret, :a, :pc, :city, :ho)',
            [
                'c' => $companyId,
                'n' => $data['name'],
                'siret' => $data['siret'] ?? '',
                'a' => $data['address'] ?? '',
                'pc' => $data['postal_code'] ?? '',
                'city' => $data['city'] ?? '',
                'ho' => (bool) ($data['head_office'] ?? false),
            ]
        );
        audit('establishment.create', 'Establishment', (int) $this->db->lastInsertId());
        return (int) $this->db->lastInsertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function departments(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM departments WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addDepartment(int $companyId, array $data): void
    {
        $this->db->insertIgnore(
            'departments',
            ['company_id' => $companyId, 'code' => $data['code'], 'name' => $data['name']],
            ['company_id', 'code']
        );
        audit('department.create', 'Department', null, $data);
    }
}
