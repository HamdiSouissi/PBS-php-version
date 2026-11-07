<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $clients
 */
$formData = $formData ?? [];
$actions = '<a href="/contracts" class="btn btn-outline-secondary btn-sm">Annuler</a>'
    . '<button form="contract-form" class="btn btn-primary btn-sm" type="submit">'
    . '<i data-lucide="save"></i>Enregistrer et générer le PDF</button>';
?>
<?= $viewRenderer->pageHeader(
    'Nouveau contrat client',
    [['label' => 'Contrats clients', 'href' => '/contracts']],
    $actions
) ?>

<div class="page-body form-narrow">
    <?php if (!empty($formError)): ?><div class="alert alert-danger" role="alert"><?= e($formError) ?></div><?php endif; ?>
    <div class="card">
        <form method="post" action="/contracts" id="contract-form" data-guard>
            <?= csrf_field() ?>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client *</label>
                    <select class="form-select" name="third_party_id" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id'] ?>" <?= (string) ($formData['third_party_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['code'] . ' · ' . $client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Intitulé *</label>
                    <input class="form-control" value="<?= e((string) ($formData['title'] ?? '')) ?>" name="title" required placeholder="Maintenance applicative, forfait…">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date d'effet *</label>
                    <input type="date" class="form-control" value="<?= e((string) ($formData['starts_on'] ?? '')) ?>" name="starts_on" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin</label>
                    <input type="date" class="form-control" value="<?= e((string) ($formData['ends_on'] ?? '')) ?>" name="ends_on">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de signature</label>
                    <input type="date" class="form-control" value="<?= e((string) ($formData['signed_on'] ?? '')) ?>" name="signed_on">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Montant HT *</label>
                    <input type="number" step="0.01" min="0" class="form-control" value="<?= e((string) ($formData['amount_ht'] ?? '')) ?>" name="amount_ht" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cycle de facturation *</label>
                    <select class="form-select" name="billing_cycle" id="contract-billing-cycle">
                        <?php foreach (['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'] as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= ($formData['billing_cycle'] ?? 'monthly') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Modalités de paiement *</label>
                    <select class="form-select" name="payment_terms" id="contract-payment-terms" required>
                        <option value="cycle" <?= ($formData['payment_terms'] ?? 'cycle') === 'cycle' ? 'selected' : '' ?>>Selon le cycle de facturation</option>
                        <option value="full" <?= ($formData['payment_terms'] ?? 'cycle') === 'full' ? 'selected' : '' ?>>100 % à la signature</option>
                        <option value="milestones" <?= ($formData['payment_terms'] ?? 'cycle') === 'milestones' ? 'selected' : '' ?>>40 % à la signature / 40 % à la livraison V0 / 20 % à la livraison finale</option>
                    </select>
                    <div class="form-text">Les paiements 100 % et par étapes nécessitent le cycle « Ponctuel ».</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Prestations et services proposés *</label>
                    <textarea class="form-control" name="services_description" rows="5" maxlength="5000" required><?= e((string) ($formData['services_description'] ?? '')) ?></textarea>
                    <div class="form-text">Décrivez le périmètre, les livrables, la V0, les critères de livraison et les exclusions. Ces informations figurent dans le contrat PDF.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Renouvellement *</label>
                    <select class="form-select" name="renewal_mode">
                        <option value="tacit" <?= ($formData['renewal_mode'] ?? 'tacit') === 'tacit' ? 'selected' : '' ?>>Tacite</option>
                        <option value="express" <?= ($formData['renewal_mode'] ?? 'tacit') === 'express' ? 'selected' : '' ?>>Exprès (avenant)</option>
                        <option value="none" <?= ($formData['renewal_mode'] ?? 'tacit') === 'none' ? 'selected' : '' ?>>Aucun</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Préavis (jours) *</label>
                    <input type="number" min="0" class="form-control" name="notice_days" value="<?= e((string) ($formData['notice_days'] ?? '30')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut *</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($formData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="draft" <?= ($formData['status'] ?? 'active') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="terminated" <?= ($formData['status'] ?? 'active') === 'terminated' ? 'selected' : '' ?>>Résilié</option>
                        <option value="expired" <?= ($formData['status'] ?? 'active') === 'expired' ? 'selected' : '' ?>>Expiré</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="3"><?= e((string) ($formData['notes'] ?? '')) ?></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('contract-payment-terms').addEventListener('change', function () {
    if (this.value !== 'cycle') {
        document.getElementById('contract-billing-cycle').value = 'one_shot';
    }
});
</script>
