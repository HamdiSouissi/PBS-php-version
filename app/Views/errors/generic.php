<?php

declare(strict_types=1);

/**
 * Page d'erreur générique partagée (403, 419, 422, 429, 500...).
 *
 * @var int $status
 * @var string $message
 * @var ?Throwable $exception
 */
$icons = [403 => 'shield-lock', 419 => 'shield-exclamation', 422 => 'exclamation-triangle',
          429 => 'hourglass-split', 500 => 'bug'];
$icon = $icons[$status] ?? 'x-circle';
?>
<div class="text-center py-5">
    <i class="bi bi-<?= e($icon) ?> display-4 text-danger"></i>
    <p class="display-5 fw-bold text-muted mt-2"><?= e($status) ?></p>
    <p class="fs-5"><?= e($message) ?></p>
    <?php if (!empty($exception) && config('app.debug')): ?>
        <pre class="text-start bg-dark text-light p-3 small rounded overflow-auto"><?= e((string) $exception) ?></pre>
    <?php endif; ?>
    <a href="/dashboard" class="btn btn-primary mt-3"><i class="bi bi-house me-1"></i>Retour au tableau de bord</a>
</div>
