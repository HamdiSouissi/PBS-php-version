<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $requests
 * @var list<array<string,mixed>> $pending
 * @var string $status
 */
$badges = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'dark'];
?>
<?= $viewRenderer->pageHeader('Demandes de congés', [['label' => 'Ressources humaines']]) ?>

<div class="page-body">
<?php if ($pending !== []): ?>
<div class="card border-warning">
    <div class="card-header bg-warning-subtle fw-semibold">À valider (<?= count($pending) ?>)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0" data-sortable>
            <thead><tr><th>Salarié</th><th>Type</th><th>Période</th><th>Jours</th><th>Motif</th><th class="text-end">Décision</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $request): ?>
                <tr>
                    <td><?= e($request['last_name'] . ' ' . $request['first_name']) ?></td>
                    <td><?= e($request['type_label']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $request['starts_on']))) ?>
                        → <?= e(date('d/m/Y', strtotime((string) $request['ends_on']))) ?></td>
                    <td><?= e((string) $request['days_count']) ?></td>
                    <td class="small text-muted"><?= e($request['reason']) ?></td>
                    <td class="text-end">
                        <form method="post" action="/hr/leaves/<?= (int) $request['id'] ?>/approve" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-success">Approuver</button>
                        </form>
                        <form method="post" action="/hr/leaves/<?= (int) $request['id'] ?>/reject" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger">Rejeter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<form class="table-toolbar mb-0" method="get">
    <select class="form-select form-select-sm d-inline w-auto" name="status" onchange="this.form.submit()">
        <option value="">Tous statuts</option>
        <?php foreach (['submitted' => 'Soumises', 'approved' => 'Approuvées', 'rejected' => 'Rejetées', 'cancelled' => 'Annulées'] as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light"><tr><th>Salarié</th><th>Type</th><th>Période</th><th>Jours</th><th>Statut</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($requests === []): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucune demande.</td></tr><?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['last_name'] . ' ' . $request['first_name']) ?></td>
                    <td><?= e($request['type_label']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $request['starts_on']))) ?></td>
                    <td><?= e((string) $request['days_count']) ?></td>
                    <td><span class="badge text-bg-<?= e($badges[$request['status']] ?? 'secondary') ?>"><?= e($request['status']) ?></span></td>
                    <td class="small text-muted"><?= e(date('d/m/Y', strtotime((string) $request['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
