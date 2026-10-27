<?php

declare(strict_types=1);

namespace Modules\Payroll\Listeners;

use App\Core\Pdf\PdfManager;
use Modules\Payroll\Events\PayslipValidated;

/**
 * Génère le PDF du bulletin de paie après validation.
 */
final class GeneratePayslipDocument
{
    public function __construct(private readonly PdfManager $pdf)
    {
    }

    public function handle(PayslipValidated $event): void
    {
        try {
            $this->pdf->generate('payslip', $event->payslipId);
        } catch (\Throwable $exception) {
            logger()->error('Génération PDF bulletin échouée', [
                'payslip_id' => $event->payslipId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
