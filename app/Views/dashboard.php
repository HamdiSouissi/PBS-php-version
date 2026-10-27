<?php

declare(strict_types=1);

/**
 * Tableau de bord : indicateurs clés, chiffre d'affaires, échéances et
 * actions rapides. Toutes les données proviennent de DashboardService ;
 * aucune valeur n'est simulée côté interface.
 *
 * @var array<string,mixed> $kpis
 * @var list<array<string,mixed>> $recentInvoices
 * @var list<array<string,mixed>> $obligations
 * @var list<array{month:string,total:float}> $revenueSeries
 */
$auth = container()->get(\App\Core\Auth\AuthManager::class);

$kpiCards = [
    [
        'label' => 'Employés actifs',
        'value' => (string) $kpis['active_employees'],
        'raw' => (float) $kpis['active_employees'],
        'icon' => 'users',
        'tone' => 'is-accent',
        'decimals' => 0,
    ],
    [
        'label' => 'Congés en attente',
        'value' => (string) $kpis['pending_leaves'],
        'raw' => (float) $kpis['pending_leaves'],
        'icon' => 'calendar-range',
        'tone' => 'is-warning',
        'decimals' => 0,
    ],
    [
        'label' => 'Factures impayées',
        'value' => (string) $kpis['unpaid_invoices'],
        'raw' => (float) $kpis['unpaid_invoices'],
        'icon' => 'receipt',
        'tone' => 'is-danger',
        'decimals' => 0,
        'sub' => $kpis['overdue_invoices'] > 0
            ? sprintf('%d en retard', (int) $kpis['overdue_invoices'])
            : 'aucune en retard',
    ],
    [
        'label' => 'Trésorerie',
        'value' => money_format($kpis['cash_balance']),
        'raw' => (float) $kpis['cash_balance'],
        'icon' => 'landmark',
        'tone' => 'is-success',
        'decimals' => 2,
        'currency' => '€',
    ],
    [
        'label' => 'MRR (HT)',
        'value' => money_format($kpis['mrr']),
        'raw' => (float) $kpis['mrr'],
        'icon' => 'repeat',
        'tone' => 'is-info',
        'decimals' => 2,
        'currency' => '€',
    ],
    [
        'label' => 'Obligations ouvertes',
        'value' => (string) $kpis['open_obligations'],
        'raw' => (float) $kpis['open_obligations'],
        'icon' => 'shield-check',
        'tone' => 'is-neutral',
        'decimals' => 0,
        'sub' => $kpis['pending_expenses'] > 0
            ? sprintf('%d note(s) de frais en attente', (int) $kpis['pending_expenses'])
            : 'flux à jour',
    ],
];

// Tendance du dernier mois facturé par rapport au précédent (données réelles).
$trend = null;
if (count($revenueSeries) >= 2) {
    $last = $revenueSeries[count($revenueSeries) - 1];
    $prev = $revenueSeries[count($revenueSeries) - 2];
    if ((float) $prev['total'] > 0.0) {
        $delta = ((float) $last['total'] - (float) $prev['total']) / (float) $prev['total'] * 100;
        $trend = ['value' => $delta, 'month' => $last['month']];
    }
}

$quickLinks = [
    ['/invoicing/invoices/new', 'Nouvelle facture', 'receipt', 'invoicing.write'],
    ['/invoicing/quotes/new', 'Nouveau devis', 'file-text', 'invoicing.write'],
    ['/clients/new', 'Nouveau client', 'user-plus', 'clients.write'],
    ['/contracts/new', 'Nouveau contrat', 'file-plus-2', 'contracts.write'],
    ['/employees/create', 'Nouveau salarié', 'user-plus', 'employees.write'],
    ['/expenses/new', 'Note de frais', 'wallet', 'expenses.request'],
    ['/portal', 'Demande de congé', 'calendar-range', 'portal.self'],
];

try {
    $dateLabel = ucfirst((new IntlDateFormatter(
        'fr_FR',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE
    ))->format(new DateTimeImmutable()));
} catch (Throwable) {
    $dateLabel = (new DateTimeImmutable())->format('d/m/Y');
}
$displayName = current_user()?->fullName() ?? '';
$statusBadges = [
    'draft' => 'secondary', 'issued' => 'primary', 'partial' => 'warning',
    'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark',
];
$greeting = $displayName !== '' ? 'Bonjour, ' . $displayName : 'Tableau de bord';
?>
<?= $viewRenderer->pageHeader(
    $greeting,
    [],
    '',
    $dateLabel . ' · vue d\'ensemble de l\'activité et des échéances clés.'
) ?>

<?php $visibleQuick = array_filter($quickLinks, static fn (array $q): bool => $auth->hasPermission($q[3])); ?>
<?php if ($visibleQuick !== []): ?>
    <div class="quick-actions mb-4">
        <?php foreach ($visibleQuick as [$url, $label, $icon]): ?>
            <a class="quick-action" href="<?= e($url) ?>">
                <i data-lucide="<?= e($icon) ?>"></i><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="kpi-grid mb-4" aria-label="Indicateurs clés">
    <?php foreach ($kpiCards as $i => $card): ?>
        <article class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon <?= e($card['tone']) ?>">
                    <i data-lucide="<?= e($card['icon']) ?>"></i>
                </span>
                <?php if ($i === 3 && $trend !== null): ?>
                    <span class="kpi-trend <?= $trend['value'] >= 0 ? 'is-up' : 'is-down' ?>">
                        <i data-lucide="<?= $trend['value'] >= 0 ? 'trending-up' : 'trending-down' ?>"></i>
                        <?= e(number_format(abs($trend['value']), 1, ',', ' ')) ?> %
                    </span>
                <?php endif; ?>
            </div>
            <div class="kpi-value tnum"
                 data-countup="<?= e((string) $card['raw']) ?>"
                 data-decimals="<?= e((string) $card['decimals']) ?>"
                 <?= isset($card['currency']) ? 'data-currency="' . e($card['currency']) . '"' : '' ?>><?= e($card['value']) ?></div>
            <div class="kpi-label"><?= e($card['label']) ?></div>
            <?php if (!empty($card['sub'])): ?>
                <div class="small text-muted mt-1"><?= e($card['sub']) ?></div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Chiffre d'affaires facturé (12 mois, HT)</span>
                <?php if ($trend !== null): ?>
                    <span class="kpi-trend <?= $trend['value'] >= 0 ? 'is-up' : 'is-down' ?>">
                        <i data-lucide="<?= $trend['value'] >= 0 ? 'trending-up' : 'trending-down' ?>"></i>
                        <?= e($trend['month']) ?> · <?= e(number_format($trend['value'], 1, ',', ' ')) ?> %
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="132" role="img"
                        aria-label="Courbe du chiffre d'affaires facturé sur douze mois"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Échéances conformité</div>
            <?php if ($obligations === []): ?>
                <div class="empty-state">
                    <span class="empty-icon"><i data-lucide="shield-check"></i></span>
                    <span class="empty-title">Aucune échéance en cours</span>
                    <span class="empty-text">Les obligations déclaratives sont à jour. Les nouvelles échéances apparaîtront ici.</span>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($obligations as $obligation): ?>
                        <div class="due-item">
                            <span class="due-dot <?= ($obligation['status'] ?? '') === 'overdue' ? 'is-overdue' : '' ?>"></span>
                            <span class="due-title"><?= e($obligation['title']) ?></span>
                            <span class="due-date">
                                <?= e($obligation['due_on'] ? date('d/m/Y', strtotime((string) $obligation['due_on'])) : 'périodique') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Dernières factures clients</span>
        <?php if ($auth->hasPermission('invoicing.read')): ?>
            <a class="btn btn-sm btn-outline-primary" href="/invoicing/invoices">
                <i data-lucide="arrow-right"></i>Tout voir
            </a>
        <?php endif; ?>
    </div>
    <?php if ($recentInvoices === []): ?>
        <div class="empty-state">
            <span class="empty-icon"><i data-lucide="receipt"></i></span>
            <span class="empty-title">Aucune facture pour le moment</span>
            <span class="empty-text">La première facture émise figurera dans cette liste.</span>
            <?php if ($auth->hasPermission('invoicing.write')): ?>
                <a class="btn btn-primary btn-sm" href="/invoicing/invoices/new">
                    <i data-lucide="plus"></i>Créer une facture
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead>
                <tr>
                    <th>N°</th><th>Client</th><th>Échéance</th>
                    <th class="text-end">Montant TTC</th><th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentInvoices as $invoice): ?>
                    <tr>
                        <td>
                            <?php if ($auth->hasPermission('invoicing.read')): ?>
                                <a class="table-link" href="/invoicing/invoices/<?= (int) $invoice['id'] ?>"><?= e($invoice['invoice_number']) ?></a>
                            <?php else: ?>
                                <span class="fw-semibold"><?= e($invoice['invoice_number']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($invoice['name']) ?></td>
                        <td class="text-muted small tnum"><?= e(date('d/m/Y', strtotime((string) $invoice['due_date']))) ?></td>
                        <td class="text-end fw-semibold tnum"><?= e(money_format($invoice['total_ttc'])) ?></td>
                        <td>
                            <span class="badge text-bg-<?= e($statusBadges[$invoice['status']] ?? 'secondary') ?>">
                                <?= e($invoice['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="/assets/vendor/chartjs/chart.umd.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('revenueChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hexToRgba = (hex, alpha) => {
        const value = hex.replace('#', '');
        const r = parseInt(value.slice(0, 2), 16);
        const g = parseInt(value.slice(2, 4), 16);
        const b = parseInt(value.slice(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };
    const readTokens = () => {
        const css = getComputedStyle(document.documentElement);
        return {
            accent: css.getPropertyValue('--accent').trim() || '#0b55bd',
            muted: css.getPropertyValue('--c-muted').trim() || '#746d61',
            border: css.getPropertyValue('--c-border-subtle').trim() || '#edebe6',
            body: css.getPropertyValue('--c-body').trim() || '#2d2922',
            surface: css.getPropertyValue('--c-surface').trim() || '#ffffff',
            ink: css.getPropertyValue('--c-ink').trim() || '#17140f',
        };
    };

    const ctx = canvas.getContext('2d');
    let revenueChart;

    const buildConfig = (t) => {
        return {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(static fn (array $r) => $r['month'], $revenueSeries), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'CA HT (€)',
                    data: <?= json_encode(array_map(static fn (array $r) => (float) $r['total'], $revenueSeries)) ?>,
                    borderColor: t.accent,
                    backgroundColor: hexToRgba(t.accent, 0.10),
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: t.accent,
                    pointHoverBorderColor: t.surface,
                    pointHoverBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                animation: reduceMotion ? false : { duration: 600, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: t.ink,
                        titleColor: t.surface,
                        bodyColor: t.surface,
                        padding: 10,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: (item) => new Intl.NumberFormat('fr-FR', {
                                style: 'currency', currency: 'EUR', maximumFractionDigits: 0,
                            }).format(item.parsed.y),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: t.border },
                        ticks: {
                            color: t.muted,
                            font: { size: 11 },
                            callback: (value) => new Intl.NumberFormat('fr-FR', {
                                notation: 'compact', maximumFractionDigits: 1,
                            }).format(value),
                        },
                    },
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: { color: t.muted, font: { size: 11 }, maxRotation: 0, autoSkip: true },
                    },
                },
            },
        };
    };

    revenueChart = new Chart(ctx, buildConfig(readTokens()));

    /* Le graphique hérite immédiatement du nouveau thème sans rechargement. */
    document.addEventListener('pbs:theme-changed', () => {
        const t = readTokens();
        const dataset = revenueChart.data.datasets[0];
        dataset.borderColor = t.accent;
        dataset.backgroundColor = hexToRgba(t.accent, 0.10);
        dataset.pointHoverBackgroundColor = t.accent;
        dataset.pointHoverBorderColor = t.surface;
        revenueChart.options.scales.y.grid.color = t.border;
        revenueChart.options.scales.y.ticks.color = t.muted;
        revenueChart.options.scales.x.ticks.color = t.muted;
        revenueChart.options.plugins.tooltip.backgroundColor = t.ink;
        revenueChart.options.plugins.tooltip.titleColor = t.surface;
        revenueChart.options.plugins.tooltip.bodyColor = t.surface;
        revenueChart.update(reduceMotion ? 'none' : 'active');
    });
});
</script>
