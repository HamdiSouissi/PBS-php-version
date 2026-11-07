<?php

declare(strict_types=1);

namespace Modules\Contracts\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;

/**
 * Contrats commerciaux clients : création, cycle de vie et numérotation.
 */
final class ClientContractService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, tp.name AS client_name
             FROM client_contracts c
             JOIN third_parties tp ON tp.id = c.third_party_id
             ORDER BY c.starts_on DESC, c.id DESC LIMIT 300'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $id): array
    {
        $contract = $this->db->fetchOne(
            'SELECT c.*, tp.name AS client_name, tp.address AS client_address, tp.postal_code AS client_pc, tp.city AS client_city
             FROM client_contracts c
             JOIN third_parties tp ON tp.id = c.third_party_id
             WHERE c.id = :id',
            ['id' => $id]
        );
        if ($contract === null) {
            throw new NotFoundException('Contrat client introuvable.');
        }
        return $contract;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data, ?int $userId = null): int
    {
        $terms = $data['payment_terms'] ?? 'cycle';
        if (!in_array($terms, ['cycle', 'full', 'milestones'], true)) {
            throw new ValidationException(['payment_terms' => 'Modalité de paiement invalide.']);
        }
        if ($terms !== 'cycle' && ($data['billing_cycle'] ?? '') !== 'one_shot') {
            throw new ValidationException(['payment_terms' => 'Les paiements total ou par étapes nécessitent un cycle ponctuel.']);
        }
        $description = trim((string) ($data['services_description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 5000) {
            throw new ValidationException(['services_description' => 'Décrivez les prestations en 1 à 5 000 caractères.']);
        }
        $number = $this->nextNumber();
        $this->db->execute(
            'INSERT INTO client_contracts
                (third_party_id, contract_number, title, starts_on, ends_on, renewal_mode, notice_days,
                 amount_ht, billing_cycle, status, signed_on, notes, created_by, services_description, payment_terms)
             VALUES
                (:party, :number, :title, :starts, :ends, :renewal, :notice,
                 :amount, :cycle, :status, :signed, :notes, :user, :services, :terms)',
            [
                'services' => $description,
                'terms' => $terms,
                'party' => (int) $data['third_party_id'],
                'number' => $number,
                'title' => $data['title'],
                'starts' => $data['starts_on'],
                'ends' => $data['ends_on'] ?: null,
                'renewal' => $data['renewal_mode'] ?? 'tacit',
                'notice' => (int) ($data['notice_days'] ?? 30),
                'amount' => (float) ($data['amount_ht'] ?? 0),
                'cycle' => $data['billing_cycle'] ?? 'monthly',
                'status' => $data['status'] ?? 'active',
                'signed' => $data['signed_on'] ?: null,
                'notes' => $data['notes'] ?? '',
                'user' => $userId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    private function nextNumber(): string
    {
        $year = date('Y');
        $count = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM client_contracts WHERE contract_number LIKE :prefix",
            ['prefix' => "CTR-{$year}-%"]
        );
        return sprintf('CTR-%s-%03d', $year, $count + 1);
    }
}
