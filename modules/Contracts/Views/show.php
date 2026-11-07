<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $contract
 * @var ?array<string,mixed> $pdfDoc
 * @var list<array<string,mixed>> $history
 */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'];
$paymentLabels = ['cycle' => 'Selon le cycle de facturation', 'full' => '100 % à la signature', 'milestones' => '40 % à la signature / 40 % à la livraison V0 / 20 % à la livraison finale'];
$renewalLabels = ['none' => 'Aucun', 'tacit' => 'Tacite', 'express' => 'Exprès'];

$actions = '<form method="post" action="/contracts/' . (int) $contract['id'] . '/pdf">'
    . csrf_field() . '<button class="btn btn-primary btn-sm"><i data-lucide="file-text"></i>Régénérer PDF</button></form>';
if ($pdfDoc !== null) {
    $actions .= '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/' . (int) $pdfDoc['id'] . '/preview">'
        . '<i data-lucide="eye"></i>Aperçu</a>';
    $actions .= '<a class="btn btn-outline-secondary btn-sm" href="/pdf/' . (int) $pdfDoc['id'] . '/download">'
        . '<i data-lucide="download"></i>Télécharger</a>';
}
?>
<?= $viewRenderer->pageHeader(
    (string) $contract['contract_number'],
    [['label' => 'Contrats clients', 'href' => '/contracts']],
    $actions,
    $contract['title'] . ' · ' . $contract['client_name']
) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Caractéristiques</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>Client</span><strong><?= e($contract['client_name']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Date d'effet</span><span><?= e(date('d/m/Y', strtotime((string) $contract['starts_on']))) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Date de fin</span><span><?= e($contract['ends_on'] ? date('d/m/Y', strtotime((string) $contract['ends_on'])) : 'Indéterminée') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Cycle</span><span><?= e($cycleLabels[$contract['billing_cycle']] ?? $contract['billing_cycle']) ?></span></li>
                <li class="list-group-item"><span>Modalités de paiement : </span><strong><?= e($paymentLabels[$contract['payment_terms'] ?? ''] ?? 'Non précisées (contrat historique)') ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Renouvellement</span><span><?= e($renewalLabels[$contract['renewal_mode']] ?? $contract['renewal_mode']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Préavis</span><span><?= (int) $contract['notice_days'] ?> jours</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Montant HT</span><strong><?= money_format((float) $contract['amount_ht']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Statut</span><span class="badge text-bg-info"><?= e($contract['status']) ?></span></li>
            </ul>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header">Signature électronique simple</div>
            <div class="card-body">
                <p class="small">La signature porte sur une copie figée du dernier PDF. Elle ne vérifie pas l’identité du client et ne constitue pas une signature qualifiée eIDAS. Conservez le contrat figé et le justificatif ensemble.</p>
                <?php if (!empty($signatureLink)): ?>
                    <div class="alert alert-warning">Lien personnel affiché une seule fois : <input class="form-control" readonly value="<?= e((string) $signatureLink) ?>">Copiez-le et transmettez-le de manière sécurisée. Il expire après 7 jours ; il donne accès au contrat et permet de signer.</div>
                <?php endif; ?>
                <?php if (container()->get(\App\Core\Auth\AuthManager::class)->hasPermission('contracts.write') && $pdfDoc !== null): ?>
                <form method="post" action="/contracts/<?= (int) $contract['id'] ?>/signatures" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_id" value="<?= (int) $pdfDoc['id'] ?>">
                    <div class="col-md-4"><label class="form-label">Nom du signataire client</label><input class="form-control" name="client_name" maxlength="180" required></div>
                    <div class="col-md-4"><label class="form-label">E-mail du signataire client</label><input type="email" class="form-control" name="client_email" maxlength="254" required></div>
                    <div class="col-md-4"><label class="form-label">Transmission</label><select class="form-select" name="delivery"><option value="link">Lien à transmettre</option><option value="email">Envoyer par e-mail</option></select></div>
                    <div class="col-12"><label><input type="checkbox" name="consent" value="1" required> <?= e(\Modules\Contracts\Services\ContractSignatureService::PROVIDER_CONSENT) ?></label></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Signer et préparer l’invitation</button></div>
                </form>
                <?php endif; ?>
                <hr><h3 class="h6">Historique des signatures</h3>
                <?php if (empty($signatures)): ?><p class="text-muted">Aucune invitation.</p><?php endif; ?>
                <?php foreach ($signatures as $sig): ?>
                    <div class="border rounded p-2 mb-2 small">
                        <strong><?= e((string) $sig['client_name']) ?></strong> (<?= e((string) $sig['client_email']) ?>) —
                        <?php if ($sig['client_signed_at']): ?>Signé le <?= e((string) $sig['client_signed_at']) ?>
                        <?php elseif ($sig['revoked_at']): ?>Révoqué
                        <?php elseif ($sig['expires_at'] <= date('Y-m-d H:i:s')): ?>Expiré
                        <?php else: ?>En attente jusqu’au <?= e((string) $sig['expires_at']) ?><?php endif; ?> (heure serveur).
                        <div>Prestataire : <?= e((string) $sig['provider_name']) ?> — <?= e((string) $sig['provider_signed_at']) ?></div>
                        <a href="/contracts/<?= (int) $contract['id'] ?>/signatures/<?= (int) $sig['id'] ?>/document">Contrat figé</a>
                        <?php if ($sig['client_signed_at']): ?> · <a href="/contracts/<?= (int) $contract['id'] ?>/signatures/<?= (int) $sig['id'] ?>/receipt">Justificatif</a><?php endif; ?>
                        <?php if (!$sig['client_signed_at'] && !$sig['revoked_at'] && container()->get(\App\Core\Auth\AuthManager::class)->hasPermission('contracts.write')): ?>
                        <form method="post" action="/contracts/<?= (int) $contract['id'] ?>/signatures/<?= (int) $sig['id'] ?>/revoke"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger mt-1">Révoquer le lien</button></form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Versions PDF générées</span>
                <span class="badge text-bg-light rounded-pill"><?= count($history) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if ($history === []): ?><li class="list-group-item text-muted">Aucun PDF généré.</li><?php endif; ?>
                <?php foreach ($history as $pdfRow): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i data-lucide="file-text" class="text-danger me-1"></i><?= e($pdfRow['name']) ?>
                            <span class="text-muted ms-2"><?= e(date('d/m/Y H:i', strtotime((string) $pdfRow['created_at']))) ?></span></span>
                        <span class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/pdf/<?= (int) $pdfRow['id'] ?>/preview">Aperçu</a>
                            <a class="btn btn-sm btn-outline-primary" href="/pdf/<?= (int) $pdfRow['id'] ?>/download">Télécharger</a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Prestations et services proposés</div>
            <div class="card-body small"><?= !empty($contract['services_description']) ? nl2br(e((string) $contract['services_description'])) : 'Non renseignés (contrat historique).' ?></div>
        </div>
        <?php if (($contract['notes'] ?? '') !== ''): ?>
            <div class="card">
                <div class="card-header">Notes</div>
                <div class="card-body small"><?= nl2br(e((string) $contract['notes'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
