<?php

declare(strict_types=1);
/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $clients
 * @var list<float> $vatRates
 */
$actions = '<a href="/invoicing/quotes" class="btn btn-outline-secondary btn-sm">Annuler</a>'
    . '<button form="quote-form" class="btn btn-primary btn-sm" type="submit"><i data-lucide="save"></i>Enregistrer le devis</button>';
?>
<?= $viewRenderer->pageHeader(
    'Nouveau devis',
    [['label' => 'Facturation'], ['label' => 'Devis', 'href' => '/invoicing/quotes']],
    $actions
) ?>

<div class="page-body">
<form method="post" action="/invoicing/quotes" id="quote-form" data-guard>
    <?= csrf_field() ?>
    <div class="card"><div class="card-body row g-3">
        <div class="col-md-5"><label class="form-label">Client <span class="text-danger">*</span></label>
            <select name="third_party_id" class="form-select" required>
                <option value="">— Choisir —</option>
                <?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-3"><label class="form-label">Validité</label><input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
    </div></div>
    <div class="card">
        <div class="card-header fw-semibold d-flex justify-content-between">Lignes
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i data-lucide="plus"></i> Ligne</button></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="lines-table">
                <thead class="table-light"><tr><th>Libellé</th><th class="col-qty">Qté</th><th class="col-price">Prix unit. HT</th><th class="col-vat">TVA %</th><th class="col-row-actions"></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <div class="card"><div class="card-body"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div></div>
</form>
</div>
<template id="line-template">
    <tr>
        <td><input name="lines[label][]" class="form-control form-control-sm" required></td>
        <td><input name="lines[quantity][]" type="number" step="0.001" min="0.001" class="form-control form-control-sm" value="1" required></td>
        <td><input name="lines[unit_price][]" type="number" step="0.0001" min="0" class="form-control form-control-sm" value="0" required></td>
        <td><select name="lines[vat_rate][]" class="form-select form-select-sm">
            <?php foreach ($vatRates as $rate): ?><option value="<?= e((string) $rate) ?>" <?= $rate === 20.0 ? 'selected' : '' ?>><?= e((string) $rate) ?> %</option><?php endforeach; ?>
        </select></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-line" aria-label="Supprimer la ligne"><i data-lucide="trash-2"></i></button></td>
    </tr>
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#lines-table tbody');
    const tpl = document.querySelector('#line-template');
    const addLine = () => tbody.appendChild(tpl.content.cloneNode(true));
    document.querySelector('#add-line').addEventListener('click', addLine);
    tbody.addEventListener('click', (e) => {
        if (e.target.closest('.remove-line')) e.target.closest('tr').remove();
    });
    addLine();
});
</script>
