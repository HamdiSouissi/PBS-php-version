<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $obligations
 * @var list<array<string,mixed>> $backups
 * @var list<string> $authorities
 * @var array<string,string> $frequencies
 */
$statusBadges = ['pending' => 'warning', 'done' => 'success', 'overdue' => 'danger', 'not_applicable' => 'secondary'];
$statusLabels = ['pending' => 'À traiter', 'done' => 'Traitée', 'overdue' => 'En retard', 'not_applicable' => 'Non applicable'];
$authorityLabels = ['URSSAF' => 'URSSAF', 'DGFIP' => 'DGFIP', 'INSEE' => 'INSEE', 'greffe' => 'Greffe', 'medecine_travail' => 'Médecine du travail', 'interne' => 'Interne'];
$actions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#oblModal">'
    . '<i data-lucide="plus"></i>Nouvelle obligation</button>';
?>
<?= $viewRenderer->pageHeader(
    'Conformité',
    [],
    $actions,
    'Obligations légales, échéances et sauvegardes.'
) ?>

<div class="page-body">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Obligations</span>
        <span class="badge text-bg-light rounded-pill"><?= count($obligations) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light"><tr><th>Obligation</th><th>Organisme</th><th>Fréquence</th><th>Échéance</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php if ($obligations === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucune obligation.</td></tr><?php endif; ?>
            <?php foreach ($obligations as $obligation): ?>
                <tr>
                    <td><?= e($obligation['title']) ?></td>
                    <td><span class="badge text-bg-light border"><?= e($authorityLabels[$obligation['authority']] ?? $obligation['authority']) ?></span></td>
                    <td><?= e($frequencies[$obligation['frequency']] ?? $obligation['frequency']) ?></td>
                    <td><?= e($obligation['due_on'] ? date('d/m/Y', strtotime((string) $obligation['due_on'])) : 'périodique') ?></td>
                    <td><span class="badge text-bg-<?= e($statusBadges[$obligation['status']] ?? 'secondary') ?>"><?= e($statusLabels[$obligation['status']] ?? $obligation['status']) ?></span></td>
                    <td>
                        <?php if ($obligation['status'] !== 'done'): ?>
                            <form method="post" action="/compliance/obligations/<?= (int) $obligation['id'] ?>/complete">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-success"><i data-lucide="check"></i>Traiter</button>
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
    <div class="card-header">Sauvegardes locales <span class="text-muted small">(commande <code>php bin/console backup:run</code>)</span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Fichier</th><th>Taille</th><th>Créée le</th></tr></thead>
            <tbody>
            <?php if ($backups === []): ?><tr><td colspan="3" class="text-center text-muted py-3">Aucune sauvegarde.</td></tr><?php endif; ?>
            <?php foreach ($backups as $backup): ?>
                <tr><td><i data-lucide="database-backup"></i><?= e($backup['file']) ?></td><td><?= e($backup['size']) ?></td><td><?= e($backup['created_at']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal fade" id="oblModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="oblModalTitle">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/compliance/obligations">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="oblModalTitle">Nouvelle obligation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-12"><label class="form-label small">Intitulé *</label><input class="form-control form-control-sm" name="title" required></div>
                <div class="col-6">
                    <label class="form-label small">Organisme *</label>
                    <select class="form-select form-select-sm" name="authority" required>
                        <?php foreach ($authorities as $authority): ?><option value="<?= e($authority) ?>"><?= e($authorityLabels[$authority]) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small">Fréquence *</label>
                    <select class="form-select form-select-sm" name="frequency" required>
                        <?php foreach ($frequencies as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6"><label class="form-label small">Échéance</label><input type="date" class="form-control form-control-sm" name="due_on"></div>
                <div class="col-12"><label class="form-label small">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
        </form>
    </div>
</div>
