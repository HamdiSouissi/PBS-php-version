<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $accounts
 * @var list<array<string,mixed>> $transactions
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Trésorerie</h2>
        <p class="text-muted mb-0">Comptes bancaires, mouvements et rapprochements.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#txModal">
        <i class="bi bi-plus-lg me-1"></i>Nouveau mouvement
    </button>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($accounts as $account): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label"><?= e($account['label']) ?></div>
                            <div class="stat-value"><?= money_format((float) $account['current_balance']) ?></div>
                            <div class="text-muted small mt-1"><?= e($account['bank_name']) ?> · <?= e($account['iban']) ?></div>
                        </div>
                        <div class="stat-icon stat-success"><i class="bi bi-bank"></i></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">50 derniers mouvements</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
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
                                <i class="bi bi-check2-circle me-1"></i><?= (int) $tx['is_reconciled'] === 1 ? 'Rapproché' : 'Rapprocher' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="txModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/treasury/transactions">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Nouveau mouvement bancaire</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
