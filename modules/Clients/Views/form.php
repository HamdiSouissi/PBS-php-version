<?php

declare(strict_types=1);

/**
 * @var ?array<string,mixed> $client
 */
$isEdit = $client !== null;
$action = $isEdit ? '/clients/' . (int) $client['id'] : '/clients';
$value = static fn (string $key): string => e((string) ($client[$key] ?? ''));
?>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header"><?= $isEdit ? 'Modifier le client' : 'Nouveau client' ?></div>
            <form method="post" action="<?= e($action) ?>">
                <?= csrf_field() ?>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Raison sociale *</label>
                        <input class="form-control" name="name" required value="<?= $value('name') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Forme juridique</label>
                        <input class="form-control" name="legal_form" value="<?= $value('legal_form') ?>" placeholder="SAS, SARL…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SIREN</label>
                        <input class="form-control" name="siren" value="<?= $value('siren') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SIRET</label>
                        <input class="form-control" name="siret" value="<?= $value('siret') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">N° TVA intracommunautaire</label>
                        <input class="form-control" name="vat_number" value="<?= $value('vat_number') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adresse</label>
                        <input class="form-control" name="address" value="<?= $value('address') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Code postal</label>
                        <input class="form-control" name="postal_code" value="<?= $value('postal_code') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Ville</label>
                        <input class="form-control" name="city" value="<?= $value('city') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pays</label>
                        <input class="form-control" name="country" value="<?= $value('country') ?: 'France' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" value="<?= $value('email') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input class="form-control" name="phone" value="<?= $value('phone') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Échéance (jours)</label>
                        <input type="number" class="form-control" name="payment_terms_days"
                               value="<?= $value('payment_terms_days') ?: '30' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mode de règlement</label>
                        <select class="form-select" name="payment_method">
                            <?php foreach (['virement' => 'Virement', 'cheque' => 'Chèque', 'prelevement' => 'Prélèvement', 'carte' => 'Carte'] as $code => $label): ?>
                                <option value="<?= e($code) ?>" <?= ($client['payment_method'] ?? 'virement') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?= $value('notes') ?></textarea>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2 justify-content-end">
                    <a href="<?= $isEdit ? '/clients/' . (int) $client['id'] : '/clients' ?>" class="btn btn-outline-secondary btn-sm">Annuler</a>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
