<?php

declare(strict_types=1);

/** @var string $message @var int $status */
?>
<div class="text-center py-5">
    <p class="display-1 fw-bold text-muted">404</p>
    <p class="fs-4">Page introuvable</p>
    <p class="text-muted"><?= e($message) ?></p>
    <a href="/dashboard" class="btn btn-primary"><i data-lucide="house"></i>Retour au tableau de bord</a>
</div>
