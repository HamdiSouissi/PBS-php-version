<?php

declare(strict_types=1);

namespace Modules\Invoicing\Listeners;

use Modules\Invoicing\Events\InvoiceIssued;
use Modules\Invoicing\Services\EInvoiceService;

/**
 * À l'émission d'une facture, génère automatiquement le document électronique
 * UBL 2.1 (EN 16931) dans la boîte d'envoi. La télétransmission reste externe :
 * le message reste au statut 'generated' tant qu'un opérateur ne l'a pas exporté.
 */
final class GenerateInvoiceDocument
{
    public function __construct(private readonly EInvoiceService $eInvoices)
    {
    }

    public function handle(InvoiceIssued $event): void
    {
        $this->eInvoices->generate($event->invoiceId, EInvoiceService::FORMAT_UBL);
    }
}
