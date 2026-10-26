<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;

/**
 * Facturation électronique — génération locale de fichiers conformes aux
 * normes UBL 2.1 (EN 16931) et CII (UN/CEFACT Cross Industry Invoice).
 *
 * Limite assumée et déclarée : PBS produit des fichiers XML structurés et
 * horodatés (boîte e_invoice_outbox), mais n'implémente PAS la
 * télétransmission vers le Portail Public de Facturation (PPF), une PDP
 * ou Chorus Pro : ces canaux exigent un contrat, des certificats et une
 * API partenaire. Aucun accusvé de réception n'est donc fabriqué :
 * le statut maximal en autonomie est 'exported' (fichier remis à un
 * opérateur ou déposé manuellement), la preuve de dépôt reste externe.
 */
final class EInvoiceService
{
    public const FORMAT_UBL = 'UBL';
    public const FORMAT_CII = 'CII';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Génère le fichier XML et l'enregistre dans la boîte d'envoi.
     */
    public function generate(int $invoiceId, string $format = self::FORMAT_UBL): array
    {
        if (!in_array($format, [self::FORMAT_UBL, self::FORMAT_CII], true)) {
            throw new AppException('Format e-facturation inconnu (UBL ou CII attendu).');
        }
        $invoice = $this->loadInvoice($invoiceId);
        if (in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            throw new AppException("Une facture {$invoice['status']} ne peut pas être dématérialisée.");
        }

        // Idempotence : un message par couple facture/format (génération auto à l'émission + bouton manuel).
        $existing = $this->db->fetchOne(
            'SELECT id, file_path, checksum_sha256 AS checksum FROM e_invoice_outbox
             WHERE invoice_id = :id AND format = :format ORDER BY id DESC LIMIT 1',
            ['id' => $invoiceId, 'format' => $format]
        );
        if ($existing !== null && is_file((string) $existing['file_path'])) {
            return ['id' => (int) $existing['id'], 'file_path' => (string) $existing['file_path'], 'checksum' => (string) $existing['checksum']];
        }

        $xml = $format === self::FORMAT_UBL
            ? $this->buildUbl($invoice)
            : $this->buildCii($invoice);

        $directory = storage_path('einvoicing/' . date('Y', strtotime((string) $invoice['issue_date'])));
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new AppException("Impossible de créer le dossier de stockage e-facturation : {$directory}");
        }
        $fileName = $invoice['invoice_number'] . '-' . $format . '.xml';
        $filePath = $directory . '/' . $fileName;
        file_put_contents($filePath, $xml);
        $checksum = hash_file('sha256', $filePath);

        $this->db->execute(
            'INSERT INTO e_invoice_outbox (invoice_id, format, platform, status, file_path, checksum_sha256)
             VALUES (:invoice, :format, :platform, :status, :path, :checksum)',
            [
                'invoice' => $invoiceId,
                'format' => $format,
                'platform' => 'PPF',
                'status' => 'generated',
                'path' => $filePath,
                'checksum' => (string) $checksum,
            ]
        );
        $outboxId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "UPDATE invoices SET electronic_status = 'generated', updated_at = now() WHERE id = :id",
            ['id' => $invoiceId]
        );
        audit('einvoice.generate', 'EInvoiceOutbox', $outboxId, ['invoice' => $invoice['invoice_number'], 'format' => $format]);

        return ['id' => $outboxId, 'file_path' => $filePath, 'checksum' => $checksum];
    }

    /**
     * Marque le fichier comme exporté/remis à un opérateur (action manuelle tracée).
     */
    public function markExported(int $outboxId, string $externalReference = ''): void
    {
        $row = $this->db->fetchOne('SELECT * FROM e_invoice_outbox WHERE id = :id', ['id' => $outboxId]);
        if ($row === null) {
            throw new NotFoundException('Message e-facturation introuvable.');
        }
        $this->db->execute(
            "UPDATE e_invoice_outbox
                SET status = 'exported', external_reference = :ref, updated_at = now()
              WHERE id = :id",
            ['ref' => $externalReference, 'id' => $outboxId]
        );
        $this->db->execute(
            "UPDATE invoices SET electronic_status = 'submitted', updated_at = now()
             WHERE id = (SELECT invoice_id FROM e_invoice_outbox WHERE id = :id)",
            ['id' => $outboxId]
        );
        audit('einvoice.export', 'EInvoiceOutbox', $outboxId, ['ref' => $externalReference]);
    }

    /**
     * Rejette un message (retour opérateur saisi manuellement) — jamais auto-fabriqué.
     */
    public function markRejected(int $outboxId, string $errorMessage): void
    {
        $this->db->execute(
            "UPDATE e_invoice_outbox SET status = 'rejected', error_message = :err, updated_at = now()
             WHERE id = :id",
            ['err' => $errorMessage, 'id' => $outboxId]
        );
        $this->db->execute(
            "UPDATE invoices SET electronic_status = 'rejected', updated_at = now()
             WHERE id = (SELECT invoice_id FROM e_invoice_outbox WHERE id = :id)",
            ['id' => $outboxId]
        );
        audit('einvoice.reject', 'EInvoiceOutbox', $outboxId);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadInvoice(int $invoiceId): array
    {
        $invoice = $this->db->fetchOne(
            'SELECT i.*, tp.name AS customer_name, tp.address AS customer_address, tp.postal_code AS customer_pc,
                    tp.city AS customer_city, tp.country AS customer_country, tp.vat_number AS customer_vat,
                    tp.siret AS customer_siret,
                    c.legal_name AS supplier_name, c.address AS supplier_address, c.postal_code AS supplier_pc,
                    c.city AS supplier_city, c.siret AS supplier_siret, c.vat_number AS supplier_vat,
                    c.vat_number AS supplier_vat_intra
             FROM invoices i
             JOIN third_parties tp ON tp.id = i.third_party_id
             JOIN companies c ON c.id = i.company_id
             WHERE i.id = :id',
            ['id' => $invoiceId]
        );
        if ($invoice === null) {
            throw new NotFoundException('Facture introuvable.');
        }
        $invoice['lines'] = $this->db->fetchAll(
            'SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY sort_order, id',
            ['id' => $invoiceId]
        );
        return $invoice;
    }

    /**
     * Facture UBL 2.1 (profil EN 16931).
     *
     * @param array<string,mixed> $invoice
     */
    private function buildUbl(array $invoice): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'ubl:Invoice');
        $dom->appendChild($root);
        $cbc = static fn (string $name, string $value): \DOMElement => $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'cbc:' . $name,
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        );

        $root->appendChild($cbc('ID', (string) $invoice['invoice_number']));
        $root->appendChild($cbc('IssueDate', (string) $invoice['issue_date']));
        $root->appendChild($cbc('DueDate', (string) $invoice['due_date']));
        $root->appendChild($cbc('DocumentCurrencyCode', (string) $invoice['currency']));

        $supplier = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:AccountingSupplierParty'
        );
        $supplier->appendChild($this->ublParty($dom, (string) $invoice['supplier_name'], (string) $invoice['supplier_siret'], (string) $invoice['supplier_vat_intra'], $invoice));
        $root->appendChild($supplier);

        $customer = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:AccountingCustomerParty'
        );
        $customer->appendChild($this->ublParty($dom, (string) $invoice['customer_name'], (string) $invoice['customer_siret'], (string) $invoice['customer_vat'], $invoice, false));
        $root->appendChild($customer);

        // Total TVA par taux.
        $taxTotal = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:TaxTotal'
        );
        $taxTotal->appendChild($cbc('TaxAmount', $this->money((float) $invoice['total_vat'])));
        $subtotals = [];
        foreach ($invoice['lines'] as $line) {
            $rate = (string) (float) $line['vat_rate'];
            $subtotals[$rate] = ($subtotals[$rate] ?? 0.0) + (float) $line['line_vat'];
        }
        foreach ($subtotals as $rate => $amount) {
            $subtotal = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:TaxSubtotal'
            );
            $subtotal->appendChild($cbc('TaxableAmount', $this->money($this->taxableForRate($invoice, (float) $rate))));
            $subtotal->appendChild($cbc('TaxAmount', $this->money($amount)));
            $taxCategory = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:TaxCategory'
            );
            $taxCategory->appendChild($cbc('ID', 'S'));
            $taxScheme = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:TaxScheme'
            );
            $taxScheme->appendChild($cbc('ID', 'VAT'));
            $taxCategory->appendChild($taxScheme);
            $subtotal->appendChild($taxCategory);
            $taxTotal->appendChild($subtotal);
        }
        $root->appendChild($taxTotal);

        $legalTotal = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:LegalMonetaryTotal'
        );
        $legalTotal->appendChild($cbc('LineExtensionAmount', $this->money((float) $invoice['total_ht'])));
        $legalTotal->appendChild($cbc('TaxExclusiveAmount', $this->money((float) $invoice['total_ht'])));
        $legalTotal->appendChild($cbc('TaxInclusiveAmount', $this->money((float) $invoice['total_ttc'])));
        $legalTotal->appendChild($cbc('PayableAmount', $this->money((float) $invoice['balance'])));
        $root->appendChild($legalTotal);

        foreach ($invoice['lines'] as $index => $line) {
            $invoiceLine = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:InvoiceLine'
            );
            $invoiceLine->appendChild($cbc('ID', (string) ($index + 1)));
            $invoiceLine->appendChild($cbc('InvoicedQuantity', (string) (float) $line['quantity']));
            $invoiceLine->appendChild($cbc('LineExtensionAmount', $this->money((float) $line['line_ht'])));
            $item = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Item'
            );
            $item->appendChild($cbc('Name', (string) $line['label']));
            $invoiceLine->appendChild($item);
            $price = $dom->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Price'
            );
            $price->appendChild($cbc('PriceAmount', $this->money((float) $line['unit_price'])));
            $invoiceLine->appendChild($price);
            $root->appendChild($invoiceLine);
        }

        return (string) $dom->saveXML();
    }

    /**
     * Facture CII (UN/CEFACT Cross Industry Invoice).
     *
     * @param array<string,mixed> $invoice
     */
    private function buildCii(array $invoice): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS('urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100', 'rsm:CrossIndustryInvoice');
        // Déclaration des préfixes utilisés dans le document CII.
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:ram',
            'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100'
        );
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:udt',
            'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100'
        );
        $dom->appendChild($root);

        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $exchanged = $dom->createElementNS(
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'rsm:ExchangedDocument'
        );
        $exchanged->appendChild($dom->createElement('ram:ID', $esc((string) $invoice['invoice_number'])));
        $exchanged->appendChild($dom->createElement('ram:TypeCode', '380'));
        $exchanged->appendChild($dom->createElement('ram:IssueDateTime', ''));
        $exchanged->lastChild->appendChild($dom->createElement('udt:DateTimeString', $esc(str_replace('-', '', (string) $invoice['issue_date']))));
        $root->appendChild($exchanged);

        $transaction = $dom->createElementNS(
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'rsm:SupplyChainTradeTransaction'
        );

        foreach ($invoice['lines'] as $index => $line) {
            $tradeLine = $dom->createElement('ram:IncludedSupplyChainTradeLineItem');
            $docLine = $dom->createElement('ram:AssociatedDocumentLineDocument');
            $docLine->appendChild($dom->createElement('ram:LineID', (string) ($index + 1)));
            $tradeLine->appendChild($docLine);
            $product = $dom->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($dom->createElement('ram:Name', $esc((string) $line['label'])));
            $tradeLine->appendChild($product);
            $agreement = $dom->createElement('ram:SpecifiedLineTradeAgreement');
            $netPrice = $dom->createElement('ram:NetPriceProductTradePrice');
            $netPrice->appendChild($dom->createElement('ram:ChargeAmount', $this->money((float) $line['unit_price'])));
            $agreement->appendChild($netPrice);
            $tradeLine->appendChild($agreement);
            $delivery = $dom->createElement('ram:SpecifiedLineTradeDelivery');
            $delivery->appendChild($dom->createElement('ram:BilledQuantity', (string) (float) $line['quantity']));
            $tradeLine->appendChild($delivery);
            $settlement = $dom->createElement('ram:SpecifiedLineTradeSettlement');
            $tradeTax = $dom->createElement('ram:ApplicableTradeTax');
            $tradeTax->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $tradeTax->appendChild($dom->createElement('ram:RateApplicablePercent', (string) (float) $line['vat_rate']));
            $settlement->appendChild($tradeTax);
            $settlement->appendChild($dom->createElement('ram:LineTotalAmount', $this->money((float) $line['line_ht'])));
            $tradeLine->appendChild($settlement);
            $transaction->appendChild($tradeLine);
        }

        $agreement = $dom->createElement('ram:ApplicableHeaderTradeAgreement');
        $agreement->appendChild($dom->createElement('ram:BuyerReference', $esc((string) $invoice['customer_name'])));
        $transaction->appendChild($agreement);
        $delivery = $dom->createElement('ram:ApplicableHeaderTradeDelivery');
        $transaction->appendChild($delivery);
        $settlement = $dom->createElement('ram:ApplicableHeaderTradeSettlement');
        $settlement->appendChild($dom->createElement('ram:InvoiceCurrencyCode', $esc((string) $invoice['currency'])));
        $tradeTax = $dom->createElement('ram:ApplicableTradeTax');
        $tradeTax->appendChild($dom->createElement('ram:BasisAmount', $this->money((float) $invoice['total_ht'])));
        $tradeTax->appendChild($dom->createElement('ram:CalculatedAmount', $this->money((float) $invoice['total_vat'])));
        $tradeTax->appendChild($dom->createElement('ram:CategoryCode', 'S'));
        $settlement->appendChild($tradeTax);
        $grandTotal = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $grandTotal->appendChild($dom->createElement('ram:LineTotalAmount', $this->money((float) $invoice['total_ht'])));
        $grandTotal->appendChild($dom->createElement('ram:TaxBasisTotalAmount', $this->money((float) $invoice['total_ht'])));
        $grandTotal->appendChild($dom->createElement('ram:TaxTotalAmount', $this->money((float) $invoice['total_vat'])));
        $grandTotal->appendChild($dom->createElement('ram:GrandTotalAmount', $this->money((float) $invoice['total_ttc'])));
        $grandTotal->appendChild($dom->createElement('ram:DuePayableAmount', $this->money((float) $invoice['balance'])));
        $settlement->appendChild($grandTotal);
        $transaction->appendChild($settlement);

        $root->appendChild($transaction);
        return (string) $dom->saveXML();
    }

    private function ublParty(
        \DOMDocument $dom,
        string $name,
        string $siret,
        string $vatNumber,
        array $invoice,
        bool $supplier = true
    ): \DOMElement {
        $cac = static fn (string $n): \DOMElement => $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac:' . $n
        );
        $cbc = static fn (string $n, string $v): \DOMElement => $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'cbc:' . $n,
            htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        );
        $party = $cac('Party');
        $partyTaxScheme = $cac('PartyTaxScheme');
        if ($vatNumber !== '') {
            $partyTaxScheme->appendChild($cbc('CompanyID', $vatNumber));
        }
        $taxScheme = $cac('TaxScheme');
        $taxScheme->appendChild($cbc('ID', 'VAT'));
        $partyTaxScheme->appendChild($taxScheme);
        if ($vatNumber !== '') {
            $party->appendChild($partyTaxScheme);
        }
        $partyLegal = $cac('PartyLegalEntity');
        $partyLegal->appendChild($cbc('RegistrationName', $name));
        if ($siret !== '') {
            $partyLegal->appendChild($cbc('CompanyID', $siret));
        }
        $addressKey = $supplier ? 'supplier' : 'customer';
        $address = $cac('PostalAddress');
        $address->appendChild($cbc('StreetName', (string) ($invoice[$addressKey . '_address'] ?? '')));
        $address->appendChild($cbc('PostalZone', (string) ($invoice[$addressKey . '_pc'] ?? '')));
        $address->appendChild($cbc('CityName', (string) ($invoice[$addressKey . '_city'] ?? '')));
        $country = $cac('Country');
        $country->appendChild($cbc('IdentificationCode', 'FR'));
        $address->appendChild($country);
        $partyLegal->appendChild($address);
        $party->appendChild($partyLegal);
        return $party;
    }

    /**
     * @param array<string,mixed> $invoice
     */
    private function taxableForRate(array $invoice, float $rate): float
    {
        $total = 0.0;
        foreach ($invoice['lines'] as $line) {
            if ((float) $line['vat_rate'] === $rate) {
                $total += (float) $line['line_ht'];
            }
        }
        return round($total, 2);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
