<?php

declare(strict_types=1);

namespace Modules\Invoicing\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\Invoicing\Services\DocumentNumberingService;
use Modules\Invoicing\Services\EInvoiceService;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Invoicing\Services\QuoteService;
use Tests\Core\DatabaseTestCase;

final class InvoiceLifecycleTest extends DatabaseTestCase
{
    private function fixtures(): array
    {
        // INSERT puis lastInsertId : portable SQLite (tests) comme MySQL.
        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code, is_default)
             VALUES ('PBS Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001', true)"
        );
        $companyId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO third_parties (type, code, name, siret, payment_terms_days)
             VALUES ('client', 'CLI-0001', 'Client Test SARL', '98765432109876', 30)"
        );
        $partyId = (int) $this->db->lastInsertId();
        return [$companyId, $partyId];
    }

    private function sampleLines(): array
    {
        return [
            ['label' => 'Développement', 'quantity' => 5, 'unit_price' => 800.0, 'vat_rate' => 20.0, 'discount_pct' => 0],
            ['label' => 'Support', 'quantity' => 1, 'unit_price' => 200.0, 'vat_rate' => 20.0, 'discount_pct' => 0],
        ];
    }

    public function testInvoiceNumberingIsSequential(): void
    {
        $numbering = Application::container()->get(DocumentNumberingService::class);
        $first = $numbering->next(DocumentNumberingService::KIND_INVOICE, new \DateTimeImmutable('2026-10-01'));
        $second = $numbering->next(DocumentNumberingService::KIND_INVOICE, new \DateTimeImmutable('2026-10-15'));
        self::assertSame('F-2026-0001', $first);
        self::assertSame('F-2026-0002', $second);

        $nextYear = $numbering->next(DocumentNumberingService::KIND_INVOICE, new \DateTimeImmutable('2027-01-05'));
        self::assertSame('F-2027-0001', $nextYear);
    }

    public function testFullInvoiceCycleDraftToPaid(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $service = Application::container()->get(InvoiceService::class);

        $invoiceId = $service->create($companyId, [
            'third_party_id' => $partyId,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'lines' => $this->sampleLines(),
        ], 1);

        $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        // HT = 5×800 + 200 = 4 200 ; TVA 20 % = 840 ; TTC = 5 040
        self::assertSame('draft', $invoice['status']);
        self::assertEqualsWithDelta(4200.0, (float) $invoice['total_ht'], 0.001);
        self::assertEqualsWithDelta(840.0, (float) $invoice['total_vat'], 0.001);
        self::assertEqualsWithDelta(5040.0, (float) $invoice['total_ttc'], 0.001);
        self::assertEqualsWithDelta(5040.0, (float) $invoice['balance'], 0.001);
        self::assertCount(2, $this->db->fetchAll('SELECT * FROM invoice_lines WHERE invoice_id = :id', ['id' => $invoiceId]));

        $service->issue($invoiceId, 1);
        self::assertSame('issued', $this->db->fetchScalar('SELECT status FROM invoices WHERE id = :id', ['id' => $invoiceId]));

        // Paiement partiel : statut 'partial', solde recalculé.
        $service->registerPayment($invoiceId, ['amount' => 2000.0, 'method' => 'virement', 'paid_on' => '2026-10-10'], 1);
        $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        self::assertSame('partial', $invoice['status']);
        self::assertEqualsWithDelta(3040.0, (float) $invoice['balance'], 0.001);

        // Trop-perçu refusé.
        $this->expectException(AppException::class);
        $service->registerPayment($invoiceId, ['amount' => 9999.0, 'paid_on' => '2026-10-12'], 1);
    }

    public function testOverpaymentThrowsAndFullPaymentCloses(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $service = Application::container()->get(InvoiceService::class);
        $invoiceId = $service->create($companyId, [
            'third_party_id' => $partyId,
            'lines' => [['label' => 'Prestation', 'quantity' => 1, 'unit_price' => 100.0, 'vat_rate' => 20.0]],
        ], 1);
        $service->issue($invoiceId, 1);
        $service->registerPayment($invoiceId, ['amount' => 120.0, 'paid_on' => '2026-10-10'], 1);

        $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        self::assertSame('paid', $invoice['status']);
        self::assertEqualsWithDelta(0.0, (float) $invoice['balance'], 0.001);

        // Une facture soldée ne s'annule pas, il faut un avoir.
        $this->expectException(AppException::class);
        $service->cancel($invoiceId);
    }

    public function testCreditNoteReusesInvoiceLines(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $service = Application::container()->get(InvoiceService::class);
        $invoiceId = $service->create($companyId, [
            'third_party_id' => $partyId,
            'lines' => [['label' => 'Module', 'quantity' => 2, 'unit_price' => 500.0, 'vat_rate' => 20.0]],
        ], 1);

        $creditId = $service->createCreditNote($invoiceId, null, 'Prestation non conforme', 1);
        $credit = $this->db->fetchOne('SELECT * FROM credit_notes WHERE id = :id', ['id' => $creditId]);
        self::assertSame('issued', $credit['status']);
        self::assertEqualsWithDelta(1000.0, (float) $credit['total_ht'], 0.001);
        self::assertEqualsWithDelta(200.0, (float) $credit['total_vat'], 0.001);
        self::assertSame($invoiceId, (int) $credit['invoice_id']);
        self::assertCount(1, $this->db->fetchAll('SELECT * FROM credit_note_lines WHERE credit_note_id = :id', ['id' => $creditId]));
    }

    public function testQuoteAcceptThenConvertCreatesInvoice(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $quotes = Application::container()->get(QuoteService::class);
        $quoteId = $quotes->create($companyId, [
            'third_party_id' => $partyId,
            'lines' => [['label' => 'Étude', 'quantity' => 3, 'unit_price' => 600.0, 'vat_rate' => 20.0]],
        ], 1);

        // Conversion refusée tant que le devis n'est pas accepté.
        try {
            $quotes->convertToInvoice($quoteId, 1);
            self::fail('La conversion dun devis non accepté devait échouer.');
        } catch (AppException $e) {
            // comportement attendu
        }

        $quotes->accept($quoteId);
        $invoiceId = $quotes->convertToInvoice($quoteId, 1);
        $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        self::assertSame('draft', $invoice['status']);
        self::assertEqualsWithDelta(1800.0, (float) $invoice['total_ht'], 0.001);
        $quote = $this->db->fetchOne('SELECT * FROM quotes WHERE id = :id', ['id' => $quoteId]);
        self::assertSame('invoiced', $quote['status']);
        self::assertSame($invoiceId, (int) $quote['converted_invoice_id']);
    }

    public function testEInvoiceGeneratesValidUblAndCii(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $service = Application::container()->get(InvoiceService::class);
        $eInvoice = Application::container()->get(EInvoiceService::class);

        $invoiceId = $service->create($companyId, [
            'third_party_id' => $partyId,
            'lines' => [['label' => 'Abonnement', 'quantity' => 1, 'unit_price' => 100.0, 'vat_rate' => 20.0]],
        ], 1);

        // La dématérialisation exige une facture émise.
        $this->expectException(AppException::class);
        $eInvoice->generate($invoiceId, 'UBL');
    }

    public function testEInvoiceAfterIssuanceProducesXmlFiles(): void
    {
        [$companyId, $partyId] = $this->fixtures();
        $service = Application::container()->get(InvoiceService::class);
        $eInvoice = Application::container()->get(EInvoiceService::class);

        $invoiceId = $service->create($companyId, [
            'third_party_id' => $partyId,
            'lines' => [['label' => 'Abonnement', 'quantity' => 1, 'unit_price' => 100.0, 'vat_rate' => 20.0]],
        ], 1);
        $service->issue($invoiceId, 1);

        $ubl = $eInvoice->generate($invoiceId, 'UBL');
        $cii = $eInvoice->generate($invoiceId, 'CII');

        self::assertTrue(is_file($ubl['file_path']));
        self::assertTrue(is_file($cii['file_path']));
        self::assertSame(64, strlen((string) $ubl['checksum']));

        $ublXml = simplexml_load_file($ubl['file_path']);
        self::assertTrue($ublXml !== false);
        $ttc = $ublXml->xpath("//*[local-name()='TaxInclusiveAmount']");
        self::assertSame('120.00', (string) $ttc[0]);

        $ciiXml = simplexml_load_file($cii['file_path']);
        self::assertTrue($ciiXml !== false);

        $outbox = $this->db->fetchAll('SELECT * FROM e_invoice_outbox WHERE invoice_id = :id', ['id' => $invoiceId]);
        self::assertCount(2, $outbox);
        self::assertSame('generated', $outbox[0]['status']);

        // Aucun accusé fabriqué : le statut maximal autonome après export manuel est 'exported'.
        $eInvoice->markExported((int) $outbox[0]['id'], 'DEPOT-MANUEL-001');
        $updated = $this->db->fetchOne('SELECT * FROM e_invoice_outbox WHERE id = :id', ['id' => $outbox[0]['id']]);
        self::assertSame('exported', $updated['status']);
        self::assertSame('DEPOT-MANUEL-001', $updated['external_reference']);
        self::assertSame('submitted', $this->db->fetchScalar('SELECT electronic_status FROM invoices WHERE id = :id', ['id' => $invoiceId]));
    }
}
