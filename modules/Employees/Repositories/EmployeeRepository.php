<?php

declare(strict_types=1);

namespace Modules\Employees\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Database\Connection;

final class EmployeeRepository extends BaseRepository
{
    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'employees';
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function search(int $companyId, string $term = '', string $status = '', int $limit = 100): array
    {
        $sql = 'SELECT e.*, d.name AS department_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.company_id = :company';
        $params = ['company' => $companyId];
        if ($term !== '') {
            $sql .= ' AND (' . $this->db->like('e.last_name') . ' :term OR ' . $this->db->like('e.first_name') . ' :term OR ' . $this->db->like('e.employee_number') . ' :term)';
            $params['term'] = '%' . $term . '%';
        }
        if ($status !== '') {
            $sql .= ' AND e.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY e.last_name, e.first_name LIMIT ' . $limit;
        return $this->db->fetchAll($sql, $params);
    }

    public function nextEmployeeNumber(int $companyId): string
    {
        $year = date('Y');
        $count = (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM employees WHERE company_id = :c',
            ['c' => $companyId]
        );
        return sprintf('EMP-%s-%04d', $year, $count + 1);
    }

    public function findDetailed(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT e.*, d.name AS department_name, es.name AS establishment_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN establishments es ON es.id = e.establishment_id
             WHERE e.id = :id',
            ['id' => $id]
        );
    }

    public function activeContract(int $employeeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM employment_contracts WHERE employee_id = :e AND status = \'active\'
             ORDER BY start_date DESC LIMIT 1',
            ['e' => $employeeId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function contracts(int $employeeId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM employment_contracts WHERE employee_id = :e ORDER BY start_date DESC',
            ['e' => $employeeId]
        );
    }

    /**
     * Fiche salarié rattachée à un compte utilisateur.
     *
     * Lien direct via employees.user_id, puis repli historique par e-mail
     * pour les fiches créées avant le rattachement obligatoire.
     *
     * @return array<string,mixed>|null
     */
    public function findForUser(int $userId, string $email): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM employees WHERE user_id = :user LIMIT 1',
            ['user' => $userId]
        );
        if ($row !== null) {
            return $row;
        }
        if ($email === '') {
            return null;
        }
        return $this->db->fetchOne(
            'SELECT * FROM employees
             WHERE professional_email = :email OR personal_email = :email
             LIMIT 1',
            ['email' => mb_strtolower(trim($email))]
        );
    }
}
