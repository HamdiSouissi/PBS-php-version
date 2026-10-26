<?php

declare(strict_types=1);

namespace Modules\Invoicing\Events;

/**
 * Facture émise : les modules abonnés (comptabilité) génèrent leurs écritures.
 */
final readonly class InvoiceIssued
{
    public function __construct(
        public int $invoiceId,
        public int $companyId,
        public int $thirdPartyId
    ) {
    }
}
