<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $periods
 * @var list<array<string,mixed>> $payslips
 * @var list<array<string,mixed>> $rules
 */
$statusBadges = ['draft' => 'secondary', 'validated' => 'info', 'locked' => 'success'];
$statusLabels = ['draft' => 'Brouillon', 'validated' => 'Validé', 'locked' => 'Verrouillé'];
$actions = '<button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#periodModal">'
    . '<i data-lucide="calendar-plus"></i>Nouvelle période</button>'
    . '<a class="btn btn-primary btn-sm" href="/payroll/generate"><i data-lucide="calculator"></i>Générer un bulletin</a>';
?>
<?= $viewRenderer->pageHeader(
    'Paie',
    [],
    $actions,
    'Périodes, bulletins et règles de cotisations versionnées.'
) ?>

<div class="page-body">
<div class="card">
    <div class="card-header">Périodes de paie</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light"><tr><th>Période</th><th>Début</th><th>Fin</th><th>Statut</th><th class="text-end">Bulletins</th><th></th></tr></thead>
            <tbody>
            <?php if ($periods === []): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucune période.</td></tr><?php endif; ?>
            <?php foreach ($periods as $period): ?>
                <tr>
                    <td class="fw-semibold"><?= sprintf('%02d/%d', (int) $period['month'], (int) $period['year']) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $period['starts_on']))) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $period['ends_on']))) ?></td>
                    <td>
                        <span class="badge text-bg-<?= (int) $period['is_locked'] === 1 ? 'success' : 'warning' ?>">
                            <?= (int) $period['is_locked'] === 1 ? 'Verrouillée' : 'Ouverte' ?>
                        </span>
                        <?php if ((int) $period['draft_count'] > 0): ?>
                            <span class="badge text-bg-secondary"><?= (int) $period['draft_count'] ?> brouillon(s)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= (int) $period['payslip_count'] ?></td>
                    <td class="text-end">
                        <?php if ((int) $period['is_locked'] === 0): ?>
                            <form method="post" action="/payroll/periods/<?= (int) $period['id'] ?>/lock">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-primary"><i data-lucide="lock"></i>Verrouiller</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Bulletins</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light"><tr><th>Période</th><th>Matricule</th><th>Salarié</th><th>Statut</th><th class="text-end">Brut</th><th class="text-end">Salarial</th><th class="text-end">Patronal</th><th class="text-end">Net à payer</th><th></th></tr></thead>
            <tbody>
            <?php if ($payslips === []): ?><tr><td colspan="9" class="text-center text-muted py-3">Aucun bulletin.</td></tr><?php endif; ?>
            <?php foreach ($payslips as $payslip): ?>
                <tr>
                    <td><?= sprintf('%02d/%d', (int) $payslip['month'], (int) $payslip['year']) ?></td>
                    <td><?= e($payslip['employee_number']) ?></td>
                    <td><a href="/payroll/<?= (int) $payslip['id'] ?>"><?= e(mb_strtoupper((string) $payslip['last_name']) . ' ' . $payslip['first_name']) ?></a></td>
                    <td><span class="badge text-bg-<?= e($statusBadges[$payslip['status']] ?? 'secondary') ?>"><?= e($statusLabels[$payslip['status']] ?? $payslip['status']) ?></span></td>
                    <td class="text-end"><?= money_format((float) $payslip['gross_salary']) ?></td>
                    <td class="text-end"><?= money_format((float) $payslip['total_employee']) ?></td>
                    <td class="text-end"><?= money_format((float) $payslip['total_employer']) ?></td>
                    <td class="text-end fw-semibold"><?= money_format((float) $payslip['net_payable']) ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="/payroll/<?= (int) $payslip['id'] ?>">Ouvrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Règles de cotisations <span class="text-muted small">(taux à valider par un spécialiste paie)</span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0" data-sortable>
            <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th class="text-end">Salarial %</th><th class="text-end">Patronal %</th><th class="text-end">Plafond</th><th>Effet</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td class="fw-semibold"><?= e($rule['code']) ?></td>
                    <td class="small"><?= e($rule['label']) ?></td>
                    <td class="text-end"><?= e((string) $rule['rate_employee']) ?></td>
                    <td class="text-end"><?= e((string) $rule['rate_employer']) ?></td>
                    <td class="text-end"><?= $rule['ceiling_month'] !== null ? money_format((float) $rule['ceiling_month']) : '—' ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $rule['effective_from']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal fade" id="periodModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="periodModalTitle">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/payroll/periods">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="periodModalTitle">Nouvelle période de paie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label small">Année *</label><input type="number" min="2020" max="2100" class="form-control form-control-sm" name="year" value="<?= date('Y') ?>" required></div>
                <div class="col-6"><label class="form-label small">Mois *</label><input type="number" min="1" max="12" class="form-control form-control-sm" name="month" value="<?= date('n') ?>" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Créer la période</button></div>
        </form>
    </div>
</div>
