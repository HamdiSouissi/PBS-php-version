<?php

declare(strict_types=1);
/** @var string $title */
?>
<div class="login-heading">
    <span class="login-kicker">BIENVENUE CHEZ VOUS</span>
    <h2>Heureux de vous revoir<span>.</span></h2>
    <p>Connectez-vous pour accéder à votre espace.</p>
</div>
<form class="login-form" method="post" action="/login" novalidate>
    <?= csrf_field() ?>
    <div class="login-field">
        <label for="login-email">Adresse e-mail</label>
        <div class="login-input-wrap">
            <i data-lucide="mail" aria-hidden="true"></i>
            <input id="login-email" type="email" name="email" required autofocus
                   value="<?= e(old('email')) ?>" autocomplete="username" placeholder="vous@entreprise.fr">
        </div>
    </div>
    <div class="login-field">
        <div class="login-label-row">
            <label for="login-password">Mot de passe</label>
            <a href="/forgot-password">Mot de passe oublié ?</a>
        </div>
        <div class="login-input-wrap">
            <i data-lucide="lock-keyhole" aria-hidden="true"></i>
            <input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Saisissez votre mot de passe">
            <button class="login-password-toggle" type="button" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false" hidden><span>Afficher</span></button>
        </div>
    </div>
    <div class="login-remember">
        <input type="checkbox" name="remember" id="remember" value="1">
        <label for="remember">Se souvenir de moi</label>
    </div>
    <button class="login-submit" type="submit"><span>Se connecter</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
</form>
<div class="login-reassurance"><i data-lucide="lock-keyhole" aria-hidden="true"></i><span>Votre espace, en toute confidentialité.</span></div>
