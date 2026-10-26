<?php declare(strict_types=1); ?>
<h2 class="h5 mb-3 text-center">Double authentification</h2>
<p class="text-muted small text-center">Saisissez le code à 6 chiffres de votre application TOTP.</p>
<form method="post" action="/two-factor">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Code TOTP</label>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               class="form-control form-control-lg text-center fw-bold" autofocus required>
    </div>
    <button class="btn btn-primary w-100">Vérifier</button>
</form>
<div class="text-center mt-3">
    <a href="/login" class="small">Retour à la connexion</a>
</div>
