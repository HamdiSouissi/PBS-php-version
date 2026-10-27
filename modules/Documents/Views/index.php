<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $documents
 * @var bool $canWrite
 * @var int $maxSizeMb
 * @var list<string> $extensions
 */
$categories = ['autre' => 'Autre', 'rh' => 'RH', 'paie' => 'Paie', 'facture' => 'Facture', 'contrat' => 'Contrat', 'juridique' => 'Juridique', 'comptable' => 'Comptable'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Documents</h2>
</div>

<?php if ($canWrite): ?>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Déposer un document</div>
    <div class="card-body">
        <form method="post" action="/documents/upload" enctype="multipart/form-data" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-4"><input type="file" name="file" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Nom affiché (facultatif)" maxlength="255"></div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <?php foreach ($categories as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Envoyer</button></div>
            <div class="col-12 text-muted small">Max <?= (int) $maxSizeMb ?> Mo — formats : <?= e(implode(', ', $extensions)) ?></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>Nom</th><th>Catégorie</th><th>Taille</th><th>Déposé par</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($documents === []): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucun document.</td></tr><?php endif; ?>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td class="fw-semibold"><?= e($document['name']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($document['category']) ?></span></td>
                    <td class="small"><?= e(number_format(((float) $document['size']) / 1024, 1, ',', ' ')) ?> Ko</td>
                    <td class="small"><?= e($document['uploader'] ?? 'système') ?></td>
                    <td class="small"><?= e(date('d/m/Y H:i', strtotime((string) $document['created_at']))) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="/documents/download/<?= (int) $document['id'] ?>"><i class="bi bi-download"></i></a>
                        <?php if ($canWrite): ?>
                            <form method="post" action="/documents/<?= (int) $document['id'] ?>/delete" class="d-inline" data-confirm="Supprimer ce document ?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
