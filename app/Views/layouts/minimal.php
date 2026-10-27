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
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-body">
<main class="auth-card">
    <img class="auth-logo" src="/assets/img/logo-full.png" alt="Prime Vision Solutions">
    <p class="auth-tagline">Prime Business Suite · gestion d'entreprise</p>

    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ((array) $messages as $message): ?>
            <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?> mb-3" role="alert">
                <i data-lucide="<?= e($toastIcons[$type] ?? 'info') ?>"></i>
                <span><?= e($message) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?= $content ?>
</main>
<p class="auth-foot">Application de gestion d'entreprise · PHP natif</p>

<script src="/assets/vendor/lucide/lucide.min.js" defer></script>
<script>
    /* Les scripts différés s'exécutent avant DOMContentLoaded : on attend donc
       cet événement pour être certain que lucide est disponible. */
    document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons());
</script>
</body>
</html>
