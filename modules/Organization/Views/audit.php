<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $logs
 * @var int $total @var int $page @var int $totalPages
 */
?>
<?= $viewRenderer->pageHeader(
    "Journal d'audit",
    [['label' => 'Gouvernance']],
    '',
    'Traçabilité des actions sensibles.'
) ?>
<div class="page-body">
<form class="table-toolbar d-flex gap-2" method="get">
    <input class="form-control form-control-sm" name="entity_type" placeholder="Entité (User, Invoice…)">
    <button class="btn btn-outline-secondary btn-sm"><i data-lucide="search"></i>Filtrer</button>
</form>
<div class="card">
    <div class="card-header fw-semibold">Événements enregistrés</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" data-sortable>
            <thead class="table-light">
            <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>ID</th><th>IP</th><th>Détails</th></tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">Aucune entrée pour le moment.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="small"><?= e(date('d/m/Y H:i:s', strtotime((string) $log['created_at']))) ?></td>
                    <td><?= e($log['email'] ?? 'système') ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($log['action']) ?></span></td>
                    <td><?= e($log['entity_type']) ?></td>
                    <td><?= e((string) ($log['entity_id'] ?? '—')) ?></td>
                    <td class="small"><?= e($log['ip_address']) ?></td>
                    <td class="small text-muted"><?= e(mb_substr((string) $log['changes'], 0, 80)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><?= (int) $total ?> entrée(s) — page <?= (int) $page ?>/<?= (int) $totalPages ?></p>
</div>
