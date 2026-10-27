<?php

declare(strict_types=1);

/**
 * Layout minimal pour les pages d'authentification.
 *
 * @var string $content
 * @var string $title
 */
$flash = container()->get(\App\Core\Session\FlashBag::class)->pull();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Connexion') ?> — Prime Business Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-body">
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-briefcase-fill text-info fs-1"></i>
                <h1 class="h4 mt-2 mb-0">Prime Business Suite</h1>
                <p class="text-muted small">Prime Vision Solutions</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?php foreach ($flash as $type => $messages): ?>
                        <?php foreach ((array) $messages as $message): ?>
                            <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?> py-2 small">
                                <?= e($message) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?= $content ?>
                </div>
            </div>
            <p class="text-center text-muted small mt-3">
                Application de gestion d'entreprise · PHP natif
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
