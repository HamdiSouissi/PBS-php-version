<?php

declare(strict_types=1);

/**
 * Layout principal de l'application.
 *
 * Structure : sidebar givrée (repliable en rail), topbar matériau verre,
 * palette de commandes (Cmd/Ctrl + K), toasts, modale de confirmation.
 * Le thème (auto / clair / sombre) est appliqué avant le premier rendu par
 * le script inline de l'en-tête pour éviter tout clignotement.
 *
 * @var string $content
 * @var string $title
 */
$user = current_user();
$flash = container()->get(\App\Core\Session\FlashBag::class)->pull();
$authManager = container()->get(\App\Core\Auth\AuthManager::class);
// Horloge permanente exprimée dans le fuseau légal de l'entreprise (Paris).
$parisNow = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));

// Pastille des messages non lus de la messagerie collaborative (chat.use).
$chatUnread = 0;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$isChatPage = $currentPath === '/chat' || str_starts_with($currentPath, '/chat/');
if ($user !== null && $authManager->hasPermission('chat.use')) {
    $chatUnread = container()->get(\Modules\Chat\Services\ChatService::class)->unreadTotal($user->id);
}

// Navigation filtrée par permission RBAC. Le code icône est le nom Bootstrap
// historique ; le script global le remplace par une icône Lucide équivalente.
$navSections = [
    [
        'label' => 'Pilotage',
        'items' => [
            ['/organization', 'Entreprise & organisation', 'building', 'organization.read'],
            ['/employees', 'Employés', 'people', 'employees.read'],
            ['/hr/leaves', 'Congés', 'calendar-week', 'hr.leaves.read'],
            ['/hr/settings', 'Réglages RH', 'gear', 'hr.settings.manage'],
            ['/portal', 'Portail salarié', 'person-badge', 'portal.self'],
            ['/payroll', 'Paie', 'cash-stack', 'payroll.read'],
        ],
    ],
    [
        'label' => 'Communication',
        'items' => [
            ['/chat', 'Messagerie', 'chat-dots', 'chat.use', 'chat'],
            ['/mail', 'Boîte mail', 'envelope', 'mail.read'],
            ['/mail/admin/accounts', 'Comptes mail', 'gear', 'mail.configure'],
        ],
    ],
    [
        'label' => 'Finances',
        'items' => [
            ['/clients', 'Clients', 'people-fill', 'clients.read'],
            ['/invoicing/invoices', 'Facturation', 'receipt', 'invoicing.read'],
            ['/invoicing/quotes', 'Devis', 'file-earmark-text', 'invoicing.read'],
            ['/subscriptions', 'Abonnements', 'arrow-repeat', 'subscriptions.read'],
            ['/contracts', 'Contrats clients', 'file-earmark-text', 'contracts.read'],
            ['/expenses', 'Notes de frais', 'wallet2', 'expenses.read'],
            ['/accounting', 'Comptabilité', 'journal-book', 'accounting.read'],
            ['/treasury', 'Trésorerie', 'bank', 'treasury.read'],
            ['/dividends', 'Associés & dividendes', 'pie-chart', 'dividends.read'],
        ],
    ],
    [
        'label' => 'Gouvernance',
        'items' => [
            ['/compliance', 'Conformité', 'shield-check', 'compliance.read'],
            ['/documents', 'Documents', 'folder2-open', 'documents.read'],
            ['/ai/assistant', 'Assistant IA', 'stars', 'ai.use'],
            ['/settings/users', 'Utilisateurs', 'people', 'users.read'],
            ['/settings/roles', 'Rôles & permissions', 'shield-lock', 'users.read'],
            ['/settings/audit', "Journal d'audit", 'clock-history', 'audit.read'],
        ],
    ],
];

// Données de la palette de commandes : mêmes entrées, mêmes permissions.
$lucideMap = [
    'speedometer2' => 'gauge', 'building' => 'building-2', 'people' => 'users',
    'people-fill' => 'users', 'person' => 'user', 'person-badge' => 'id-card',
    'calendar-week' => 'calendar-range', 'cash-stack' => 'banknote',
    'bank' => 'landmark', 'receipt' => 'receipt', 'file-earmark-text' => 'file-text',
    'arrow-repeat' => 'repeat', 'wallet2' => 'wallet', 'journal-book' => 'book-open',
    'pie-chart' => 'pie-chart', 'shield-check' => 'shield-check',
    'folder2-open' => 'folder-open', 'stars' => 'sparkles',
    'shield-lock' => 'shield-lock', 'clock-history' => 'history',
    'envelope' => 'mail', 'gear' => 'settings', 'chat-dots' => 'messages-square',
];
$palette = [
    ['group' => 'Navigation', 'label' => 'Tableau de bord', 'href' => '/dashboard', 'icon' => 'gauge', 'hint' => ''],
];
foreach ($navSections as $section) {
    foreach ($section['items'] as [$url, $label, $icon, $permission]) {
        if (!$authManager->hasPermission($permission)) {
            continue;
        }
        $palette[] = [
            'group' => 'Navigation',
            'label' => $label,
            'href' => $url,
            'icon' => $lucideMap[$icon] ?? 'circle',
            'hint' => $section['label'],
        ];
    }
}
$quickActions = [
    ['/mail/compose', 'Nouveau message', 'mail-plus', 'mail.read'],
    ['/invoicing/invoices/new', 'Nouvelle facture', 'receipt', 'invoicing.write'],
    ['/invoicing/quotes/new', 'Nouveau devis', 'file-text', 'invoicing.write'],
    ['/clients/new', 'Nouveau client', 'user-plus', 'clients.write'],
    ['/contracts/new', 'Nouveau contrat client', 'file-plus-2', 'contracts.write'],
    ['/employees/create', 'Nouveau salarié', 'user-plus', 'employees.write'],
    ['/expenses/new', 'Nouvelle note de frais', 'wallet', 'expenses.request'],
    ['/portal', 'Demander un congé', 'calendar-range', 'portal.self'],
];
foreach ($quickActions as [$url, $label, $icon, $permission]) {
    if ($authManager->hasPermission($permission)) {
        $palette[] = ['group' => 'Actions rapides', 'label' => $label, 'href' => $url, 'icon' => $icon, 'hint' => ''];
    }
}

$toastIcons = ['success' => 'circle-check', 'warning' => 'triangle-alert', 'error' => 'circle-x', 'danger' => 'circle-x', 'info' => 'info'];
$pageTitle = $title ?? 'Prime Business Suite';
?>
<!doctype html>
<html lang="fr" data-theme="auto" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <title><?= e($pageTitle) ?> — Prime Business Suite</title>
    <script>
        /* Thème appliqué avant le premier affichage : aucun clignotement. */
        (function () {
            try {
                var theme = localStorage.getItem('pbs-theme') || 'auto';
                var dark = theme === 'dark' || (theme === 'auto' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = theme;
                document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
            } catch (e) { /* localStorage indisponible : thème automatique */ }
        })();
    </script>
    <link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="wrapper d-flex">
    <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
        <a class="sidebar-brand text-decoration-none" href="/dashboard">
            <span class="brand-tile"><img src="/assets/img/logo-mark.png" alt="Logo Prime Vision Solutions"></span>
            <span class="brand-wordmark">
                <span class="brand-name">Prime Business Suite</span>
                <span class="brand-sub">Prime Vision Solutions</span>
            </span>
        </a>
        <nav class="nav flex-column">
            <?= $this->navItem('/dashboard', 'Tableau de bord', 'speedometer2') ?>
            <?php foreach ($navSections as $section): ?>
                <?php
                $visibleItems = array_filter(
                    $section['items'],
                    static fn (array $item): bool => $authManager->hasPermission($item[3])
                );
                if ($visibleItems === []) {
                    continue;
                }
                ?>
                <?= $this->navSection($section['label']) ?>
                <?php foreach ($visibleItems as $item): ?>
                    <?php
                    [$url, $label, $icon, $permission] = $item;
                    $badgeHtml = '';
                    if (($item[4] ?? '') === 'chat') {
                        $badgeHtml = '<span class="badge text-bg-danger rounded-pill ms-auto chat-unread-total"'
                            . ' id="chatNavBadge"' . ($chatUnread === 0 ? ' hidden' : '') . '>'
                            . $chatUnread . '</span>';
                    }
                    ?>
                    <?= $this->navItem($url, $label, $icon, $permission, $badgeHtml) ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main d-flex flex-column min-vh-100">
        <header class="topbar d-flex align-items-center px-3">
            <button class="icon-btn d-lg-none" id="sidebarToggle" type="button"
                    aria-label="Ouvrir le menu de navigation" aria-controls="sidebar" aria-expanded="false">
                <i data-lucide="menu"></i>
            </button>
            <button class="icon-btn d-none d-lg-grid" id="railToggle" type="button"
                    aria-label="Replier la barre latérale" title="Replier / déplier la barre latérale">
                <i data-lucide="panel-left"></i>
            </button>

            <div class="me-auto"></div>

            <button type="button" class="search-trigger" data-palette-open aria-label="Recherche globale">
                <i data-lucide="search"></i>
                <span class="search-label">Rechercher ou aller à…</span>
                <kbd><span class="d-none d-sm-inline">Ctrl </span>K</kbd>
            </button>

            <?php if ($authManager->hasPermission('chat.use') && !$isChatPage): ?>
            <button type="button" class="icon-btn chat-topbar-btn" id="chatTopbarBtn"
                    aria-label="Ouvrir la messagerie" title="Messagerie" aria-expanded="false">
                <i data-lucide="messages-square"></i>
                <span class="chat-dock-badge chat-unread-total" id="chatTopbarBadge"<?= $chatUnread === 0 ? ' hidden' : '' ?>><?= (int) $chatUnread ?></span>
            </button>
            <?php endif; ?>

            <button type="button" class="theme-toggle" data-theme-toggle
                    aria-label="Basculer le thème" title="Basculer le thème">
                <i class="icon-auto" data-lucide="monitor"></i>
                <i class="icon-light" data-lucide="sun"></i>
                <i class="icon-dark" data-lucide="moon"></i>
            </button>

            <div class="topbar-clock d-none d-lg-flex" id="pbsClock" role="timer"
                 aria-label="Date et heure de Paris">
                <i data-lucide="clock"></i>
                <span class="topbar-clock-date" data-clock-date><?= e($parisNow->format('d/m/Y')) ?></span>
                <span class="topbar-clock-time" data-clock-time><?= e($parisNow->format('H:i:s')) ?></span>
                <span class="topbar-clock-zone">Paris</span>
            </div>

            <div class="dropdown">
                <button class="user-menu-btn dropdown-toggle" data-bs-toggle="dropdown"
                        aria-label="Menu utilisateur" aria-expanded="false">
                    <span class="avatar"><?= e($user?->initials() ?? '?') ?></span>
                    <span class="user-name d-none d-md-inline small fw-semibold"><?= e($user?->fullName() ?? '') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/profile"><i data-lucide="user"></i>Mon profil</a></li>
                    <?php if ($authManager->hasPermission('users.read')): ?>
                        <li><a class="dropdown-item" href="/settings/users"><i data-lucide="users"></i>Utilisateurs</a></li>
                        <li><a class="dropdown-item" href="/settings/roles"><i data-lucide="shield-lock"></i>Rôles &amp; permissions</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="/logout" class="px-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger w-100">
                                <i data-lucide="log-out"></i>Déconnexion
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content flex-grow-1">
            <div class="content-inner">
                <?= $content ?>
            </div>
        </main>
        <footer class="app-footer">
            <div>
                Prime Business Suite — PHP natif · données sensibles protégées<?php if ($authManager->hasPermission('compliance.read')): ?> · <a href="/compliance">espace conformité</a><?php endif; ?>
            </div>
        </footer>
    </div>
</div>

<?php if ($authManager->hasPermission('chat.use') && !$isChatPage): ?>
<!-- Widget de messagerie flottant (ancré en bas à droite, façon messagerie instantanée) -->
<div class="chat-dock" id="chatDock"
     data-csrf="<?= e(csrf_token()) ?>"
     data-initial-unread="<?= (int) $chatUnread ?>">
    <div class="chat-dock-panel" id="chatDockPanel" hidden>
        <!-- Vue : liste des conversations -->
        <section class="chat-dock-view" id="dockListView">
            <header class="chat-dock-header">
                <h6 class="mb-0 me-auto">Messagerie</h6>
                <a class="icon-btn chat-dock-header-btn" href="/chat" title="Ouvrir la messagerie complète" aria-label="Ouvrir la messagerie complète">
                    <i data-lucide="maximize-2"></i>
                </a>
                <button type="button" class="icon-btn chat-dock-header-btn" id="dockMinimizeBtn" title="Réduire" aria-label="Réduire">
                    <i data-lucide="minus"></i>
                </button>
                <button type="button" class="icon-btn chat-dock-header-btn" id="dockCloseBtn" title="Fermer" aria-label="Fermer">
                    <i data-lucide="x"></i>
                </button>
            </header>
            <div class="chat-dock-list" id="dockConversationList">
                <p class="text-muted small text-center py-4 px-3 mb-0">Chargement…</p>
            </div>
            <footer class="chat-dock-footer">
                <a class="btn btn-sm btn-outline-primary w-100" href="/chat">
                    <i data-lucide="square-pen"></i> Nouvel échange
                </a>
            </footer>
        </section>

        <!-- Vue : fil d'une conversation -->
        <section class="chat-dock-view" id="dockThreadView" hidden>
            <header class="chat-dock-header">
                <button type="button" class="icon-btn chat-dock-header-btn" id="dockBackBtn" title="Retour aux conversations" aria-label="Retour aux conversations">
                    <i data-lucide="arrow-left"></i>
                </button>
                <h6 class="mb-0 me-auto text-truncate" id="dockThreadTitle">—</h6>
                <button type="button" class="icon-btn chat-dock-header-btn" id="dockThreadMinimizeBtn" title="Réduire" aria-label="Réduire">
                    <i data-lucide="minus"></i>
                </button>
                <button type="button" class="icon-btn chat-dock-header-btn" id="dockThreadCloseBtn" title="Fermer" aria-label="Fermer">
                    <i data-lucide="x"></i>
                </button>
            </header>
            <div class="chat-dock-messages" id="dockMessages" aria-live="polite"></div>
            <form class="chat-dock-composer" id="dockComposer">
                <input type="hidden" name="conversation_id" id="dockConversationId">
                <textarea class="form-control form-control-sm" id="dockBody" rows="1" maxlength="5000"
                          placeholder="Votre message… (Entrée pour envoyer)"></textarea>
                <button class="btn btn-primary btn-sm" type="submit" aria-label="Envoyer">
                    <i data-lucide="send-horizontal"></i>
                </button>
            </form>
        </section>
    </div>

    <button type="button" class="chat-dock-fab" id="chatDockFab"
            aria-label="Ouvrir la messagerie" aria-expanded="false">
        <i data-lucide="messages-square"></i>
        <span class="chat-dock-badge chat-unread-total" id="dockFabBadge"<?= $chatUnread === 0 ? ' hidden' : '' ?>><?= (int) $chatUnread ?></span>
    </button>
</div>
<?php endif; ?>

<div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="false">
    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ((array) $messages as $message): ?>
            <?php $toastType = $type === 'error' ? 'danger' : $type; ?>
            <div class="app-toast is-<?= e($toastType) ?>" role="<?= $toastType === 'danger' ? 'alert' : 'status' ?>">
                <span class="toast-icon" data-lucide="<?= e($toastIcons[$type] ?? 'info') ?>"></span>
                <span class="toast-body">
                    <span class="toast-message"><?= e($message) ?></span>
                </span>
                <button type="button" class="toast-close" data-toast-close aria-label="Fermer" data-lucide="x"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<!-- Modale de confirmation partagée (remplace window.confirm) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmModalTitle">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="confirmModalTitle" data-confirm-title>Confirmer l’action</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body text-secondary" data-confirm-body></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger btn-sm" data-confirm-ok>Confirmer</button>
            </div>
        </div>
    </div>
</div>

<!-- Palette de commandes -->
<div class="palette-overlay" id="commandPalette" role="dialog" aria-modal="true" aria-label="Recherche globale">
    <div class="palette">
        <div class="palette-search">
            <i data-lucide="search"></i>
            <input type="text" id="paletteInput" placeholder="Rechercher une page ou une action…"
                   aria-label="Rechercher une page ou une action" autocomplete="off">
            <kbd>Échap</kbd>
        </div>
        <div class="palette-results" id="paletteResults"></div>
        <div class="palette-footer">
            <span><kbd>↑</kbd> <kbd>↓</kbd> naviguer</span>
            <span><kbd>Entrée</kbd> ouvrir</span>
            <span><kbd>Ctrl</kbd> + <kbd>K</kbd> fermer</span>
        </div>
    </div>
</div>

<script>window.PBS_DATA = { palette: <?= json_encode($palette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/vendor/lucide/lucide.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?php if ($authManager->hasPermission('chat.use') && !$isChatPage): ?>
<script src="<?= e(asset('assets/js/chat-widget.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
