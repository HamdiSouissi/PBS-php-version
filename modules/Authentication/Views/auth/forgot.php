<?php declare(strict_types=1); ?>
<h2 class="h5 mb-3 text-center">Mot de passe oublié</h2>
<p class="text-muted small text-center">Saisissez l’adresse utilisée pour vous connecter. Si un compte actif y est associé, vous recevrez un lien valable une heure.</p>
<form method="post" action="/forgot-password">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Adresse e-mail du compte</label>
        <input type="email" name="email" class="form-control" autocomplete="email" maxlength="180" required autofocus>
    </div>
    <button class="btn btn-primary w-100">Envoyer le lien de réinitialisation</button>
</form>
<div class="text-center mt-3">
    <a href="/login" class="small">Retour à la connexion</a>
</div>
