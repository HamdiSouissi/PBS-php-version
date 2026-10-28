<?php

declare(strict_types=1);
/**
 * @var array<string,mixed> $employee
 * @var list<array<string,mixed>> $balances
 * @var list<array<string,mixed>> $requests
 * @var list<array<string,mixed>> $leaveTypes
 * @var list<array<string,mixed>> $payslips
 */
?>
<?= $viewRenderer->pageHeader(
    'Mon portail — ' . $employee['first_name'] . ' ' . $employee['last_name'],
    [['label' => 'Portail salarié']]
) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Mes soldes de congés (<?= (int) date('Y') ?>)</div>
            <ul class="list-group list-group-flush small">
                <?php if ($balances === []): ?><li class="list-group-item text-muted">Soldes non initialisés.</li><?php endif; ?>
                <?php foreach ($balances as $balance): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($balance['label']) ?></span>
                        <strong><?= e((string) $balance['remaining_days']) ?> j</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Nouvelle demande</div>
            <div class="card-body">
                <form method="post" action="/portal/leave" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label small">Type de congé</label>
                        <select name="leave_type_id" class="form-select form-select-sm" required>
                            <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?= (int) $type['id'] ?>"><?= e($type['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label small">Début</label><input type="date" class="form-control form-control-sm" name="starts_on" required></div>
                    <div class="col-6"><label class="form-label small">Fin</label><input type="date" class="form-control form-control-sm" name="ends_on" required></div>
                    <div class="col-12"><label class="form-label small">Motif</label><textarea name="reason" class="form-control form-control-sm" rows="2"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary btn-sm w-100">Envoyer la demande</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Mes demandes</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th>Période</th><th>Jours</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($requests === []): ?><tr><td colspan="5" class="text-center text-muted py-3">Aucune demande.</td></tr><?php endif; ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= e($request['type_label']) ?></td>
                            <td class="small"><?= e(date('d/m/Y', strtotime((string) $request['starts_on']))) ?></td>
                            <td><?= e((string) $request['days_count']) ?></td>
                            <td><span class="badge text-bg-info"><?= e($request['status']) ?></span></td>
                            <td>
                                <?php if (in_array($request['status'], ['draft', 'submitted'], true)): ?>
                                    <form method="post" action="/portal/leave/<?= (int) $request['id'] ?>/cancel">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-link text-danger p-0">Annuler</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Mes bulletins de paie</div>
            <ul class="list-group list-group-flush small">
                <?php if ($payslips === []): ?><li class="list-group-item text-muted">Aucun bulletin disponible.</li><?php endif; ?>
                <?php foreach ($payslips as $payslip): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Bulletin <?= (int) $payslip['month'] ?>/<?= (int) $payslip['year'] ?></span>
                        <span class="badge text-bg-<?= $payslip['status'] === 'validated' ? 'success' : 'secondary' ?>"><?= e($payslip['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Mes documents</span>
                <a class="btn btn-sm btn-outline-primary" href="/documents">Tout voir</a>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if ($documents === []): ?><li class="list-group-item text-muted">Aucun document disponible.</li><?php endif; ?>
                <?php foreach ($documents as $document): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i data-lucide="file-text" class="text-danger me-1"></i><?= e($document['name']) ?></span>
                        <span class="d-flex gap-1">
                            <?php if (($document['mime'] ?? '') === 'application/pdf'): ?>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank"
                                   href="/pdf/<?= (int) $document['id'] ?>/preview">Aperçu</a>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/pdf/<?= (int) $document['id'] ?>/download">Télécharger</a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/documents/download/<?= (int) $document['id'] ?>">Télécharger</a>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
</div>
