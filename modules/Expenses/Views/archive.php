<?php declare(strict_types=1); ?>
<?= $viewRenderer->pageHeader('Notes de frais archivées', [['label' => 'Notes de frais', 'href' => '/expenses']]) ?>
<div class="page-body"><div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>N°</th><th>Salarié</th><th>Intitulé</th><th>Montant</th><th>Supprimée le</th></tr></thead><tbody>
    <?php if ($reports === []): ?><tr><td colspan="5" class="text-center text-muted">Aucune note archivée.</td></tr><?php endif; ?>
    <?php foreach ($reports as $report): ?><tr><td><a href="/expenses/archive/<?= (int) $report['id'] ?>">NF-<?= (int) $report['id'] ?></a></td><td><?= e($report['last_name'] . ' ' . $report['first_name']) ?></td><td><?= e($report['title']) ?></td><td><?= money_format((float) $report['total_amount']) ?></td><td><?= e($report['deleted_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
