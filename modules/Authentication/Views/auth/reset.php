<?php declare(strict_types=1); /** @var string $token */ ?>
<h2 class="h5 mb-3 text-center">Nouveau mot de passe</h2>
<form method="post" action="/reset-password">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
        <label class="form-label">Mot de passe (12 caractères min., majuscule, minuscule, chiffre, spécial)</label>
        <input type="password" name="password" class="form-control" required minlength="12">
    </div>
    <div class="mb-3">
        <label class="form-label">Confirmation</label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="12">
    </div>
    <button class="btn btn-primary w-100">Mettre à jour</button>
</form>
