<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

use App\Core\Database\Connection;

/**
 * Génération des factures récurrentes à partir des modèles, selon leur
 * fréquence. Pilotable par le planificateur (commande console) ou à la main.
 */
final class RecurringInvoiceService
{
    private const INTERVALS = [
        'monthly' => '+1 month',
        'quarterly' => '+3 months',
        'half_yearly' => '+6 months',
        'yearly' => '+1 year',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly InvoiceService $invoices
    ) {
    }

    /**
     * Génère toutes les factures dues à la date donnée.
     *
     * @return list<int> identifiants des factures créées
     */
    public function runDue(?\DateTimeImmutable $on = null, int $systemUserId = 1): array
    {
        $on ??= new \DateTimeImmutable();
        $today = $on->format('Y-m-d');

        $templates = $this->db->fetchAll(
            'SELECT * FROM recurring_invoice_templates
             WHERE is_active AND next_run_on <= :today
             ORDER BY next_run_on, id
             FOR UPDATE SKIP LOCKED',
            ['today' => $today]
        );

        $created = [];
        foreach ($templates as $template) {
            $lines = json_decode((string) $template['lines_payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($lines) || $lines === []) {
                continue;
            }

            $invoiceId = $this->invoices->create(
                (int) $template['company_id'],
                [
                    'third_party_id' => (int) $template['third_party_id'],
                    'issue_date' => $today,
                    'due_date' => date('Y-m-d', strtotime($today . ' +30 days')),
                    'notes' => 'Facturation récurrente : ' . $template['label'],
                    'lines' => $lines,
                ],
                $systemUserId
            );

            $nextRun = date('Y-m-d', strtotime($today . ' ' . self::INTERVALS[$template['frequency']]));
            $this->db->execute(
                'UPDATE recurring_invoice_templates
                    SET last_run_on = :today, next_run_on = :next, updated_at = now()
                  WHERE id = :id',
                ['today' => $today, 'next' => $nextRun, 'id' => $template['id']]
            );
            audit('recurring_invoice.run', 'RecurringInvoiceTemplate', (int) $template['id'], ['invoice_id' => $invoiceId]);
            $created[] = $invoiceId;
        }

        return $created;
    }
}
