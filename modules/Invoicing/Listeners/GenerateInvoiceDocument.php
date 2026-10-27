<?php

declare(strict_types=1);

namespace Modules\Invoicing\Listeners;

use App\Core\Pdf\PdfManager;
use Modules\Invoicing\Events\InvoiceIssued;

/**
 * Génère automatiquement le PDF d'une facture dès son émission.
 * Une anomalie d'export ne doit pas casser le cycle de vente (l'émission
 * est déjà commitée) : l'erreur est journalisée et reste régénérable.
 */
final class GenerateInvoiceDocument
{
    public function __construct(private readonly PdfManager $pdf)
    {
    }

    public function handle(InvoiceIssued $event): void
    {
        try {
            $this->pdf->generate('invoice', $event->invoiceId);
        } catch (\Throwable $exception) {
            logger()->error('Génération PDF facture échouée', [
                'invoice_id' => $event->invoiceId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
