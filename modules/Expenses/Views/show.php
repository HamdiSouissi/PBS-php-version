<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $report
 * @var list<array<string,mixed>> $lines
 * @var ?array<string,mixed> $pdfDoc
 */
$statusBadges = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'paid' => 'success'];
$statusLabels = ['draft' => 'Brouillon', 'submitted' => 'Soumise', 'approved' => 'Validée', 'rejected' => 'Rejetée', 'paid' => 'Payée'];
$categoryLabels = ['transport' => 'Transport', 'meal' => 'Repas', 'accommodation' => 'Hébergement', 'fuel' => 'Carburant', 'kilometric' => 'Kilométrique', 'supplies' => 'Fournitures', 'other' => 'Autre'];
$id = (int) $report['id'];

$actions = '<span class="badge text-bg-' . e($statusBadges[$report['status']] ?? 'secondary') . '">'
    . e($statusLabels[$report['status']] ?? $report['status']) . '</span>';
$actions .= '<form method="post" action="/expenses/' . $id . '/pdf">'
    . csrf_field() . '<button class="btn btn-primary btn-sm"><i data-lucide="file-text"></i>PDF</button></form>';
if ($pdfDoc !== null) {
    $actions .= '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/' . (int) $pdfDoc['id'] . '/preview"><i data-lucide="eye"></i>Aperçu</a>';
    $actions .= '<a class="btn btn-outline-secondary btn-sm" href="/pdf/' . (int) $pdfDoc['id'] . '/download"><i data-lucide="download"></i>Télécharger</a>';
}
if ($report['status'] === 'draft') {
    $actions .= '<form method="post" action="/expenses/' . $id . '/submit">' . csrf_field() . '<button class="btn btn-warning btn-sm">Soumettre</button></form>';
}
if ($report['status'] === 'submitted') {
    $actions .= '<form method="post" action="/expenses/' . $id . '/approve">' . csrf_field() . '<button class="btn btn-success btn-sm">Valider</button></form>';
    $actions .= '<form method="post" action="/expenses/' . $id . '/reject" data-confirm="Rejeter cette note de frais ?" data-confirm-title="Rejet d\'une note de frais">'
        . csrf_field() . '<button class="btn btn-outline-danger btn-sm">Rejeter</button></form>';
}
if ($report['status'] === 'approved') {
    $actions .= '<form method="post" action="/expenses/' . $id . '/pay">' . csrf_field() . '<button class="btn btn-success btn-sm">Marquer payée</button></form>';
}
?>
<?= $viewRenderer->pageHeader(
    'Note de frais NF-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
    [['label' => 'Notes de frais', 'href' => '/expenses']],
    $actions,
    mb_strtoupper((string) $report['last_name']) . ' ' . $report['first_name'] . ' · ' . $report['title']
) ?>

<div class="page-body">
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light"><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th class="text-end">Montant TTC</th><th class="text-end">TVA</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime((string) $line['spent_on']))) ?></td>
                    <td><span class="badge text-bg-light border"><?= e($categoryLabels[$line['category']] ?? $line['category']) ?></span></td>
                    <td><?= e($line['label']) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $line['amount']) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $line['vat_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr class="table-light"><td colspan="3" class="text-end fw-bold">Total TTC</td><td class="text-end fw-bold tnum"><?= money_format((float) $report['total_amount']) ?></td><td></td></tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (($report['notes'] ?? '') !== ''): ?>
    <div class="card"><div class="card-header">Notes</div><div class="card-body small"><?= nl2br(e((string) $report['notes'])) ?></div></div>
<?php endif; ?>
</div>
