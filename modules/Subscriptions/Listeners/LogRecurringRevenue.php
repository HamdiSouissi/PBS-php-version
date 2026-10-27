<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Listeners;

use App\Core\Database\Connection;
use Modules\Subscriptions\Events\SubscriptionInvoiceGenerated;

/**
 * Trace les factures récurrentes générées dans le journal d'audit et
 * alimente l'instantané de revenus récurrents du jour.
 */
final class LogRecurringRevenue
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function handle(SubscriptionInvoiceGenerated $event): void
    {
        audit('subscription.invoice_generated', 'Invoice', $event->invoiceId, [
            'subscription_id' => $event->subscriptionId,
            'total_ttc' => $event->totalTtc,
        ]);

        $mrr = (float) $this->db->fetchScalar('SELECT COALESCE(mrr_ht, 0) FROM v_subscription_mrr');
        $this->db->upsert(
            'revenue_snapshots',
            [
                'snapshot_on' => date('Y-m-d'),
                'mrr' => $mrr,
                'arr' => $mrr * 12,
                'active_subscriptions' => (int) $this->db->fetchScalar(
                    "SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"
                ),
                'churned' => 0,
            ],
            ['snapshot_on'],
            ['mrr', 'arr', 'active_subscriptions']
        );
    }
}
