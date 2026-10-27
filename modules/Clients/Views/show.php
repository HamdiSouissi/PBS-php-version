<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $client
 * @var array<string,mixed> $stats
 * @var list<array<string,mixed>> $invoices
 * @var list<array<string,mixed>> $quotes
 * @var list<array<string,mixed>> $contracts
 * @var list<array<string,mixed>> $subscriptions
 * @var ?array<string,mixed> $pdfDoc
 * @var list<array<string,mixed>> $generatedPdfs
 */
$invoiceBadges = ['draft' => 'secondary', 'issued' => 'primary', 'partial' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-1"><?= e($client['name']) ?></h2>
        <span class="text-muted small"><?= e($client['code']) ?> · <?= e(trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')) ?: '—') ?></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="/clients/<?= (int) $client['id'] ?>/pdf">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Fiche PDF</button>
        </form>
        <?php if ($pdfDoc !== null): ?>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/<?= (int) $pdfDoc['id'] ?>/preview">
                <i class="bi bi-eye me-1"></i>Aperçu
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="/pdf/<?= (int) $pdfDoc['id'] ?>/download">
                <i class="bi bi-download me-1"></i>Télécharger
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="/clients/<?= (int) $client['id'] ?>/edit">
            <i class="bi bi-pencil me-1"></i>Modifier
        </a>
    </div>
</div>

<?php
$clientKpis = [
    ['Factures', (int) ($stats['invoice_count'] ?? 0), 'receipt', 'is-accent'],
    ['Impayées', (int) ($stats['unpaid_count'] ?? 0), 'triangle-alert', 'is-warning'],
    ['Total facturé TTC', money_format((float) ($stats['billed_ttc'] ?? 0)), 'euro', 'is-info'],
    ['Encours dû', money_format((float) ($stats['outstanding'] ?? 0)), 'hourglass', 'is-danger'],
];
?>
<div class="kpi-grid mb-4">
    <?php foreach ($clientKpis as $i => [$label, $value, $icon, $tone]): ?>
        <article class="kpi-card" style="--i: <?= $i ?>">
            <div class="kpi-top">
                <span class="kpi-icon <?= e($tone) ?>"><i data-lucide="<?= e($icon) ?>"></i></span>
            </div>
            <div class="kpi-value tnum"><?= e((string) $value) ?></div>
            <div class="kpi-label"><?= e($label) ?></div>
        </article>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Coordonnées</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>Forme juridique</span><strong><?= e($client['legal_form'] ?: '—') ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>SIREN</span><span><?= e($client['siren'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>SIRET</span><span><?= e($client['siret'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>TVA</span><span><?= e($client['vat_number'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Adresse</span><span class="text-end"><?= e(implode(', ', array_filter([$client['address'], trim($client['postal_code'] . ' ' . $client['city']), $client['country']]))) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>E-mail</span><span><?= e($client['email'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Téléphone</span><span><?= e($client['phone'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Règlement</span><span><?= (int) $client['payment_terms_days'] ?> jours · <?= e($client['payment_method']) ?></span></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Factures</span>
                <a class="btn btn-sm btn-primary" href="/invoicing/invoices/new?client=<?= (int) $client['id'] ?>"><i class="bi bi-plus-lg me-1"></i>Facturer</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>N°</th><th>Date</th><th class="text-end">TTC</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php if ($invoices === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Aucune facture.</td></tr><?php endif; ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a href="/invoicing/invoices/<?= (int) $invoice['id'] ?>"><?= e($invoice['invoice_number']) ?></a></td>
                            <td><?= e(date('d/m/Y', strtotime((string) $invoice['issue_date']))) ?></td>
                            <td class="text-end"><?= money_format((float) $invoice['total_ttc']) ?></td>
                            <td><span class="badge text-bg-<?= e($invoiceBadges[$invoice['status']] ?? 'secondary') ?>"><?= e($invoice['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Contrats & abonnements</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Référence</th><th>Type</th><th class="text-end">Montant HT</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php if ($contracts === [] && $subscriptions === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Aucun contrat ni abonnement.</td></tr><?php endif; ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><a href="/contracts/<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?></a></td>
                            <td>Contrat · <?= e($contract['billing_cycle']) ?></td>
                            <td class="text-end"><?= money_format((float) $contract['amount_ht']) ?></td>
                            <td><span class="badge text-bg-info"><?= e($contract['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <td><?= e($subscription['plan_label']) ?></td>
                            <td>Abonnement · x<?= e((string) $subscription['quantity']) ?></td>
                            <td class="text-end"><?= money_format((float) $subscription['unit_amount_ht']) ?></td>
                            <td><span class="badge text-bg-<?= $subscription['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($subscription['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Documents PDF liés</span>
                <span class="badge text-bg-light rounded-pill"><?= count($generatedPdfs) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if ($generatedPdfs === []): ?><li class="list-group-item text-muted">Aucun document généré.</li><?php endif; ?>
                <?php foreach ($generatedPdfs as $pdfRow): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-file-earmark-pdf text-danger me-1"></i><?= e($pdfRow['name']) ?></span>
                        <span class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/pdf/<?= (int) $pdfRow['id'] ?>/preview">Aperçu</a>
                            <a class="btn btn-sm btn-outline-primary" href="/pdf/<?= (int) $pdfRow['id'] ?>/download">Télécharger</a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
