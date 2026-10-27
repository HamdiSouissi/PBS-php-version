<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $reports
 */
$statusBadges = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'paid' => 'success'];
$statusLabels = ['draft' => 'Brouillon', 'submitted' => 'Soumise', 'approved' => 'Validée', 'rejected' => 'Rejetée', 'paid' => 'Payée'];
$actions = '<a class="btn btn-primary btn-sm" href="/expenses/new"><i data-lucide="plus"></i>Nouvelle note</a>';
?>
<?= $viewRenderer->pageHeader(
    'Notes de frais',
    [],
    $actions,
    count($reports) . ' note(s) — saisie, validation et paiement.'
) ?>

<div class="page-body">
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" data-sortable>
            <thead class="table-light">
            <tr><th>N°</th><th>Salarié</th><th>Intitulé</th><th>Soumise</th><th class="text-end">Total TTC</th><th>Statut</th></tr>
            </thead>
            <tbody>
            <?php if ($reports === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune note de frais.</td></tr>
            <?php endif; ?>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><a class="fw-semibold text-decoration-none" href="/expenses/<?= (int) $report['id'] ?>">NF-<?= str_pad((string) $report['id'], 4, '0', STR_PAD_LEFT) ?></a></td>
                    <td><?= e(mb_strtoupper((string) $report['last_name']) . ' ' . $report['first_name']) ?></td>
                    <td><?= e($report['title']) ?></td>
                    <td><?= e($report['submitted_at'] ? date('d/m/Y', strtotime((string) $report['submitted_at'])) : '—') ?></td>
                    <td class="text-end tnum"><?= money_format((float) $report['total_amount']) ?></td>
                    <td><span class="badge text-bg-<?= e($statusBadges[$report['status']] ?? 'secondary') ?>"><?= e($statusLabels[$report['status']] ?? $report['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
