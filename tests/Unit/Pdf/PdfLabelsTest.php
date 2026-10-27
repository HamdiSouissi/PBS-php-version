<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Core\Pdf\PdfLabels;
use Tests\Core\TestCase;

/**
 * Verrouille la terminologie française des libellés PDF et garantit qu'un
 * code inconnu n'est jamais masqué (restitution telle quelle).
 */
final class PdfLabelsTest extends TestCase
{
    public function testInvoiceAndQuoteStatusesAreFrench(): void
    {
        self::assertSame('Payée', PdfLabels::invoiceStatus('paid'));
        self::assertSame('En retard', PdfLabels::invoiceStatus('overdue'));
        self::assertSame('Paiement partiel', PdfLabels::invoiceStatus('partial'));
        self::assertSame('Facturé', PdfLabels::quoteStatus('invoiced'));
        self::assertSame('Accepté', PdfLabels::quoteStatus('accepted'));
    }

    public function testPayslipEmployeeAndContractStatusesAreFrench(): void
    {
        self::assertSame('Clôturé', PdfLabels::payslipStatus('locked'));
        self::assertSame('Brouillon', PdfLabels::payslipStatus('draft'));
        self::assertSame('Actif', PdfLabels::employeeStatus('active'));
        self::assertSame('En congé', PdfLabels::employeeStatus('on_leave'));
        self::assertSame('Résilié', PdfLabels::contractStatus('terminated'));
        self::assertSame('En pause', PdfLabels::subscriptionStatus('paused'));
    }

    public function testExpenseAndPaymentLabelsAreFrench(): void
    {
        self::assertSame('Validée', PdfLabels::expenseStatus('approved'));
        self::assertSame('Frais kilométriques', PdfLabels::expenseCategory('kilometric'));
        self::assertSame('Trimestriel', PdfLabels::billingCycle('quarterly'));
        self::assertSame('Prélèvement', PdfLabels::paymentMethod('prelevement'));
        self::assertSame('Chèque', PdfLabels::paymentMethod('cheque'));
    }

    public function testUnknownCodeIsReturnedUnchanged(): void
    {
        self::assertSame('future_status_2030', PdfLabels::invoiceStatus('future_status_2030'));
        self::assertSame('custom', PdfLabels::contractStatus('custom'));
    }

    public function testEmptyEmployeeStatusRendersDash(): void
    {
        self::assertSame('—', PdfLabels::employeeStatus(''));
    }
}
