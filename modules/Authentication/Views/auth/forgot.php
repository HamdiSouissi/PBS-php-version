<?php declare(strict_types=1); ?>
<h2 class="h5 mb-3 text-center">Mot de passe oublié</h2>
<form method="post" action="/forgot-password">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Adresse e-mail du compte</label>
        <input type="email" name="email" class="form-control" required autofocus>
    </div>
    <button class="btn btn-primary w-100">Envoyer le lien de réinitialisation</button>
</form>
<div class="text-center mt-3">
    <a href="/login" class="small">Retour à la connexion</a>
</div>
