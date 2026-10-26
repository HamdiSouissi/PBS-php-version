<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $clients
 * @var list<array<string,mixed>> $bankAccounts
 * @var list<float> $vatRates
 */
?>
<h2 class="h4 mb-3">Nouvelle facture</h2>
<form method="post" action="/invoicing/invoices" id="invoice-form">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label">Client <span class="text-danger">*</span></label>
                <select name="third_party_id" class="form-select" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>"><?= e($client['name']) ?> (<?= e($client['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Date d'émission</label>
                <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Date d'échéance</label>
                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required></div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            Lignes de facturation
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Ligne</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="lines-table">
                <thead class="table-light"><tr><th>Libellé</th><th style="width:110px">Qté</th><th style="width:140px">Prix unit. HT</th><th style="width:120px">TVA %</th><th style="width:40px"></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <div class="card mb-3"><div class="card-body">
        <label class="form-label">Notes / conditions</label>
        <textarea name="notes" class="form-control" rows="2"></textarea>
    </div></div>
    <button class="btn btn-primary">Enregistrer le brouillon</button>
    <a href="/invoicing/invoices" class="btn btn-link">Annuler</a>
</form>
<template id="line-template">
    <tr>
        <td><input name="lines[label][]" class="form-control form-control-sm" required></td>
        <td><input name="lines[quantity][]" type="number" step="0.001" min="0.001" class="form-control form-control-sm" value="1" required></td>
        <td><input name="lines[unit_price][]" type="number" step="0.0001" min="0" class="form-control form-control-sm" value="0" required></td>
        <td><select name="lines[vat_rate][]" class="form-select form-select-sm">
            <?php foreach ($vatRates as $rate): ?><option value="<?= e((string) $rate) ?>" <?= $rate === 20.0 ? 'selected' : '' ?>><?= e((string) $rate) ?> %</option><?php endforeach; ?>
        </select></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-line">✕</button></td>
    </tr>
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#lines-table tbody');
    const tpl = document.querySelector('#line-template');
    const addLine = () => tbody.appendChild(tpl.content.cloneNode(true));
    document.querySelector('#add-line').addEventListener('click', addLine);
    tbody.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-line')) e.target.closest('tr').remove();
    });
    addLine();
});
</script>
