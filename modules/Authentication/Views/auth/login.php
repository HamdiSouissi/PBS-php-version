<?php

declare(strict_types=1);
/** @var string $title */
?>
<h2 class="h5 mb-3 text-center">Connexion</h2>
<form method="post" action="/login" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Adresse e-mail</label>
        <div class="input-group">
            <span class="input-group-text"><i data-lucide="mail"></i></span>
            <input type="email" name="email" class="form-control" required autofocus
                   value="<?= e(old('email')) ?>" autocomplete="username">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Mot de passe</label>
        <div class="input-group">
            <span class="input-group-text"><i data-lucide="lock"></i></span>
            <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label small" for="remember">Se souvenir de moi</label>
        </div>
        <a href="/forgot-password" class="small">Mot de passe oublié ?</a>
    </div>
    <button class="btn btn-primary w-100" type="submit">
        <i data-lucide="log-in"></i>Se connecter
    </button>
</form>
<p class="text-muted small mt-3 mb-0">
    Compte de démonstration initial : <code>admin@prime-vision-solutions.fr</code> / <code>PBS@2026!admin</code>
    (changement obligatoire à la première connexion).
</p>
