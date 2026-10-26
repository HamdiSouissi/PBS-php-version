<?php

declare(strict_types=1);

namespace Modules\Invoicing\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Database\Connection;

final class InvoiceRepository extends BaseRepository
{
    protected function table(): string
    {
        return 'invoices';
    }

    /**
     * Liste paginée des factures avec le nom du tiers.
     *
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginateInvoices(int $page, int $perPage, string $type = 'sale', string $status = ''): array
    {
        $where = 'i.type = :type';
        $params = ['type' => $type];
        if ($status !== '') {
            $where .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        $total = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM invoices i WHERE {$where}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT i.*, tp.name AS third_party_name
             FROM invoices i
             JOIN third_parties tp ON tp.id = i.third_party_id
             WHERE {$where}
             ORDER BY i.issue_date DESC, i.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function findDetailed(int $id): ?array
    {
        $invoice = $this->db->fetchOne(
            'SELECT i.*, tp.name AS third_party_name, tp.address, tp.postal_code, tp.city,
                    tp.vat_number, tp.siret, tp.payment_terms_days, c.legal_name AS company_name,
                    c.siret AS company_siret, c.vat_number AS company_vat_number, c.address AS company_address,
                    c.postal_code AS company_postal_code, c.city AS company_city
             FROM invoices i
             JOIN third_parties tp ON tp.id = i.third_party_id
             JOIN companies c ON c.id = i.company_id
             WHERE i.id = :id',
            ['id' => $id]
        );
        if ($invoice === null) {
            return null;
        }
        $invoice['lines'] = $this->db->fetchAll(
            'SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY sort_order, id',
            ['id' => $id]
        );
        $invoice['payments'] = $this->db->fetchAll(
            'SELECT * FROM payments WHERE invoice_id = :id ORDER BY paid_on DESC, id DESC',
            ['id' => $id]
        );
        return $invoice;
    }

    /**
     * Factures en retard (statut échu), pour le planificateur et les relances.
     *
     * @return list<array<string,mixed>>
     */
    public function findOverdue(): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, tp.name AS third_party_name, tp.email AS third_party_email
             FROM invoices i
             JOIN third_parties tp ON tp.id = i.third_party_id
             WHERE i.type = 'sale' AND i.balance > 0 AND i.due_date < CURRENT_DATE
               AND i.status IN ('issued','partial','overdue')
             ORDER BY i.due_date"
        );
    }

    public function markOverdue(): int
    {
        return $this->db->execute(
            "UPDATE invoices SET status = 'overdue', updated_at = now()
             WHERE type = 'sale' AND balance > 0 AND due_date < CURRENT_DATE
               AND status IN ('issued','partial')"
        );
    }
}
