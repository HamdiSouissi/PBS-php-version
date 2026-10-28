<?php

declare(strict_types=1);

/**
 * @var float $mrr
 * @var list<array<string,mixed>> $plans
 * @var list<array<string,mixed>> $subscriptions
 * @var list<array<string,mixed>> $clients
 * @var list<array<string,mixed>> $runs
 */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel'];
$statusBadges = ['active' => 'success', 'paused' => 'warning', 'cancelled' => 'danger'];
$actions = '<button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal"><i data-lucide="bookmark-plus"></i>Nouvelle offre</button>'
    . '<form method="post" action="/subscriptions/run">'
    . csrf_field()
    . '<button class="btn btn-outline-primary btn-sm"><i data-lucide="zap"></i>Facturer les abonnements dus</button></form>'
    . '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#subModal"><i data-lucide="plus"></i>Nouvel abonnement</button>';
?>
<?= $viewRenderer->pageHeader(
    'Abonnements',
    [],
    $actions,
    'MRR HT : ' . money_format($mrr) . ' · ARR : ' . money_format($mrr * 12)
) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Offres</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" data-sortable>
                    <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Cycle</th><th class="text-end">Montant HT</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php if ($plans === []): ?><tr><td colspan="5" class="text-center text-muted py-3">Aucune offre.</td></tr><?php endif; ?>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($plan['code']) ?></td>
                            <td><?= e($plan['label']) ?></td>
                            <td><?= e($cycleLabels[$plan['billing_cycle']] ?? $plan['billing_cycle']) ?></td>
                            <td class="text-end"><?= money_format((float) $plan['amount_ht']) ?></td>
                            <td>
                                <form method="post" action="/subscriptions/plans/<?= (int) $plan['id'] ?>/toggle">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= (int) $plan['is_active'] === 1 ? 'btn-success' : 'btn-outline-secondary' ?>">
                                        <?= (int) $plan['is_active'] === 1 ? 'Oui' : 'Non' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Abonnements clients</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" data-sortable>
                    <thead class="table-light"><tr><th>Client</th><th>Offre</th><th class="text-end">Qté</th><th class="text-end">Prix unit. HT</th><th>Prochaine facture</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($subscriptions === []): ?><tr><td colspan="7" class="text-center text-muted py-3">Aucun abonnement.</td></tr><?php endif; ?>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <td><?= e($subscription['client']) ?></td>
                            <td><?= e($subscription['plan']) ?></td>
                            <td class="text-end"><?= e((string) $subscription['quantity']) ?></td>
                            <td class="text-end"><?= money_format((float) $subscription['unit_amount_ht']) ?></td>
                            <td class="small"><?= e(date('d/m/Y', strtotime((string) $subscription['next_invoice_date']))) ?></td>
                            <td><span class="badge text-bg-<?= e($statusBadges[$subscription['status']] ?? 'secondary') ?>"><?= e($subscription['status']) ?></span></td>
                            <td class="text-end">
                                <?php if ($subscription['status'] === 'active'): ?>
                                    <form method="post" action="/subscriptions/<?= (int) $subscription['id'] ?>/status/paused" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-warning">Pause</button></form>
                                <?php elseif ($subscription['status'] === 'paused'): ?>
                                    <form method="post" action="/subscriptions/<?= (int) $subscription['id'] ?>/status/active" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-success">Reprendre</button></form>
                                <?php endif; ?>
                                <?php if ($subscription['status'] !== 'cancelled'): ?>
                                    <form method="post" action="/subscriptions/<?= (int) $subscription['id'] ?>/status/cancelled" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Résilier</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($runs !== []): ?>
<div class="card mt-3">
    <div class="card-header">Dernières campagnes de facturation</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0" data-sortable>
            <thead class="table-light"><tr><th>Date</th><th class="text-end">Factures générées</th><th class="text-end">Total TTC</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $run): ?>
                <tr><td><?= e(date('d/m/Y H:i', strtotime((string) $run['run_date']))) ?></td><td class="text-end"><?= (int) $run['generated_count'] ?></td><td class="text-end"><?= money_format((float) $run['total_ttc']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="planModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="planModalTitle">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/subscriptions/plans">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="planModalTitle">Nouvelle offre</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label small">Code *</label><input class="form-control form-control-sm" name="code" required></div>
                <div class="col-6"><label class="form-label small">Libellé *</label><input class="form-control form-control-sm" name="label" required></div>
                <div class="col-4"><label class="form-label small">Montant HT *</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="amount_ht" required></div>
                <div class="col-4"><label class="form-label small">TVA % *</label><input type="number" step="0.01" class="form-control form-control-sm" name="vat_rate" value="20" required></div>
                <div class="col-4"><label class="form-label small">Cycle *</label>
                    <select class="form-select form-select-sm" name="billing_cycle">
                        <?php foreach ($cycleLabels as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Créer l'offre</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="subModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="subModalTitle">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post" action="/subscriptions">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Nouvel abonnement client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Client *</label>
                    <select class="form-select form-select-sm" name="third_party_id" required>
                        <option value="">—</option>
                        <?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Offre *</label>
                    <select class="form-select form-select-sm" name="plan_id" required>
                        <option value="">—</option>
                        <?php foreach ($plans as $plan): ?><option value="<?= (int) $plan['id'] ?>" data-amount="<?= e((string) $plan['amount_ht']) ?>"><?= e($plan['label']) ?> (<?= money_format((float) $plan['amount_ht']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label small">Date d'effet *</label><input type="date" class="form-control form-control-sm" name="starts_on" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Prochaine facture *</label><input type="date" class="form-control form-control-sm" name="next_invoice_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-2"><label class="form-label small">Qté *</label><input type="number" step="0.01" min="1" class="form-control form-control-sm" name="quantity" value="1" required></div>
                <div class="col-md-2"><label class="form-label small">Prix unit. HT *</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="unit_amount_ht" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Créer l'abonnement</button></div>
        </form>
    </div>
</div>
</div>
<!-- /.page-body -->
