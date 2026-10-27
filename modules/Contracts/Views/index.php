<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $contracts */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'];
$statusBadges = ['draft' => 'secondary', 'active' => 'success', 'terminated' => 'danger', 'expired' => 'warning'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Contrats clients</h2>
        <p class="text-muted mb-0"><?= count($contracts) ?> contrat(s) — renouvellement et préavis suivis.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="/contracts/new"><i class="bi bi-plus-lg me-1"></i>Nouveau contrat</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
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
                    <td class="text-end"><?= money_format((float) $contract['amount_ht']) ?></td>
                    <td><span class="badge text-bg-<?= e($statusBadges[$contract['status']] ?? 'secondary') ?>"><?= e($contract['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
