<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $employees
 * @var list<string> $categories
 */
$categoryLabels = ['transport' => 'Transport', 'meal' => 'Repas', 'accommodation' => 'Hébergement', 'fuel' => 'Carburant', 'kilometric' => 'Kilométrique', 'supplies' => 'Fournitures', 'other' => 'Autre'];
$actions = '<a href="/expenses" class="btn btn-outline-secondary btn-sm">Annuler</a>'
    . '<button form="expense-form" class="btn btn-primary btn-sm" type="submit"><i data-lucide="save"></i>Enregistrer le brouillon</button>';
?>
<?= $viewRenderer->pageHeader('Nouvelle note de frais', [['label' => 'Notes de frais', 'href' => '/expenses']], $actions) ?>

<div class="page-body">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Saisie des lignes</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i data-lucide="plus"></i> Ligne</button>
        </div>
        <form method="post" enctype="multipart/form-data" action="/expenses" id="expense-form" data-guard>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Salarié *</label>
                        <select class="form-select form-select-sm" name="employee_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= (string) ($previous['employee_id'] ?? '') === (string) $employee['id'] ? 'selected' : '' ?>>
                                    <?= e($employee['employee_number'] . ' · ' . mb_strtoupper((string) $employee['last_name']) . ' ' . $employee['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Intitulé *</label>
                        <input class="form-control form-control-sm" name="title" value="<?= e(($previous['title'] ?? '')) ?>" maxlength="180" required placeholder="Déplacement client, séminaire…">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th class="text-end">Montant TTC</th><th class="text-end">TVA</th><th class="col-row-actions"></th></tr>
                        </thead>
                        <tbody id="lines"></tbody>
                    </table>
                </div>
                <label class="form-label mt-2" for="expense-receipt">Facture PDF (facultative)</label>
                <input id="expense-receipt" type="file" name="receipt" class="form-control form-control-sm" accept=".pdf,application/pdf">
                <small class="text-muted">PDF non vide, 10 Mo maximum. Limites du serveur : fichier <?= e((string) ini_get('upload_max_filesize')) ?>, requête <?= e((string) ini_get('post_max_size')) ?>. Vous pourrez ajouter d’autres justificatifs au brouillon.</small>
                <br><label class="form-label mt-2" for="expense-notes">Notes</label>
                <textarea id="expense-notes" class="form-control form-control-sm" name="notes" maxlength="1000" rows="2"><?= e(($previous['notes'] ?? '')) ?></textarea>
            </div>
        </form>
    </div>
</div>

<template id="line-tpl">
    <tr>
        <td class="col-date"><input type="date" name="lines[spent_on][]" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></td>
        <td class="col-category">
            <select name="lines[category][]" class="form-select form-select-sm">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>"><?= e($categoryLabels[$category] ?? $category) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input name="lines[label][]" class="form-control form-control-sm" required></td>
        <td class="col-amount"><input name="lines[amount][]" type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end" required></td>
        <td class="col-vat"><input name="lines[vat_amount][]" type="number" step="0.01" min="0" value="0" class="form-control form-control-sm text-end"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-line" aria-label="Supprimer la ligne"><i data-lucide="trash-2"></i></button></td>
    </tr>
</template>
<script>
const tbody = document.getElementById('lines');
const tpl = document.getElementById('line-tpl');
const addLine = () => tbody.appendChild(tpl.content.cloneNode(true));
document.getElementById('add-line').addEventListener('click', addLine);
tbody.addEventListener('click', (e) => {
    if (e.target.closest('.remove-line')) e.target.closest('tr').remove();
});
const previousLines = <?= json_encode(($previous['lines'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const labels = previousLines && Array.isArray(previousLines.label) ? previousLines.label : [];
if (labels.length === 0) addLine();
else labels.forEach((label, index) => {
    addLine();
    const row = tbody.lastElementChild;
    ['label', 'spent_on', 'category', 'amount', 'vat_amount'].forEach((field) => {
        const input = row.querySelector('[name="lines[' + field + '][]"]');
        const value = previousLines[field] && previousLines[field][index];
        if (input && value != null && typeof value !== 'object') input.value = String(value);
    });
});
</script>
