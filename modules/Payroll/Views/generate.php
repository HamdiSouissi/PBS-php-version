<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $periods
 * @var list<array<string,mixed>> $employees
 */
?>
<div class="mb-3">
    <h2 class="h4 mb-1">Générer un bulletin de paie</h2>
    <p class="text-muted mb-0">Le calcul reprend le contrat actif et les règles de cotisations applicables à la période.</p>
</div>

<?php if ($periods === [] || $employees === []): ?>
    <div class="alert alert-warning">
        <?php if ($periods === []): ?>Aucune période ouverte : créez d'abord une période de paie.<?php endif; ?>
        <?php if ($employees === []): ?>Aucun salarié avec un contrat actif.<?php endif; ?>
        <a href="/payroll" class="alert-link">Retour à la paie</a>
    </div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="/payroll/generate" class="row g-3">
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
            <div class="col-12">
                <button class="btn btn-primary btn-sm"><i class="bi bi-calculator me-1"></i>Calculer le bulletin</button>
                <a href="/payroll" class="btn btn-outline-secondary btn-sm">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
