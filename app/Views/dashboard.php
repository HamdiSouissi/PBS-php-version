<?php

declare(strict_types=1);

/**
 * Tableau de bord : indicateurs clés, chiffre d'affaires et échéances.
 *
 * @var array<string,mixed> $kpis
 * @var list<array<string,mixed>> $recentInvoices
 * @var list<array<string,mixed>> $obligations
 * @var list<array{month:string,total:float}> $revenueSeries
 */
$cards = [
    ['Employés actifs', $kpis['active_employees'], 'people', 'primary'],
    ['Congés en attente', $kpis['pending_leaves'], 'calendar-week', 'warning'],
    ['Factures impayées', $kpis['unpaid_invoices'], 'receipt', 'danger'],
    ['Trésorerie', money_format($kpis['cash_balance']), 'bank', 'success'],
    ['MRR (HT)', money_format($kpis['mrr']), 'arrow-repeat', 'info'],
    ['Obligations ouvertes', $kpis['open_obligations'], 'shield-check', 'secondary'],
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
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="h3 mb-1"><?= $displayName !== '' ? 'Bonjour, ' . e($displayName) : 'Tableau de bord' ?></h2>
        <p class="text-muted mb-0">Vue d'ensemble de l'activité et des échéances clés.</p>
    </div>
    <span class="badge text-bg-light rounded-pill px-3 py-2">
        <i class="bi bi-calendar3 me-1"></i><?= e($dateLabel) ?>
    </span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card stat-<?= e($color) ?> h-100">
                <div class="card-body">
                    <div class="stat-icon"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value"><?= e((string) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Chiffre d'affaires facturé (12 mois, HT)</span>
                <span class="badge text-bg-light rounded-pill"><i class="bi bi-graph-up-arrow me-1"></i>Tendance</span>
            </div>
            <div class="card-body"><canvas id="revenueChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Échéances conformité</div>
            <ul class="list-group list-group-flush">
                <?php if ($obligations === []): ?>
                    <li class="list-group-item text-muted small">Aucune échéance enregistrée.</li>
                <?php endif; ?>
                <?php foreach ($obligations as $obligation): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="small"><?= e($obligation['title']) ?></span>
                        <span class="badge text-bg-light rounded-pill">
                            <?= e($obligation['due_on'] ? date('d/m/Y', strtotime((string) $obligation['due_on'])) : 'périodique') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Dernières factures clients</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Échéance</th><th>Montant TTC</th><th>Statut</th></tr></thead>
            <tbody>
            <?php if ($recentInvoices === []): ?>
                <tr><td colspan="5" class="text-muted small py-3 text-center">Aucune facture pour le moment.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentInvoices as $invoice): ?>
                <tr>
                    <td class="fw-semibold"><?= e($invoice['invoice_number']) ?></td>
                    <td><?= e($invoice['name']) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $invoice['due_date']))) ?></td>
                    <td><?= e(money_format($invoice['total_ttc'])) ?></td>
                    <td><span class="badge text-bg-info"><?= e($invoice['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('revenueChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(static fn (array $r) => $r['month'], $revenueSeries), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'CA HT (€)',
                data: <?= json_encode(array_map(static fn (array $r) => $r['total'], $revenueSeries)) ?>,
                borderColor: '#1f6feb',
                backgroundColor: 'rgba(31,111,235,.12)',
                fill: true,
                tension: .35,
                borderWidth: 2,
                pointBackgroundColor: '#1f6feb',
                pointRadius: 3,
                pointHoverRadius: 5,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.12)' } },
                x: { grid: { display: false } },
            },
        },
    });
}
</script>
