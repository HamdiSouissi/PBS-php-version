<?php

declare(strict_types=1);
$id = (int) $report['id'];
$labels = ['transport' => 'Transport', 'meal' => 'Repas', 'accommodation' => 'Hébergement', 'fuel' => 'Carburant', 'kilometric' => 'Kilométrique', 'supplies' => 'Fournitures', 'other' => 'Autre'];
$actions = '<a class="btn btn-outline-secondary btn-sm" href="/expenses/' . $id . '">Annuler</a>'
    . '<button class="btn btn-primary btn-sm" type="submit" form="expense-edit-form">Enregistrer</button>';
?>
<?= $viewRenderer->pageHeader('Modifier la note de frais', [['label' => 'Notes de frais', 'href' => '/expenses'], ['label' => 'Note n°' . $id, 'href' => '/expenses/' . $id]], $actions) ?>
<div class="page-body"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Modifier le brouillon</span><button type="button" class="btn btn-outline-primary btn-sm" id="add-expense-line">Ajouter une ligne</button></div>
    <form method="post" id="expense-edit-form" action="/expenses/<?= $id ?>/update" data-guard>
        <?= csrf_field() ?>
        <div class="card-body">
            <p class="small text-muted">Les justificatifs restent associés à la note. Si vous changez de salarié, ils seront associés au nouveau salarié. Régénérez le PDF après modification.</p>
            <div class="row g-3 mb-3"><div class="col-md-6"><label for="expense-employee" class="form-label">Salarié *</label>
                <select id="expense-employee" class="form-select" name="employee_id" required>
                    <?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>" <?= (int) $employee['id'] === (int) $report['employee_id'] ? 'selected' : '' ?>><?= e($employee['employee_number'] . ' · ' . $employee['last_name'] . ' ' . $employee['first_name']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-md-6"><label for="expense-title" class="form-label">Intitulé *</label><input id="expense-title" class="form-control" name="title" maxlength="180" required value="<?= e($report['title']) ?>"></div>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th>Montant TTC</th><th>TVA</th><th>Km</th><th>Taux/km</th><th></th></tr></thead><tbody id="expense-edit-lines">
            <?php foreach ($lines as $i => $line): ?>
                <tr>
                    <td><input type="hidden" name="lines[<?= $i ?>][id]" value="<?= (int) $line['id'] ?>"><input class="form-control form-control-sm" aria-label="Date" type="date" name="lines[<?= $i ?>][spent_on]" value="<?= e($line['spent_on']) ?>" required></td>
                    <td><select class="form-select form-select-sm" aria-label="Catégorie" name="lines[<?= $i ?>][category]" required><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>" <?= $category === $line['category'] ? 'selected' : '' ?>><?= e($labels[$category] ?? $category) ?></option><?php endforeach; ?></select></td>
                    <td><input class="form-control form-control-sm" aria-label="Libellé" name="lines[<?= $i ?>][label]" value="<?= e($line['label']) ?>" maxlength="255" required></td>
                    <td><input class="form-control form-control-sm" aria-label="Montant TTC" name="lines[<?= $i ?>][amount]" type="number" min="0.01" step="0.01" value="<?= e($line['amount']) ?>" required></td>
                    <td><input class="form-control form-control-sm" aria-label="TVA" name="lines[<?= $i ?>][vat_amount]" type="number" min="0" step="0.01" value="<?= e($line['vat_amount']) ?>" required></td>
                    <td><input class="form-control form-control-sm" aria-label="Distance en kilomètres" name="lines[<?= $i ?>][distance_km]" type="number" min="0" step="0.01" value="<?= e($line['distance_km']) ?>" required></td>
                    <td><input class="form-control form-control-sm" aria-label="Taux par kilomètre" name="lines[<?= $i ?>][km_rate]" type="number" min="0" step="0.001" value="<?= e($line['km_rate']) ?>" required></td>
                    <td><button type="button" class="btn btn-link text-danger expense-remove-line" aria-label="Supprimer cette ligne">×</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <label for="expense-notes" class="form-label">Notes</label><textarea id="expense-notes" class="form-control" name="notes" maxlength="1000" rows="3"><?= e($report['notes'] ?? '') ?></textarea>
        </div>
    </form>
</div></div>
<template id="expense-edit-template"><tr>
    <td><input type="hidden" name="lines[__INDEX__][id]" value="0"><input type="date" class="form-control form-control-sm" aria-label="Date" name="lines[__INDEX__][spent_on]" value="<?= date('Y-m-d') ?>" required></td>
    <td><select class="form-select form-select-sm" aria-label="Catégorie" name="lines[__INDEX__][category]"><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?= e($labels[$category] ?? $category) ?></option><?php endforeach; ?></select></td>
    <td><input class="form-control form-control-sm" aria-label="Libellé" name="lines[__INDEX__][label]" maxlength="255" required></td>
    <td><input class="form-control form-control-sm" aria-label="Montant TTC" name="lines[__INDEX__][amount]" type="number" min="0.01" step="0.01" required></td>
    <td><input class="form-control form-control-sm" aria-label="TVA" name="lines[__INDEX__][vat_amount]" type="number" min="0" step="0.01" value="0" required></td>
    <td><input class="form-control form-control-sm" aria-label="Distance en kilomètres" name="lines[__INDEX__][distance_km]" type="number" min="0" step="0.01" value="0" required></td>
    <td><input class="form-control form-control-sm" aria-label="Taux par kilomètre" name="lines[__INDEX__][km_rate]" type="number" min="0" step="0.001" value="0" required></td>
    <td><button type="button" class="btn btn-link text-danger expense-remove-line" aria-label="Supprimer cette ligne">×</button></td>
</tr></template>
<script>
(function () {
    let index = <?= count($lines) ?>;
    const body = document.getElementById('expense-edit-lines');
    document.getElementById('add-expense-line').addEventListener('click', function () {
        const template = document.getElementById('expense-edit-template').innerHTML.replaceAll('__INDEX__', String(index++));
        body.insertAdjacentHTML('beforeend', template);
    });
    body.addEventListener('click', function (event) {
        const button = event.target.closest('.expense-remove-line');
        if (button) button.closest('tr').remove();
    });
})();
</script>
