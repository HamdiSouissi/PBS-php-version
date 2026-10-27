<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $invoices
 * @var array<string,mixed> $pager
 * @var string $status
 */
$badges = ['draft' => 'secondary', 'issued' => 'primary', 'partial' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'];
?>
<div class="page-header">
    <div>
        <h2 class="page-title h4 mb-0">Factures de vente</h2>
        <p class="page-subtitle">Suivi des facturations, encaissements et e-facturation.</p>
    </div>
    <a href="/invoicing/invoices/new" class="btn btn-primary btn-sm"><i data-lucide="plus"></i>Nouvelle facture</a>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form method="get" class="m-0">
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" aria-label="Filtrer par statut">
            <option value="">Tous statuts</option>
            <?php foreach (['draft' => 'Brouillons', 'issued' => 'Émises', 'partial' => 'Partielles', 'paid' => 'Soldées', 'overdue' => 'En retard', 'cancelled' => 'Annulées'] as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="position-relative flex-grow-1" style="max-width: 320px;">
        <span class="position-absolute top-50 translate-middle-y ms-3 text-muted" data-lucide="search" style="width:16px;height:16px;"></span>
        <input type="search" class="form-control form-control-sm ps-5" placeholder="Rechercher dans la page…"
               aria-label="Recherche instantanée" data-table-target="#invoicesTable">
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" id="invoicesTable" data-sortable>
            <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Émission</th><th>Échéance</th><th class="text-end">HT</th><th class="text-end">TTC</th><th class="text-end">Solde</th><th>Statut</th><th>E-fact.</th></tr></thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <span class="empty-icon"><i data-lucide="receipt"></i></span>
                        <span class="empty-title">Aucune facture</span>
                        <span class="empty-text">Modifiez les filtres ou créez la première facture de vente.</span>
                        <a href="/invoicing/invoices/new" class="btn btn-primary btn-sm">
                            <i data-lucide="plus"></i>Nouvelle facture
                        </a>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><a href="/invoicing/invoices/<?= (int) $invoice['id'] ?>" class="fw-semibold"><?= e($invoice['invoice_number']) ?></a></td>
                    <td><?= e($invoice['third_party_name']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $invoice['issue_date']))) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $invoice['due_date']))) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $invoice['total_ht']) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $invoice['total_ttc']) ?></td>
                    <td class="text-end tnum <?= (float) $invoice['balance'] > 0 ? 'fw-semibold text-danger' : 'text-success' ?>"><?= money_format((float) $invoice['balance']) ?></td>
                    <td><span class="badge text-bg-<?= e($badges[$invoice['status']] ?? 'secondary') ?>"><?= e($invoice['status']) ?></span></td>
                    <td class="small"><?= e($invoice['electronic_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (($pager['pages'] ?? 1) > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $pager['pages']; $p++): ?>
        <li class="page-item <?= $p === $pager['page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&status=<?= e($status) ?>"><?= $p ?></a>
        </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
