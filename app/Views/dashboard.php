<?php

declare(strict_types=1);

/**
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
?>
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="icon text-bg-<?= e($color) ?> bg-opacity-10"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div>
                        <div class="text-muted small"><?= e($label) ?></div>
                        <div class="fw-bold fs-5"><?= e((string) $value) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Chiffre d'affaires facturé (12 mois, HT)</div>
            <div class="card-body"><canvas id="revenueChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Échéances conformité</div>
            <ul class="list-group list-group-flush">
                <?php if ($obligations === []): ?>
                    <li class="list-group-item text-muted small">Aucune échéance enregistrée.</li>
                <?php endif; ?>
                <?php foreach ($obligations as $obligation): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="small"><?= e($obligation['title']) ?></span>
                        <span class="badge text-bg-light border">
                            <?= e($obligation['due_on'] ? date('d/m/Y', strtotime((string) $obligation['due_on'])) : 'périodique') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold">Dernières factures clients</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Échéance</th><th>Montant TTC</th><th>Statut</th></tr></thead>
            <tbody>
            <?php if ($recentInvoices === []): ?>
                <tr><td colspan="5" class="text-muted small py-3 text-center">Aucune facture pour le moment.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentInvoices as $invoice): ?>
                <tr>
                    <td><?= e($invoice['invoice_number']) ?></td>
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
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,.12)',
                fill: true,
                tension: .3,
            }],
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });
}
</script>
