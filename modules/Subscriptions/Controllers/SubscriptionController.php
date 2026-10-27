<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Subscriptions\Services\SubscriptionScheduler;

/**
 * Offres d'abonnement, souscriptions clients et facturation récurrente.
 */
final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly SubscriptionScheduler $scheduler
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('subscriptions.read');
        try {
            $mrr = (float) $this->db->fetchScalar('SELECT COALESCE(mrr_ht, 0) FROM v_subscription_mrr');
        } catch (\Throwable) {
            $mrr = 0.0;
        }
        return $this->view('Subscriptions::index', [
            'title' => 'Abonnements',
            'mrr' => $mrr,
            'plans' => $this->db->fetchAll('SELECT * FROM subscription_plans ORDER BY label'),
            'subscriptions' => $this->db->fetchAll(
                'SELECT s.*, tp.name AS client, sp.label AS plan FROM subscriptions s
                 JOIN third_parties tp ON tp.id = s.third_party_id
                 JOIN subscription_plans sp ON sp.id = s.plan_id
                 ORDER BY s.next_invoice_date, s.id DESC LIMIT 300'
            ),
            'clients' => $this->db->fetchAll(
                "SELECT id, code, name FROM third_parties WHERE type IN ('client','both') AND is_active = 1 ORDER BY name"
            ),
            'runs' => $this->db->fetchAll('SELECT * FROM subscription_invoice_runs ORDER BY id DESC LIMIT 10'),
        ]);
    }

    public function storePlan(Request $request): Response
    {
        $this->auth()->requirePermission('subscriptions.write');
        $data = $this->validator()->validate($request->all(), [
            'code' => 'required|string|max:40',
            'label' => 'required|string|max:120',
            'amount_ht' => 'required|decimal:2',
            'vat_rate' => 'required|decimal:2',
            'billing_cycle' => 'required|in:monthly,quarterly,half_yearly,yearly',
        ]);
        $this->db->execute(
            'INSERT INTO subscription_plans (code, label, amount_ht, vat_rate, billing_cycle, is_active)
             VALUES (:code, :label, :amount, :vat, :cycle, 1)',
            [
                'code' => mb_strtoupper((string) $data['code']),
                'label' => $data['label'],
                'amount' => (float) $data['amount_ht'],
                'vat' => (float) $data['vat_rate'],
                'cycle' => $data['billing_cycle'],
            ]
        );
        audit('subscription.plan.create', 'SubscriptionPlan', (int) $this->db->lastInsertId());
        flash('success', 'Offre créée.');
        return new RedirectResponse('/subscriptions');
    }

    public function togglePlan(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('subscriptions.write');
        $this->db->execute('UPDATE subscription_plans SET is_active = 1 - is_active WHERE id = :id', ['id' => $id]);
        flash('success', 'Statut de l\'offre mis à jour.');
        return new RedirectResponse('/subscriptions');
    }

    public function storeSubscription(Request $request): Response
    {
        $this->auth()->requirePermission('subscriptions.write');
        $data = $this->validator()->validate($request->all(), [
            'third_party_id' => 'required|integer',
            'plan_id' => 'required|integer',
            'starts_on' => 'required|date',
            'next_invoice_date' => 'required|date',
            'quantity' => 'required|decimal:2',
            'unit_amount_ht' => 'required|decimal:2',
        ]);
        $vatRate = (float) $this->db->fetchScalar('SELECT vat_rate FROM subscription_plans WHERE id = :id', ['id' => $data['plan_id']]);
        $this->db->execute(
            'INSERT INTO subscriptions
                (third_party_id, plan_id, starts_on, status, quantity, unit_amount_ht, vat_rate, auto_invoice, next_invoice_date)
             VALUES (:party, :plan, :starts, \'active\', :qty, :unit, :vat, 1, :next)',
            [
                'party' => (int) $data['third_party_id'],
                'plan' => (int) $data['plan_id'],
                'starts' => $data['starts_on'],
                'qty' => (float) $data['quantity'],
                'unit' => (float) $data['unit_amount_ht'],
                'vat' => $vatRate,
                'next' => $data['next_invoice_date'],
            ]
        );
        audit('subscription.create', 'Subscription', (int) $this->db->lastInsertId());
        flash('success', 'Abonnement créé.');
        return new RedirectResponse('/subscriptions');
    }

    public function setStatus(Request $request, int $id, string $status): Response
    {
        $this->auth()->requirePermission('subscriptions.write');
        if (!in_array($status, ['active', 'paused', 'cancelled'], true)) {
            flash('error', 'Statut invalide.');
            return new RedirectResponse('/subscriptions');
        }
        $this->db->execute('UPDATE subscriptions SET status = :status, updated_at = ' . $this->db->nowExpression() . ' WHERE id = :id', [
            'status' => $status,
            'id' => $id,
        ]);
        audit('subscription.status', 'Subscription', $id, ['status' => $status]);
        flash('success', 'Abonnement mis à jour.');
        return new RedirectResponse('/subscriptions');
    }

    public function runNow(Request $request): Response
    {
        $this->auth()->requirePermission('subscriptions.write');
        $count = $this->scheduler->generateDueInvoices(new \DateTimeImmutable(), (int) ($this->auth()->id() ?? 1));
        flash('success', $count > 0 ? "{$count} facture(s) d'abonnement générée(s)." : 'Aucun abonnement dû à ce jour.');
        return new RedirectResponse('/subscriptions');
    }
}
