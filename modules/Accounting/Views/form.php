<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $journals
 * @var list<array<string,mixed>> $fiscalYears
 * @var list<array<string,mixed>> $accounts
 */
$headerActions = '';
if ($fiscalYears !== []) {
    $headerActions = '<a href="/accounting" class="btn btn-outline-secondary btn-sm">Annuler</a>'
        . '<button form="entry-form" class="btn btn-primary btn-sm" id="submit-btn" disabled type="submit">'
        . '<i data-lucide="circle-check"></i>Comptabiliser l\'écriture</button>';
}
?>
<?= $viewRenderer->pageHeader(
    'Nouvelle écriture comptable',
    [['label' => 'Comptabilité', 'href' => '/accounting']],
    $headerActions,
    'Saisie en partie double : les totaux débit et crédit doivent être strictement égaux.'
) ?>

<?php if ($fiscalYears === []): ?>
    <div class="alert alert-warning">Aucun exercice ouvert : la saisie est impossible tant qu'un exercice n'est pas créé.</div>
<?php else: ?>
<div class="page-body">
<form method="post" action="/accounting/entries" id="entry-form">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-header">En-tête</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Journal *</label>
                <select class="form-select form-select-sm" name="journal_id" required>
                    <?php foreach ($journals as $journal): ?>
                        <option value="<?= (int) $journal['id'] ?>"><?= e($journal['code'] . ' — ' . $journal['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Exercice *</label>
                <select class="form-select form-select-sm" name="fiscal_year_id" required>
                    <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?= (int) $fy['id'] ?>"><?= e($fy['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date *</label>
                <input type="date" class="form-control form-select-sm form-control-sm" name="entry_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Référence</label>
                <input class="form-control form-control-sm" name="reference" maxlength="60">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Libellé *</label>
                <input class="form-control form-control-sm" name="label" maxlength="255" required>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Lignes d'écriture</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i data-lucide="plus"></i>Ajouter une ligne</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr><th>Compte *</th><th>Libellé ligne</th><th>Tiers</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th></th></tr>
                </thead>
                <tbody id="lines-body">
                <tr class="line-row">
                    <td>
                        <select class="form-select form-select-sm" name="lines[account_code][]" required>
                            <option value="">—</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= e($account['code']) ?>"><?= e($account['code'] . ' — ' . $account['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="form-control form-control-sm" name="lines[label][]"></td>
                    <td><input class="form-control form-control-sm" name="lines[third_party][]"></td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end debit" name="lines[debit][]" value="0"></td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end credit" name="lines[credit][]" value="0"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" aria-label="Supprimer la ligne"><i data-lucide="trash-2"></i></button></td>
                </tr>
                </tbody>
                <tfoot>
                <tr class="table-light fw-semibold">
                    <td colspan="3">Totaux</td>
                    <td class="text-end" id="total-debit">0,00 €</td>
                    <td class="text-end" id="total-credit">0,00 €</td>
                    <td><span class="badge text-bg-danger" id="balance-badge">Déséquilibré</span></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

</form>
</div>

<template id="line-template">
    <tr class="line-row">
        <td>
            <select class="form-select form-select-sm" name="lines[account_code][]" required>
                <option value="">—</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?= e($account['code']) ?>"><?= e($account['code'] . ' — ' . $account['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input class="form-control form-control-sm" name="lines[label][]"></td>
        <td><input class="form-control form-control-sm" name="lines[third_party][]"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end debit" name="lines[debit][]" value="0"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end credit" name="lines[credit][]" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" aria-label="Supprimer la ligne"><i data-lucide="trash-2"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('lines-body');
    const template = document.getElementById('line-template');
    const badge = document.getElementById('balance-badge');
    const submitBtn = document.getElementById('submit-btn');
    const eur = (n) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);

    function recalc() {
        let debit = 0, credit = 0;
        body.querySelectorAll('.line-row').forEach((row) => {
            debit += parseFloat(row.querySelector('.debit').value) || 0;
            credit += parseFloat(row.querySelector('.credit').value) || 0;
        });
        document.getElementById('total-debit').textContent = eur(debit);
        document.getElementById('total-credit').textContent = eur(credit);
        const balanced = Math.abs(debit - credit) < 0.001 && debit > 0;
        badge.className = 'badge text-bg-' + (balanced ? 'success' : 'danger');
        badge.textContent = balanced ? 'Équilibré' : 'Déséquilibré';
        submitBtn.disabled = !balanced;
    }

    document.getElementById('add-line').addEventListener('click', () => {
        body.appendChild(template.content.cloneNode(true));
        recalc();
    });
    body.addEventListener('click', (event) => {
        const btn = event.target.closest('.remove-line');
        if (btn && body.querySelectorAll('.line-row').length > 1) {
            btn.closest('.line-row').remove();
            recalc();
        }
    });
    body.addEventListener('input', recalc);
    recalc();
})();
</script>
<?php endif; ?>
