<?php

declare(strict_types=1);

namespace Modules\Payroll\Events;

/**
 * Bulletin de paie validé : déclenche la génération du PDF.
 */
final readonly class PayslipValidated
{
    public function __construct(
        public int $payslipId,
        public int $employeeId,
        public int $periodId
    ) {
    }
}
