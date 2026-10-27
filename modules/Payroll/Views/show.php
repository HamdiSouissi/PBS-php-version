<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $payslip
 * @var list<array<string,mixed>> $lines
 * @var array<string,mixed>|null $document
 */
$statusBadges = ['draft' => 'secondary', 'validated' => 'info', 'locked' => 'success'];
$statusLabels = ['draft' => 'Brouillon', 'validated' => 'Validé', 'locked' => 'Verrouillé'];

$actions = '<span class="badge text-bg-' . e($statusBadges[$payslip['status']] ?? 'secondary') . '">'
    . e($statusLabels[$payslip['status']] ?? $payslip['status']) . '</span>';
if ((int) $payslip['is_paid'] === 1) {
    $actions .= '<span class="badge text-bg-success">Payé'
        . ($payslip['payment_date'] ? ' le ' . e(date('d/m/Y', strtotime((string) $payslip['payment_date']))) : '') . '</span>';
}
if ($document !== null) {
    $actions .= '<a class="btn btn-outline-primary btn-sm" href="/pdf/' . (int) $document['id'] . '/preview" target="_blank"><i data-lucide="eye"></i>Aperçu PDF</a>';
    $actions .= '<a class="btn btn-outline-secondary btn-sm" href="/pdf/' . (int) $document['id'] . '/download"><i data-lucide="download"></i>Télécharger</a>';
}
$actions .= '<form method="post" action="/payroll/' . (int) $payslip['id'] . '/pdf">'
    . csrf_field() . '<button class="btn btn-outline-secondary btn-sm"><i data-lucide="file-text"></i>'
    . ($document !== null ? 'Régénérer' : 'Générer le PDF') . '</button></form>';
?>
<?= $viewRenderer->pageHeader(
    'Bulletin ' . sprintf('%02d/%d', (int) $payslip['month'], (int) $payslip['year'])
        . ' — ' . $payslip['first_name'] . ' ' . mb_strtoupper((string) $payslip['last_name']),
    [['label' => 'Paie', 'href' => '/payroll']],
    $actions
) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-8">
        <?php if ($lines !== []): ?>
        <div class="card">
            <div class="card-header">Détail des cotisations</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" data-sortable>
                    <thead class="table-light"><tr><th>Cotisation</th><th class="text-end">Base</th><th class="text-end">Taux salarial</th><th class="text-end">Part salariale</th><th class="text-end">Part patronale</th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td><span class="fw-semibold"><?= e($line['rule_code']) ?></span><br><span class="small text-muted"><?= e($line['label']) ?></span></td>
                            <td class="text-end tnum"><?= money_format((float) $line['base_amount']) ?></td>
                            <td class="text-end tnum"><?= e(rtrim(rtrim(number_format((float) $line['rate_employee'], 3, ',', ' '), '0'), ',')) ?> %</td>
                            <td class="text-end tnum"><?= money_format((float) $line['amount_employee']) ?></td>
                            <td class="text-end tnum"><?= money_format((float) $line['amount_employer']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr class="table-light fw-semibold">
                        <td>Totaux</td>
                        <td></td>
                        <td></td>
                        <td class="text-end tnum"><?= money_format((float) $payslip['total_employee']) ?></td>
                        <td class="text-end tnum"><?= money_format((float) $payslip['total_employer']) ?></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-warning">Bulletin sans lignes de cotisations (données de démonstration). Régénérez le bulletin depuis <a href="/payroll/generate" class="alert-link">l'écran de génération</a>.</div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Synthèse</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>Heures travaillées</span><strong class="tnum"><?= e(number_format((float) $payslip['hours_worked'], 2, ',', ' ')) ?> h</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Salaire brut</span><strong class="tnum"><?= money_format((float) $payslip['gross_salary']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Cotisations salariales</span><strong class="text-danger tnum">− <?= money_format((float) $payslip['total_employee']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Net imposable</span><strong class="tnum"><?= money_format((float) $payslip['net_taxable']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between list-group-item-light"><span>Net à payer</span><strong class="fs-6 tnum"><?= money_format((float) $payslip['net_payable']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between text-muted"><span>Charges patronales</span><span class="tnum"><?= money_format((float) $payslip['total_employer']) ?></span></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($payslip['status'] === 'draft' && (int) $payslip['is_locked'] === 0): ?>
                    <form method="post" action="/payroll/<?= (int) $payslip['id'] ?>/validate">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm w-100"><i data-lucide="circle-check"></i>Valider (génère le PDF)</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($payslip['status'], ['validated', 'locked'], true) && (int) $payslip['is_paid'] === 0): ?>
                    <form method="post" action="/payroll/<?= (int) $payslip['id'] ?>/pay">
                        <?= csrf_field() ?>
                        <button class="btn btn-success btn-sm w-100"><i data-lucide="coins"></i>Marquer payé</button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline-secondary btn-sm w-100" href="/payroll">Retour à la paie</a>
            </div>
        </div>
    </div>
</div>
</div>
