<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;
use App\Core\Database\Connection;

/**
 * Société par défaut (Prime Vision Solutions), établissement et service.
 */
final class CompanySeeder
{
    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        $db = $this->container->get(Connection::class);

        $companyId = $db->fetchScalar('SELECT id FROM companies WHERE is_default = TRUE LIMIT 1');
        if ($companyId !== false && $companyId !== null) {
            return;
        }

        $db->execute(
            'INSERT INTO companies
                (legal_name, commercial_name, siren, siret, vat_number, legal_form,
                 address, postal_code, city, country, email, phone, is_default,
                 urssaf_number, collective_agreement)
             VALUES
                (:legal, :commercial, :siren, :siret, :vat, :form,
                 :address, :pc, :city, :country, :email, :phone, TRUE,
                 :urssaf, :cc)',
            [
                'legal' => 'Prime Vision Solutions',
                'commercial' => 'Prime Vision Solutions',
                'siren' => '900123456',
                'siret' => '90012345600014',
                'vat' => 'FR12900123456',
                'form' => 'SAS',
                'address' => '12 rue des Lumières',
                'pc' => '75002',
                'city' => 'Paris',
                'country' => 'France',
                'email' => 'contact@prime-vision-solutions.fr',
                'phone' => '+33100000000',
                'urssaf' => '117',
                'cc' => 'SYNTEC (bureaux d\'études techniques)',
            ]
        );
        $companyId = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO establishments (company_id, name, siret, address, postal_code, city, head_office)
             VALUES (:c, :n, :siret, :a, :pc, :city, TRUE)',
            [
                'c' => $companyId,
                'n' => 'Siège social - Paris',
                'siret' => '90012345600014',
                'a' => '12 rue des Lumières',
                'pc' => '75002',
                'city' => 'Paris',
            ]
        );
        $establishmentId = (int) $db->lastInsertId();

        $db->execute(
            'INSERT INTO departments (company_id, code, name) VALUES (:c, :code, :name)',
            ['c' => $companyId, 'code' => 'DIR', 'name' => 'Direction']
        );
        $db->execute(
            'INSERT INTO departments (company_id, code, name) VALUES (:c, :code, :name)',
            ['c' => $companyId, 'code' => 'DEV', 'name' => 'Développement']
        );
        $db->execute(
            'INSERT INTO departments (company_id, code, name) VALUES (:c, :code, :name)',
            ['c' => $companyId, 'code' => 'COM', 'name' => 'Commercial']
        );

        // Compte bancaire principal.
        $db->execute(
            'INSERT INTO bank_accounts (company_id, label, bank_name, iban, bic, initial_balance, current_balance)
             VALUES (:c, :l, :b, :iban, :bic, 0, 0)',
            [
                'c' => $companyId,
                'l' => 'Compte courant principal',
                'b' => 'Banque française',
                'iban' => 'FR7630001007941234567890185',
                'bic' => 'BDFEFRPP',
            ]
        );
    }
}
