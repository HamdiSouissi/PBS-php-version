<?php declare(strict_types=1); $id = (int) $report['id']; ?>
<?= $viewRenderer->pageHeader('Note de frais archivée n°' . $id, [['label' => 'Archives', 'href' => '/expenses/archive']]) ?>
<div class="page-body"><div class="alert alert-info">Note supprimée le <?= e($report['deleted_at']) ?>. Ses lignes et justificatifs sont conservés pour l’historique.</div>
<div class="card"><div class="card-body"><h2 class="h5"><?= e($report['title']) ?></h2><p><?= e($report['first_name'] . ' ' . $report['last_name']) ?> · Total <?= money_format((float) $report['total_amount']) ?></p>
<?php if (!empty($report['notes'])): ?><p><?= nl2br(e($report['notes'])) ?></p><?php endif; ?>
<table class="table table-sm"><thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th>TTC</th><th>TVA</th></tr></thead><tbody>
<?php foreach ($lines as $line): ?><tr><td><?= e($line['spent_on']) ?></td><td><?= e($line['category']) ?></td><td><?= e($line['label']) ?></td><td><?= money_format((float) $line['amount']) ?></td><td><?= money_format((float) $line['vat_amount']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h3 class="h6">Justificatifs PDF</h3><?php if ($receipts === []): ?><p class="small text-muted">Aucun justificatif.</p><?php else: ?><ul><?php foreach ($receipts as $receipt): ?><li><a href="/expenses/archive/<?= $id ?>/receipts/<?= (int) $receipt['id'] ?>"><?= e($receipt['original_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($pdfDoc !== null): ?><p><a href="/pdf/<?= (int) $pdfDoc['id'] ?>/download">Télécharger le dernier PDF généré (version historique)</a></p><?php endif; ?>
</div></div></div>
