<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $shareholders
 * @var list<array<string,mixed>> $dividends
 * @var list<array<string,mixed>> $fiscalYears
 */
$statusBadges = ['proposed' => 'warning', 'decided' => 'info', 'paid' => 'success'];
$statusLabels = ['proposed' => 'Proposé', 'decided' => 'Décidé', 'paid' => 'Payé'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Associés & dividendes</h2>
        <p class="text-muted mb-0">Table de capital et décisions de distribution.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#divModal"
        <?= $shareholders === [] ? 'disabled' : '' ?>>
        <i class="bi bi-plus-lg me-1"></i>Nouveau dividende
    </button>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Associés</div>
            <ul class="list-group list-group-flush small">
                <?php if ($shareholders === []): ?><li class="list-group-item text-muted">Aucun associé.</li><?php endif; ?>
                <?php foreach ($shareholders as $shareholder): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($shareholder['name']) ?> <span class="badge text-bg-light border ms-1"><?= e($shareholder['type']) ?></span></span>
                        <strong><?= e(number_format((float) $shareholder['share_pct'], 2, ',', ' ')) ?> %</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Dividendes</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Décidé le</th><th>Associé</th><th class="text-end">Brut</th><th class="text-end">Net</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($dividends === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun dividende.</td></tr><?php endif; ?>
                    <?php foreach ($dividends as $dividend): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime((string) $dividend['decided_on']))) ?></td>
                            <td><?= e($dividend['name']) ?></td>
                            <td class="text-end"><?= money_format((float) $dividend['gross_amount']) ?></td>
                            <td class="text-end"><?= money_format((float) $dividend['net_amount']) ?></td>
                            <td><span class="badge text-bg-<?= e($statusBadges[$dividend['status']] ?? 'secondary') ?>"><?= e($statusLabels[$dividend['status']] ?? $dividend['status']) ?></span></td>
                            <td class="text-end">
                                <?php if ($dividend['status'] === 'proposed'): ?>
                                    <form method="post" action="/dividends/<?= (int) $dividend['id'] ?>/decide" class="d-inline">
                                        <?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Décider</button>
                                    </form>
                                <?php elseif ($dividend['status'] === 'decided'): ?>
                                    <form method="post" action="/dividends/<?= (int) $dividend['id'] ?>/pay" class="d-inline">
                                        <?= csrf_field() ?><button class="btn btn-sm btn-success">Payer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="divModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/dividends">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Nouveau projet de dividende</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label small">Associé *</label>
                    <select class="form-select form-select-sm" name="shareholder_id" required>
                        <?php foreach ($shareholders as $shareholder): ?>
                            <option value="<?= (int) $shareholder['id'] ?>"><?= e($shareholder['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small">Exercice</label>
                    <select class="form-select form-select-sm" name="fiscal_year_id">
                        <option value="">—</option>
                        <?php foreach ($fiscalYears as $fy): ?><option value="<?= (int) $fy['id'] ?>"><?= e($fy['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6"><label class="form-label small">Date *</label><input type="date" class="form-control form-control-sm" name="decided_on" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-6"><label class="form-label small">Montant brut *</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="gross_amount" required></div>
                <div class="col-6"><label class="form-label small">Taux prélèvements sociaux %</label><input type="number" step="0.01" class="form-control form-control-sm" name="social_rate" value="17.2"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
        </form>
    </div>
</div>
