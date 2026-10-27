<?php

declare(strict_types=1);
/** @var ?array<string,mixed> $company @var list<array<string,mixed>> $establishments @var list<array<string,mixed>> $departments */
if ($company === null) {
    echo '<div class="alert alert-warning">Aucune société configurée. Lancez les données de référence (db:seed).</div>';
    return;
}
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Société — informations légales</div>
            <div class="card-body">
                <form method="post" action="/organization/company/<?= (int) $company['id'] ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <?php
                    $fields = [
                        'legal_name' => 'Raison sociale', 'commercial_name' => 'Nom commercial',
                        'legal_form' => 'Forme juridique', 'siren' => 'SIREN (9 chiffres)', 'siret' => 'SIRET (14 chiffres)',
                        'vat_number' => 'N° TVA intracom.', 'address' => 'Adresse', 'postal_code' => 'Code postal',
                        'city' => 'Ville', 'phone' => 'Téléphone', 'email' => 'E-mail', 'iban' => 'IBAN', 'bic' => 'BIC',
                        'urssaf_number' => 'N° URSSAF', 'collective_agreement' => 'Convention collective',
                    ];
                    foreach ($fields as $name => $label): ?>
                        <div class="col-md-6">
                            <label class="form-label small"><?= e($label) ?></label>
                            <input class="form-control form-control-sm" name="<?= e($name) ?>"
                                   value="<?= e($company[$name] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 mt-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Établissements</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($establishments as $establishment): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($establishment['name']) ?>
                            <?php if (!empty($establishment['head_office']) && $establishment['head_office'] !== 'f'): ?>
                                <span class="badge text-bg-info">Siège</span><?php endif; ?>
                        </span>
                        <span class="small text-muted"><?= e($establishment['siret'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="post" action="/organization/establishments" class="row g-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                    <div class="col-7"><input class="form-control form-control-sm" name="name" placeholder="Nom" required></div>
                    <div class="col-5"><input class="form-control form-control-sm" name="siret" placeholder="SIRET"></div>
                    <div class="col-12 d-grid"><button class="btn btn-outline-primary btn-sm">Ajouter</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Services / départements</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($departments as $department): ?>
                    <li class="list-group-item"><?= e($department['name']) ?> <span class="text-muted small">(<?= e($department['code']) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="post" action="/organization/departments" class="row g-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                    <div class="col-4"><input class="form-control form-control-sm" name="code" placeholder="Code" required></div>
                    <div class="col-8"><input class="form-control form-control-sm" name="name" placeholder="Nom du service" required></div>
                    <div class="col-12 d-grid"><button class="btn btn-outline-primary btn-sm">Ajouter</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
