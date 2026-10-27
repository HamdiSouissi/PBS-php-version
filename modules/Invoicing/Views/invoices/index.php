<?php

declare(strict_types=1);
/**
 * @var list<array<string,mixed>> $invoices
 * @var array<string,mixed> $pager
 * @var string $status
 */
$badges = ['draft' => 'secondary', 'issued' => 'primary', 'partial' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Factures de vente</h2>
    <a href="/invoicing/invoices/new" class="btn btn-primary btn-sm"><i class="bi bi-receipt me-1"></i>Nouvelle facture</a>
</div>
<form class="mb-3" method="get">
    <select class="form-select form-select-sm d-inline w-auto" name="status" onchange="this.form.submit()">
        <option value="">Tous statuts</option>
        <?php foreach (['draft' => 'Brouillons', 'issued' => 'Émises', 'partial' => 'Partielles', 'paid' => 'Soldées', 'overdue' => 'En retard', 'cancelled' => 'Annulées'] as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Émission</th><th>Échéance</th><th class="text-end">HT</th><th class="text-end">TTC</th><th class="text-end">Solde</th><th>Statut</th><th>E-fact.</th></tr></thead>
            <tbody>
            <?php if ($invoices === []): ?><tr><td colspan="9" class="text-center text-muted py-3">Aucune facture.</td></tr><?php endif; ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><a href="/invoicing/invoices/<?= (int) $invoice['id'] ?>" class="fw-semibold"><?= e($invoice['invoice_number']) ?></a></td>
                    <td><?= e($invoice['third_party_name']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $invoice['issue_date']))) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $invoice['due_date']))) ?></td>
                    <td class="text-end"><?= money_format((float) $invoice['total_ht']) ?></td>
                    <td class="text-end"><?= money_format((float) $invoice['total_ttc']) ?></td>
                    <td class="text-end <?= (float) $invoice['balance'] > 0 ? 'fw-semibold text-danger' : 'text-success' ?>"><?= money_format((float) $invoice['balance']) ?></td>
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
