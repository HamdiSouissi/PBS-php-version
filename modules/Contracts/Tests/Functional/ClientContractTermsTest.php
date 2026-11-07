<?php

declare(strict_types=1);

namespace Modules\Contracts\Tests\Functional;

use App\Core\Exception\ValidationException;
use App\Core\Pdf\PdfDocument;
use App\Services\Pdf\ClientContractPdf;
use Modules\Contracts\Services\ClientContractService;
use Tests\Core\DatabaseTestCase;

final class ClientContractTermsTest extends DatabaseTestCase
{
    private function data(string $terms): array
    {
        $this->db->execute("INSERT INTO third_parties (type, code, name) VALUES ('client', 'C01', 'Client test')");
        return [
            'third_party_id' => (int) $this->db->lastInsertId(),
            'title' => 'Site web', 'starts_on' => '2026-11-01', 'ends_on' => null,
            'signed_on' => null, 'amount_ht' => '1000.00', 'billing_cycle' => 'one_shot',
            'payment_terms' => $terms, 'services_description' => 'Site vitrine et livraison V0',
        ];
    }

    public function testMigrationPreservesHistoricalContracts(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE client_contracts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)");
        $pdo->exec("INSERT INTO client_contracts (title) VALUES ('Ancien contrat')");
        $pdo->exec((string) file_get_contents(base_path('database/migrations/20261107_000017_client_contract_payment.sql')));
        $row = $pdo->query('SELECT * FROM client_contracts')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Ancien contrat', $row['title']);
        self::assertNull($row['services_description']);
        self::assertNull($row['payment_terms']);
    }

    public function testSchedulesAndServicesPersist(): void
    {
        $data = $this->data('milestones');
        $service = new ClientContractService($this->db);
        $id = $service->create($data);
        $row = $service->getOrFail($id);
        self::assertSame('milestones', $row['payment_terms']);
        self::assertSame('Site vitrine et livraison V0', $row['services_description']);
        $data['payment_terms'] = 'full';
        $row = $service->getOrFail($service->create($data));
        self::assertSame('full', $row['payment_terms']);
    }

    public function testMilestonesCannotUseRecurringCycle(): void
    {
        $data = $this->data('milestones');
        $data['billing_cycle'] = 'monthly';
        $this->expectException(ValidationException::class);
        (new ClientContractService($this->db))->create($data);
    }

    public function testRejectsEmptyServices(): void
    {
        $data = $this->data('full');
        $data['services_description'] = '   ';
        $this->expectException(ValidationException::class);
        (new ClientContractService($this->db))->create($data);
    }

    public function testPdfContainsCorrectClausesAndServices(): void
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $data = $this->data('milestones');
        $service = new ClientContractService($this->db);
        $pdf = new ContractRecordingPdf();
        (new ClientContractPdf($this->db))->render($pdf, $service->create($data));
        self::assertTrue(str_contains($pdf->text, '40 % du prix HT'));
        self::assertTrue(str_contains($pdf->text, '20 % est facturé à la livraison finale'));
        self::assertTrue(str_contains($pdf->text, 'Site vitrine et livraison V0'));
        self::assertTrue(str_contains($pdf->text, 'critères de recette'));
        self::assertTrue(str_starts_with($pdf->output(), '%PDF'));
        $data['payment_terms'] = 'full';
        $pdf = new ContractRecordingPdf();
        (new ClientContractPdf($this->db))->render($pdf, $service->create($data));
        self::assertTrue(str_contains($pdf->text, '100 % du prix HT'));
        self::assertFalse(str_contains($pdf->text, '40 % du prix HT'));
    }

    public function testHistoricalContractKeepsCycleTerms(): void
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $data = $this->data('cycle');
        $id = (new ClientContractService($this->db))->create($data);
        $this->db->execute('UPDATE client_contracts SET payment_terms = NULL, services_description = NULL WHERE id = :id', ['id' => $id]);
        $pdf = new ContractRecordingPdf();
        (new ClientContractPdf($this->db))->render($pdf, $id);
        self::assertTrue(str_contains($pdf->text, '30 jours nets'));
        self::assertFalse(str_contains($pdf->text, '40 % du prix HT'));
    }
}

final class ContractRecordingPdf extends PdfDocument
{
    public string $text = '';

    public function article(string $number, string $title, string $body): void
    {
        $this->text .= $body . "\n";
        parent::article($number, $title, $body);
    }

    public function paragraph(string $text): void
    {
        $this->text .= $text . "\n";
        parent::paragraph($text);
    }
}
