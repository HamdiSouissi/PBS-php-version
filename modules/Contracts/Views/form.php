<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $clients */
?>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">Nouveau contrat client</div>
            <form method="post" action="/contracts">
                <?= csrf_field() ?>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Client *</label>
                        <select class="form-select" name="third_party_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"><?= e($client['code'] . ' · ' . $client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Intitulé *</label>
                        <input class="form-control" name="title" required placeholder="Maintenance applicative, forfait…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date d'effet *</label>
                        <input type="date" class="form-control" name="starts_on" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de fin</label>
                        <input type="date" class="form-control" name="ends_on">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de signature</label>
                        <input type="date" class="form-control" name="signed_on">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Montant HT *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount_ht" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cycle de facturation *</label>
                        <select class="form-select" name="billing_cycle">
                            <?php foreach (['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'] as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Renouvellement *</label>
                        <select class="form-select" name="renewal_mode">
                            <option value="tacit">Tacite</option>
                            <option value="express">Exprès (avenant)</option>
                            <option value="none">Aucun</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Préavis (jours) *</label>
                        <input type="number" min="0" class="form-control" name="notice_days" value="30" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut *</label>
                        <select class="form-select" name="status">
                            <option value="active">Actif</option>
                            <option value="draft">Brouillon</option>
                            <option value="terminated">Résilié</option>
                            <option value="expired">Expiré</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="/contracts" class="btn btn-outline-secondary btn-sm">Annuler</a>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Enregistrer et générer le PDF</button>
                </div>
            </form>
        </div>
    </div>
</div>
