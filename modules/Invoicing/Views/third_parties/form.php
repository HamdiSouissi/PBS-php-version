<?php

declare(strict_types=1);
/** @var ?array<string,mixed> $party */
?>
<h2 class="h4 mb-3">Nouveau tiers</h2>
<div class="card">
    <div class="card-body">
        <form method="post" action="/invoicing/third-parties" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                    <option value="client">Client</option>
                    <option value="supplier">Fournisseur</option>
                    <option value="both">Client et fournisseur</option>
                </select>
            </div>
            <div class="col-md-8"><label class="form-label">Raison sociale <span class="text-danger">*</span></label>
                <input name="name" class="form-control" required maxlength="180"></div>
            <div class="col-md-4"><label class="form-label">Forme juridique</label><input name="legal_form" class="form-control" placeholder="SAS, SARL…"></div>
            <div class="col-md-4"><label class="form-label">SIREN</label><input name="siren" class="form-control" maxlength="9"></div>
            <div class="col-md-4"><label class="form-label">SIRET</label><input name="siret" class="form-control" maxlength="14"></div>
            <div class="col-md-6"><label class="form-label">N° TVA intracommunautaire</label><input name="vat_number" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Adresse</label><input name="address" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Code postal</label><input name="postal_code" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Ville</label><input name="city" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Téléphone</label><input name="phone" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Échéance (jours)</label>
                <input type="number" name="payment_terms_days" class="form-control" value="30" min="0"></div>
            <div class="col-md-3"><label class="form-label">Mode de règlement</label>
                <select name="payment_method" class="form-select">
                    <option value="virement">Virement</option>
                    <option value="cheque">Chèque</option>
                    <option value="prelevement">Prélèvement SEPA</option>
                    <option value="carte">Carte bancaire</option>
                </select>
            </div>
            <div class="col-12"><button class="btn btn-primary">Enregistrer</button>
                <a href="/invoicing/third-parties" class="btn btn-link">Annuler</a></div>
        </form>
    </div>
</div>
