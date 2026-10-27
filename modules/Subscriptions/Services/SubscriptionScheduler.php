<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use App\Core\Database\Connection;
use App\Core\Event\EventDispatcher;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Subscriptions\Events\SubscriptionInvoiceGenerated;

/**
 * Génération des factures d'abonnements dus (commande `subscriptions:generate`
 * et planificateur). Cette classe était référencée par la commande console et
 * par `schedule:run` mais n'existait pas dans le dépôt (erreur « Class not found »).
 */
final class SubscriptionScheduler
{
    private const INTERVALS = [
        'monthly' => '+1 month',
        'quarterly' => '+3 months',
        'half_yearly' => '+6 months',
        'yearly' => '+1 year',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly InvoiceService $invoices,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * @return int nombre de factures générées
     */
    public function generateDueInvoices(?\DateTimeImmutable $on = null, int $systemUserId = 1): int
    {
        $on ??= new \DateTimeImmutable();
        $today = $on->format('Y-m-d');

        $companyId = (int) $this->db->fetchScalar('SELECT id FROM companies ORDER BY is_default DESC, id ASC LIMIT 1');
        if ($companyId === 0) {
            return 0;
        }

        $subscriptions = $this->db->fetchAll(
            "SELECT s.*, sp.label AS plan_label, sp.billing_cycle AS plan_cycle
             FROM subscriptions s
             JOIN subscription_plans sp ON sp.id = s.plan_id
             WHERE s.status = 'active' AND s.auto_invoice = 1
               AND s.next_invoice_date <= :today
               AND (s.ends_on IS NULL OR s.ends_on >= :today)
             ORDER BY s.next_invoice_date, s.id",
            ['today' => $today]
        );

        $details = [];
        $totalTtc = 0.0;

        foreach ($subscriptions as $subscription) {
            try {
                $invoiceId = $this->invoices->create(
                    $companyId,
                    [
                        'third_party_id' => (int) $subscription['third_party_id'],
                        'issue_date' => $today,
                        'due_date' => date('Y-m-d', strtotime($today . ' +30 days')),
                        'notes' => 'Abonnement : ' . $subscription['plan_label'],
                        'lines' => [[
                            'label' => 'Abonnement ' . $subscription['plan_label'],
                            'quantity' => (float) $subscription['quantity'],
                            'unit_price' => (float) $subscription['unit_amount_ht'],
                            'vat_rate' => (float) $subscription['vat_rate'],
                            'discount_pct' => 0,
                        ]],
                    ],
                    $systemUserId
                );
            } catch (\Throwable $exception) {
                // Une souscription en erreur ne doit pas bloquer les autres.
                logger()->warning('Abonnement ' . $subscription['id'] . ' non facturé : ' . $exception->getMessage());
                continue;
            }

            $interval = self::INTERVALS[$subscription['plan_cycle']] ?? '+1 month';
            $next = (new \DateTimeImmutable((string) $subscription['next_invoice_date']))->modify($interval)->format('Y-m-d');
            $this->db->execute(
                'UPDATE subscriptions
                    SET last_invoice_date = :today, next_invoice_date = :next, updated_at = ' . $this->db->nowExpression() . '
                  WHERE id = :id',
                ['today' => $today, 'next' => $next, 'id' => $subscription['id']]
            );

            $ttc = (float) $this->db->fetchScalar('SELECT total_ttc FROM invoices WHERE id = :id', ['id' => $invoiceId]);
            $totalTtc += $ttc;
            $details[] = ['subscription_id' => (int) $subscription['id'], 'invoice_id' => $invoiceId, 'total_ttc' => $ttc];
            audit('subscription.invoice', 'Subscription', (int) $subscription['id'], ['invoice_id' => $invoiceId]);
            $this->events->dispatch(new SubscriptionInvoiceGenerated((int) $subscription['id'], $invoiceId, $ttc));
        }

        if ($details !== []) {
            $this->db->execute(
                'INSERT INTO subscription_invoice_runs (run_date, generated_count, total_ttc, details, created_by)
                 VALUES (:d, :c, :t, :details, :u)',
                [
                    'd' => $today,
                    'c' => count($details),
                    't' => round($totalTtc, 2),
                    'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                    'u' => $systemUserId,
                ]
            );
        }

        return count($details);
    }
}
