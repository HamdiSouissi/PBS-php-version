<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/subscriptions', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $db = db();
            try {
                $mrr = (float) $db->fetchScalar('SELECT COALESCE(mrr_ht, 0) FROM v_subscription_mrr');
            } catch (\Throwable) {
                $mrr = 0.0;
            }
            $plans = $db->fetchAll('SELECT code, label, amount_ht, vat_rate, billing_cycle, is_active FROM subscription_plans ORDER BY label');
            $plans = array_map(static fn (array $r): array => $r + ['active' => (int) $r['is_active'] === 1 ? 'Oui' : 'Non'], $plans);
            $subscriptions = $db->fetchAll(
                'SELECT s.*, tp.name AS client, sp.label AS plan
                 FROM subscriptions s
                 JOIN third_parties tp ON tp.id = s.third_party_id
                 JOIN subscription_plans sp ON sp.id = s.plan_id
                 ORDER BY s.next_invoice_date LIMIT 200'
            );

            return view('modules/list', [
                'title' => 'Abonnements',
                'heading' => 'Abonnements',
                'intro' => 'MRR HT actuel : ' . money_format($mrr) . ' — ARR : ' . money_format($mrr * 12),
                'sections' => [
                    ['title' => 'Offres', 'rows' => $plans, 'empty' => 'Aucune offre.', 'columns' => [
                        'code' => ['Code'], 'label' => ['Libellé'], 'amount_ht' => ['Montant HT', 'money'], 'vat_rate' => ['TVA %'], 'billing_cycle' => ['Cycle', 'badge'], 'active' => ['Active'],
                    ]],
                    ['title' => 'Abonnements clients', 'rows' => $subscriptions, 'empty' => 'Aucun abonnement.', 'columns' => [
                        'client' => ['Client'], 'plan' => ['Offre'], 'status' => ['Statut', 'badge'], 'quantity' => ['Qté'],
                        'unit_amount_ht' => ['Prix unit. HT', 'money'], 'next_invoice_date' => ['Prochaine facture', 'date'],
                    ]],
                ],
            ]);
        })->middleware('permission:subscriptions.read')->name('subscriptions.index');
    });
};
