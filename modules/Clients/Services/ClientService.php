<?php

declare(strict_types=1);

namespace Modules\Clients\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;

/**
 * Espace clients : les clients sont des tiers (third_parties) de type
 * client ou both. Le service centralise la recherche, la création et
 * les indicateurs commerciaux.
 */
final class ClientService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function search(string $query): array
    {
        $sql = "SELECT t.*,
                    (SELECT COUNT(*) FROM invoices i WHERE i.third_party_id = t.id AND i.type = 'sale') AS invoice_count,
                    (SELECT COALESCE(SUM(i.balance), 0) FROM invoices i
                       WHERE i.third_party_id = t.id AND i.type = 'sale') AS outstanding,
                    (SELECT COALESCE(SUM(i.total_ttc), 0) FROM invoices i
                       WHERE i.third_party_id = t.id AND i.type = 'sale') AS billed_ttc
                FROM third_parties t
                WHERE (t.type = 'client' OR t.type = 'both')";
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (' . $this->db->like('t.name') . ' :q OR ' . $this->db->like('t.code') . ' :q OR ' . $this->db->like('t.city') . ' :q)';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY t.name LIMIT 300';
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $id): array
    {
        $client = $this->db->fetchOne(
            "SELECT * FROM third_parties WHERE id = :id AND (type = 'client' OR type = 'both')",
            ['id' => $id]
        );
        if ($client === null) {
            throw new NotFoundException('Client introuvable.');
        }
        return $client;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $code = $data['code'] ?? $this->nextCode();
        $this->db->execute(
            'INSERT INTO third_parties (type, code, name, legal_form, siren, siret, vat_number, address, postal_code,
                                        city, country, email, phone, payment_terms_days, payment_method, notes)
             VALUES (\'client\', :code, :name, :legal_form, :siren, :siret, :vat, :address, :pc,
                     :city, :country, :email, :phone, :terms, :method, :notes)',
            $this->bindings($code, $data)
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->getOrFail($id);
        $client = $this->db->fetchOne('SELECT code FROM third_parties WHERE id = :id', ['id' => $id]);
        $this->db->execute(
            'UPDATE third_parties SET name = :name, legal_form = :legal_form, siren = :siren, siret = :siret,
                vat_number = :vat, address = :address, postal_code = :pc, city = :city, country = :country,
                email = :email, phone = :phone, payment_terms_days = :terms, payment_method = :method, notes = :notes
             WHERE id = :id',
            $this->bindings((string) $client['code'], $data) + ['id' => $id]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function stats(int $id): array
    {
        return $this->db->fetchOne(
            "SELECT COUNT(*) AS invoice_count,
                    COALESCE(SUM(total_ttc), 0) AS billed_ttc,
                    COALESCE(SUM(balance), 0) AS outstanding,
                    COALESCE(SUM(CASE WHEN status IN ('issued','partial','overdue') THEN 1 ELSE 0 END), 0) AS unpaid_count
             FROM invoices WHERE third_party_id = :id AND type = 'sale'",
            ['id' => $id]
        ) ?: [];
    }

    private function nextCode(): string
    {
        $count = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM third_parties WHERE code LIKE 'CLI-%'"
        );
        return sprintf('CLI-%04d', $count + 1);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function bindings(string $code, array $data): array
    {
        return [
            'code' => $code,
            'name' => $data['name'],
            'legal_form' => $data['legal_form'] ?? '',
            'siren' => $data['siren'] ?? '',
            'siret' => $data['siret'] ?? '',
            'vat' => $data['vat_number'] ?? '',
            'address' => $data['address'] ?? '',
            'pc' => $data['postal_code'] ?? '',
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? 'France',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'terms' => (int) ($data['payment_terms_days'] ?? 30),
            'method' => $data['payment_method'] ?? 'virement',
            'notes' => $data['notes'] ?? '',
        ];
    }
}
