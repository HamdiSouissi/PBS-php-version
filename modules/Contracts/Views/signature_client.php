<?php

declare(strict_types=1);
/** @var array<string,mixed> $signature */
/** @var string $token */
?>
<h1 class="h4">Signature du contrat <?= e((string) $signature['contract_number']) ?></h1>
<p><?= e((string) $signature['title']) ?></p>
<p>Signataire attendu : <strong><?= e((string) $signature['client_name']) ?></strong> (<?= e((string) $signature['client_email']) ?>).</p>
<p>Signé pour le prestataire par <?= e((string) $signature['provider_name']) ?> le <?= e((string) $signature['provider_signed_at']) ?> (heure serveur).</p>
<p><a class="btn btn-outline-primary" href="/signature/<?= e($token) ?>/document">Télécharger et lire le contrat</a></p>
<p class="small text-muted">Empreinte SHA-256 : <?= e((string) $signature['document_sha256']) ?></p>
<?php if ($signature['client_signed_at']): ?>
    <div class="alert alert-success">Votre signature a été enregistrée le <?= e((string) $signature['client_signed_at']) ?> (heure serveur).</div>
    <p><a class="btn btn-primary" href="/signature/<?= e($token) ?>/receipt">Télécharger le justificatif</a></p>
    <p class="small">Conservez le contrat et son justificatif ensemble. Ce lien expire le <?= e((string) $signature['expires_at']) ?> (heure serveur).</p>
<?php else: ?>
    <p>Il s’agit d’une signature électronique simple : ce lien est personnel et permet de signer sans vérification indépendante de votre identité. Ne le transmettez à personne.</p>
    <form method="post" action="/signature/<?= e($token) ?>">
        <?= csrf_field() ?>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent" value="1" required><span class="form-check-label"><?= e(\Modules\Contracts\Services\ContractSignatureService::CLIENT_CONSENT) ?></span></label>
        <button class="btn btn-primary" type="submit">Signer le contrat</button>
    </form>
<?php endif; ?>
