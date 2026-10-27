<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $periods
 * @var list<array<string,mixed>> $employees
 */
$actions = '<a href="/payroll" class="btn btn-outline-secondary btn-sm">Annuler</a>'
    . '<button form="payslip-form" class="btn btn-primary btn-sm" type="submit"><i data-lucide="calculator"></i>Calculer le bulletin</button>';
?>
<?= $viewRenderer->pageHeader(
    'Générer un bulletin de paie',
    [['label' => 'Paie', 'href' => '/payroll']],
    $periods === [] || $employees === [] ? '<a href="/payroll" class="btn btn-outline-secondary btn-sm">Retour à la paie</a>' : $actions,
    'Le calcul reprend le contrat actif et les règles de cotisations applicables à la période.'
) ?>

<?php if ($periods === [] || $employees === []): ?>
    <div class="page-body">
    <div class="alert alert-warning">
        <?php if ($periods === []): ?>Aucune période ouverte : créez d'abord une période de paie.<?php endif; ?>
        <?php if ($employees === []): ?>Aucun salarié avec un contrat actif.<?php endif; ?>
    </div>
    </div>
<?php else: ?>
<div class="page-body form-narrow">
<div class="card">
    <div class="card-body">
        <form method="post" action="/payroll/generate" class="row g-3" id="payslip-form" data-guard>
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Période *</label>
                <select class="form-select form-select-sm" name="period_id" required>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= (int) $period['id'] ?>">
                            <?= sprintf('%02d/%d', (int) $period['month'], (int) $period['year']) ?>
                            (<?= e(date('d/m/Y', strtotime((string) $period['starts_on']))) ?> au
                            <?= e(date('d/m/Y', strtotime((string) $period['ends_on']))) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Salarié *</label>
                <select class="form-select form-select-sm" name="employee_id" required>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int) $employee['id'] ?>">
                            <?= e($employee['employee_number'] . ' — ' . mb_strtoupper((string) $employee['last_name']) . ' ' . $employee['first_name']) ?>
                            (brut contrat <?= money_format((float) $employee['monthly_gross']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Prime brute éventuelle (€)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bonus" value="0">
            </div>
        </form>
    </div>
</div>
</div>
<?php endif; ?>
