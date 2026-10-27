<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $report
 * @var list<array<string,mixed>> $lines
 * @var ?array<string,mixed> $pdfDoc
 */
$statusBadges = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'paid' => 'success'];
$statusLabels = ['draft' => 'Brouillon', 'submitted' => 'Soumise', 'approved' => 'Validée', 'rejected' => 'Rejetée', 'paid' => 'Payée'];
$categoryLabels = ['transport' => 'Transport', 'meal' => 'Repas', 'accommodation' => 'Hébergement', 'fuel' => 'Carburant', 'kilometric' => 'Kilométrique', 'supplies' => 'Fournitures', 'other' => 'Autre'];
$id = (int) $report['id'];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-1">Note de frais NF-<?= str_pad((string) $id, 4, '0', STR_PAD_LEFT) ?>
            <span class="badge text-bg-<?= e($statusBadges[$report['status']] ?? 'secondary') ?>"><?= e($statusLabels[$report['status']] ?? $report['status']) ?></span>
        </h2>
        <span class="text-muted small"><?= e(mb_strtoupper((string) $report['last_name']) . ' ' . $report['first_name']) ?> · <?= e($report['title']) ?></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="/expenses/<?= $id ?>/pdf">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
        </form>
        <?php if ($pdfDoc !== null): ?>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/<?= (int) $pdfDoc['id'] ?>/preview"><i class="bi bi-eye me-1"></i>Aperçu</a>
            <a class="btn btn-outline-secondary btn-sm" href="/pdf/<?= (int) $pdfDoc['id'] ?>/download"><i class="bi bi-download me-1"></i>Télécharger</a>
        <?php endif; ?>
        <?php if ($report['status'] === 'draft'): ?>
            <form method="post" action="/expenses/<?= $id ?>/submit"><?= csrf_field() ?><button class="btn btn-warning btn-sm">Soumettre</button></form>
        <?php endif; ?>
        <?php if ($report['status'] === 'submitted'): ?>
            <form method="post" action="/expenses/<?= $id ?>/approve"><?= csrf_field() ?><button class="btn btn-success btn-sm">Valider</button></form>
            <form method="post" action="/expenses/<?= $id ?>/reject"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Rejeter</button></form>
        <?php endif; ?>
        <?php if ($report['status'] === 'approved'): ?>
            <form method="post" action="/expenses/<?= $id ?>/pay"><?= csrf_field() ?><button class="btn btn-success btn-sm">Marquer payée</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th class="text-end">Montant TTC</th><th class="text-end">TVA</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime((string) $line['spent_on']))) ?></td>
                    <td><span class="badge text-bg-light border"><?= e($categoryLabels[$line['category']] ?? $line['category']) ?></span></td>
                    <td><?= e($line['label']) ?></td>
                    <td class="text-end"><?= money_format((float) $line['amount']) ?></td>
                    <td class="text-end"><?= money_format((float) $line['vat_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr class="table-light"><td colspan="3" class="text-end fw-bold">Total TTC</td><td class="text-end fw-bold"><?= money_format((float) $report['total_amount']) ?></td><td></td></tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (($report['notes'] ?? '') !== ''): ?>
    <div class="card mt-3"><div class="card-header">Notes</div><div class="card-body small"><?= nl2br(e((string) $report['notes'])) ?></div></div>
<?php endif; ?>
