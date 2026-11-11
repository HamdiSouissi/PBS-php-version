<?php declare(strict_types=1); $id = (int) $report['id'];
$actions = '<button class="btn btn-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#permanentDeleteModal"><i data-lucide="trash-2"></i>Supprimer définitivement</button>'; ?>
<?= $viewRenderer->pageHeader('Note de frais archivée n°' . $id, [['label' => 'Archives', 'href' => '/expenses/archive']], $actions) ?>
<div class="page-body"><div class="alert alert-info">Note supprimée le <?= e($report['deleted_at']) ?>. Ses lignes et justificatifs sont conservés pour l’historique.</div>
<div class="card"><div class="card-body"><h2 class="h5"><?= e($report['title']) ?></h2><p><?= e($report['first_name'] . ' ' . $report['last_name']) ?> · Total <?= money_format((float) $report['total_amount']) ?></p>
<?php if (!empty($report['notes'])): ?><p><?= nl2br(e($report['notes'])) ?></p><?php endif; ?>
<table class="table table-sm"><thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th>TTC</th><th>TVA</th></tr></thead><tbody>
<?php foreach ($lines as $line): ?><tr><td><?= e($line['spent_on']) ?></td><td><?= e($line['category']) ?></td><td><?= e($line['label']) ?></td><td><?= money_format((float) $line['amount']) ?></td><td><?= money_format((float) $line['vat_amount']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h3 class="h6">Justificatifs PDF</h3><?php if ($receipts === []): ?><p class="small text-muted">Aucun justificatif.</p><?php else: ?><ul><?php foreach ($receipts as $receipt): ?><li><a href="/expenses/archive/<?= $id ?>/receipts/<?= (int) $receipt['id'] ?>"><?= e($receipt['original_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($pdfDoc !== null): ?><p><a href="/pdf/<?= (int) $pdfDoc['id'] ?>/download">Télécharger le dernier PDF généré (version historique)</a></p><?php endif; ?>
</div></div></div>

<div class="modal fade" id="permanentDeleteModal" tabindex="-1" aria-labelledby="permanentDeleteTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="post" action="/expenses/archive/<?= $id ?>/permanent-delete">
        <?= csrf_field() ?>
        <div class="modal-header"><h2 class="modal-title fs-5" id="permanentDeleteTitle">Suppression définitive</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><p class="text-danger fw-semibold">Action irréversible.</p><p>La note, ses lignes, ses justificatifs et ses PDF générés seront supprimés définitivement. Cette action ne supprime pas les copies présentes dans les sauvegardes.</p>
            <label class="form-label" for="delete-confirmation">Saisissez SUPPRIMER pour confirmer</label>
            <input id="delete-confirmation" name="confirmation" class="form-control" autocomplete="off" required pattern="SUPPRIMER">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-danger">Supprimer définitivement</button></div>
    </form></div>
</div>
