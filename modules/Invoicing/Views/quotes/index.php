<?php

declare(strict_types=1);
/** @var list<array<string,mixed>> $quotes */
$badges = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'rejected' => 'danger', 'invoiced' => 'primary', 'expired' => 'dark'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Devis</h2>
    <a href="/invoicing/quotes/new" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-plus me-1"></i>Nouveau devis</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Date</th><th>Validité</th><th class="text-end">TTC</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($quotes === []): ?><tr><td colspan="7" class="text-center text-muted py-3">Aucun devis.</td></tr><?php endif; ?>
            <?php foreach ($quotes as $quote): ?>
                <tr>
                    <td><a href="/invoicing/quotes/<?= (int) $quote['id'] ?>" class="fw-semibold"><?= e($quote['quote_number']) ?></a></td>
                    <td><?= e($quote['third_party_name']) ?></td>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $quote['issue_date']))) ?></td>
                    <td class="small"><?= $quote['valid_until'] ? e(date('d/m/Y', strtotime((string) $quote['valid_until']))) : '—' ?></td>
                    <td class="text-end"><?= money_format((float) $quote['total_ttc']) ?></td>
                    <td><span class="badge text-bg-<?= e($badges[$quote['status']] ?? 'secondary') ?>"><?= e($quote['status']) ?></span></td>
                    <td class="text-end">
                        <?php if ($quote['status'] === 'draft'): ?>
                            <form method="post" action="/invoicing/quotes/<?= (int) $quote['id'] ?>/sent" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-info">Envoyer</button></form>
                        <?php endif; ?>
                        <?php if (in_array($quote['status'], ['draft', 'sent'], true)): ?>
                            <form method="post" action="/invoicing/quotes/<?= (int) $quote['id'] ?>/accept" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-success">Accepter</button></form>
                        <?php endif; ?>
                        <?php if ($quote['status'] === 'accepted'): ?>
                            <form method="post" action="/invoicing/quotes/<?= (int) $quote['id'] ?>/convert" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Facturer</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
