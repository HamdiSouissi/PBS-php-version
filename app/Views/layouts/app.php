<?php

declare(strict_types=1);

/**
 * Layout principal de l'application (sidebar + topbar Bootstrap 5).
 *
 * @var string $content
 * @var string $title
 */
$user = current_user();
$flash = container()->get(\App\Core\Session\FlashBag::class)->pull();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <title><?= e($title ?? 'PBS') ?> — Prime Business Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="wrapper d-flex">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand text-decoration-none" href="/dashboard">
            <span class="brand-tile"><img src="/assets/img/logo-mark.png" alt="Logo Prime Vision Solutions"></span>
            <span class="brand-wordmark">
                <span class="brand-name">Prime Business Suite</span>
                <span class="brand-sub">Prime Vision Solutions</span>
            </span>
        </a>
        <nav class="nav flex-column">
            <?= $this->navItem('/dashboard', 'Tableau de bord', 'speedometer2') ?>
            <?= $this->navSection('Pilotage') ?>
            <?= $this->navItem('/organization', 'Entreprise & organisation', 'building') ?>
            <?= $this->navItem('/employees', 'Employés', 'people') ?>
            <?= $this->navItem('/hr/leaves', 'Congés', 'calendar-week') ?>
            <?= $this->navItem('/portal', 'Portail salarié', 'person-badge', 'portal.self') ?>
            <?= $this->navItem('/payroll', 'Paie', 'cash-stack') ?>
            <?= $this->navSection('Finances') ?>
            <?= $this->navItem('/invoicing/invoices', 'Facturation', 'receipt', 'invoicing.read') ?>
            <?= $this->navItem('/subscriptions', 'Abonnements', 'arrow-repeat') ?>
            <?= $this->navItem('/contracts', 'Contrats clients', 'file-earmark-text') ?>
            <?= $this->navItem('/expenses', 'Notes de frais', 'wallet2') ?>
            <?= $this->navItem('/accounting', 'Comptabilité', 'journal-book') ?>
            <?= $this->navItem('/treasury', 'Trésorerie', 'bank') ?>
            <?= $this->navItem('/dividends', 'Associés & dividendes', 'pie-chart') ?>
            <?= $this->navSection('Gouvernance') ?>
            <?= $this->navItem('/compliance', 'Conformité', 'shield-check') ?>
            <?= $this->navItem('/documents', 'Documents', 'folder2-open') ?>
            <?= $this->navItem('/ai/assistant', 'Assistant IA', 'stars') ?>
            <?= $this->navItem('/settings/audit', 'Journal d\'audit', 'clock-history') ?>
        </nav>
    </aside>

    <div class="main flex-grow-1 d-flex flex-column min-vh-100">
        <header class="topbar d-flex align-items-center px-3 px-lg-4 gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="h6 mb-0 flex-grow-1"><?= e($title ?? 'Prime Business Suite') ?></h1>
            <div class="dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <span class="avatar"><?= e($user?->initials() ?? '?') ?></span>
                    <span class="d-none d-md-inline"><?= e($user?->fullName() ?? '') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                    <li><a class="dropdown-item" href="/settings/users"><i class="bi bi-people me-2"></i>Utilisateurs</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="/logout" class="px-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content p-3 p-lg-4 flex-grow-1">
            <?php foreach ($flash as $type => $messages): ?>
                <?php foreach ((array) $messages as $message): ?>
                    <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?> alert-dismissible fade show">
                        <?= e($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
        <footer class="text-center text-muted small py-3 border-top">
            Prime Business Suite — PHP natif · données sensibles protégées · voir <a href="/compliance">conformité</a>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
