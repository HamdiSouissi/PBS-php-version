<?php

declare(strict_types=1);

/**
 * Layout minimal pour les pages d'authentification (connexion, mot de passe
 * oublié, double facteur). Aucune navigation applicative.
 *
 * @var string $content
 * @var string $title
 */
$flash = container()->get(\App\Core\Session\FlashBag::class)->pull();
$toastIcons = ['success' => 'circle-check', 'warning' => 'triangle-alert', 'error' => 'circle-x', 'danger' => 'circle-x', 'info' => 'info'];
?>
<!doctype html>
<html lang="fr" data-theme="auto" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <title><?= e($title ?? 'Connexion') ?> — Prime Business Suite</title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('pbs-theme') || 'auto';
                var dark = theme === 'dark' || (theme === 'auto' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = theme;
                document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
            } catch (e) { /* thème automatique */ }
        })();
    </script>
    <link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/app.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/glass-theme.css')) ?>" rel="stylesheet">
    <?php if (!empty($loginPage)): ?>
        <link href="<?= e(asset('assets/css/login.css')) ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="auth-body<?= !empty($loginPage) ? ' login-body' : '' ?>">
<?php if (!empty($loginPage)): ?>
<div class="login-shell">
    <aside class="login-intro" aria-label="Prime Business Suite">
        <div class="login-brand">
            <span class="login-brand-mark"><img src="/assets/img/logo-mark.png" alt=""></span>
            <span><strong>PRIME VISION</strong><small>SOLUTIONS</small></span>
        </div>
        <div class="login-intro-copy">
            <span class="login-eyebrow"><span></span> VOTRE ESPACE DE TRAVAIL</span>
            <h1>Les idées avancent.<br><em>Votre entreprise aussi.</em></h1>
            <p>Retrouvez l’essentiel de votre activité dans un espace pensé pour vous accompagner au quotidien.</p>
        </div>
        <div class="login-orbit" aria-hidden="true"><span></span><span></span><span></span><i></i></div>
        <div class="login-intro-footer"><span>PRIME BUSINESS SUITE</span><span>UN ESPACE, TOUT SIMPLEMENT.</span></div>
    </aside>
    <main class="login-panel">
        <div class="login-panel-inner">
            <div class="login-mobile-brand"><img src="/assets/img/logo-mark.png" alt=""> <span>PRIME VISION <small>SOLUTIONS</small></span></div>
            <div class="login-panel-top"><span>ESPACE PRIVÉ</span><span class="login-secure"><i data-lucide="shield-check"></i> Connexion sécurisée</span></div>
<?php else: ?>
<main class="auth-card">
    <img class="auth-logo" src="/assets/img/logo-full.png" alt="Prime Vision Solutions">
    <p class="auth-tagline">Prime Business Suite · gestion d'entreprise</p>
    <?php endif; ?>

    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ((array) $messages as $message): ?>
            <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?> mb-3" role="alert">
                <i data-lucide="<?= e($toastIcons[$type] ?? 'info') ?>"></i>
                <span><?= e($message) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?= $content ?>
<?php if (!empty($loginPage)): ?>
            <div class="login-panel-footer"><span>Prime Business Suite</span><span>Votre espace professionnel</span></div>
        </div>
    </main>
</div>
<?php else: ?>
</main>
<p class="auth-foot">Application de gestion d'entreprise · PHP natif</p>
<?php endif; ?>

<script src="<?= e(asset('assets/vendor/lucide/lucide.min.js')) ?>" defer></script>
<script>
    /* Les scripts différés s'exécutent avant DOMContentLoaded : on attend donc
       cet événement pour être certain que lucide est disponible. */
    document.addEventListener('DOMContentLoaded', () => {
        window.lucide?.createIcons();
        const toggle = document.querySelector('[data-password-toggle]');
        if (toggle) {
            toggle.hidden = false;
            toggle.addEventListener('click', () => {
                const input = document.getElementById('login-password');
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(visible));
                toggle.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                toggle.querySelector('span').textContent = visible ? 'Masquer' : 'Afficher';
            });
        }
    });
</script>
</body>
</html>
