<?php

declare(strict_types=1);

namespace Modules\Invoicing\Events;

/**
 * Paiement encaissé : la comptabilité peut letttrer l'écriture de vente.
 */
final readonly class PaymentRecorded
{
    public function __construct(
        public int $invoiceId,
        public int $paymentId,
        public float $amount,
        public string $newStatus
    ) {
    }
}
