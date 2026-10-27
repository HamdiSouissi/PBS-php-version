<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $accounts
 * @var list<array<string,mixed>> $transactions
 */
$actions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#txModal">'
    . '<i data-lucide="plus"></i>Nouveau mouvement</button>';
?>
<?= $viewRenderer->pageHeader('Trésorerie', [], $actions, 'Comptes bancaires, mouvements et rapprochements.') ?>

<div class="page-body">
<div class="kpi-grid">
    <?php foreach ($accounts as $i => $account): ?>
        <article class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon is-success"><i data-lucide="landmark"></i></span>
            </div>
            <div class="kpi-value tnum"><?= money_format((float) $account['current_balance']) ?></div>
            <div class="kpi-label"><?= e($account['label']) ?></div>
            <div class="small text-muted mt-1 text-truncate"><?= e($account['bank_name']) ?> · <?= e($account['iban']) ?></div>
        </article>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">50 derniers mouvements</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light">
            <tr><th>Date</th><th>Compte</th><th>Libellé</th><th>Sens</th><th class="text-end">Montant</th><th>Rapproché</th></tr>
            </thead>
            <tbody>
            <?php if ($transactions === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucun mouvement.</td></tr>
            <?php endif; ?>
            <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $tx['transaction_date']))) ?></td>
                    <td class="small"><?= e($tx['account']) ?></td>
                    <td><?= e($tx['label']) ?>
                        <?php if (($tx['reference'] ?? '') !== ''): ?><span class="text-muted small"> · <?= e($tx['reference']) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($tx['direction'] === 'credit'): ?>
                            <span class="badge text-bg-success">Crédit</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Débit</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold <?= $tx['direction'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                        <?= $tx['direction'] === 'credit' ? '+' : '−' ?><?= money_format((float) $tx['amount']) ?>
                    </td>
                    <td>
                        <form method="post" action="/treasury/transactions/<?= (int) $tx['id'] ?>/reconcile">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm <?= (int) $tx['is_reconciled'] === 1 ? 'btn-success' : 'btn-outline-secondary' ?>">
                                <i data-lucide="circle-check"></i><?= (int) $tx['is_reconciled'] === 1 ? 'Rapproché' : 'Rapprocher' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal fade" id="txModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="txModalTitle">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/treasury/transactions">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="txModalTitle">Nouveau mouvement bancaire</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label small">Compte *</label>
                    <select class="form-select form-select-sm" name="bank_account_id" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"><?= e($account['label']) ?> — <?= e($account['bank_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small">Date *</label>
                    <input type="date" class="form-control form-control-sm" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label small">Sens *</label>
                    <select class="form-select form-select-sm" name="direction">
                        <option value="credit">Crédit (entrée)</option>
                        <option value="debit">Débit (sortie)</option>
                    </select>
                </div>
                <div class="col-12"><label class="form-label small">Libellé *</label><input class="form-control form-control-sm" name="label" required></div>
                <div class="col-7"><label class="form-label small">Montant *</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" required></div>
                <div class="col-5"><label class="form-label small">Référence</label><input class="form-control form-control-sm" name="reference"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
        </form>
    </div>
</div>
