<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Events;

/**
 * Facture d'abonnement générée par le planificateur de récurrence.
 */
final readonly class SubscriptionInvoiceGenerated
{
    public function __construct(
        public int $subscriptionId,
        public int $invoiceId,
        public float $totalTtc
    ) {
    }
}
