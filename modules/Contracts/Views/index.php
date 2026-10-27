<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $contracts
 */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'];
$statusBadges = ['draft' => 'secondary', 'active' => 'success', 'terminated' => 'danger', 'expired' => 'warning'];
$actions = '<a class="btn btn-primary btn-sm" href="/contracts/new"><i data-lucide="plus"></i>Nouveau contrat</a>';
?>
<?= $viewRenderer->pageHeader(
    'Contrats clients',
    [],
    $actions,
    count($contracts) . ' contrat(s) — renouvellement et préavis suivis.'
) ?>

<div class="page-body">
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" data-sortable>
            <thead class="table-light">
            <tr><th>Référence</th><th>Client</th><th>Intitulé</th><th>Période</th><th>Cycle</th><th class="text-end">Montant HT</th><th>Statut</th></tr>
            </thead>
            <tbody>
            <?php if ($contracts === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun contrat client.</td></tr>
            <?php endif; ?>
            <?php foreach ($contracts as $contract): ?>
                <tr>
                    <td><a class="fw-semibold text-decoration-none" href="/contracts/<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?></a></td>
                    <td><?= e($contract['client_name']) ?></td>
                    <td><?= e($contract['title']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $contract['starts_on']))) ?>
                        → <?= e($contract['ends_on'] ? date('d/m/Y', strtotime((string) $contract['ends_on'])) : 'indéterminé') ?></td>
                    <td><?= e($cycleLabels[$contract['billing_cycle']] ?? $contract['billing_cycle']) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $contract['amount_ht']) ?></td>
                    <td><span class="badge text-bg-<?= e($statusBadges[$contract['status']] ?? 'secondary') ?>"><?= e($contract['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
