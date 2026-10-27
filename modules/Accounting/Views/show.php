<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $entry
 * @var list<array<string,mixed>> $lines
 */
$statusBadges = ['draft' => 'secondary', 'posted' => 'success', 'cancelled' => 'danger'];
$statusLabels = ['draft' => 'Brouillon', 'posted' => 'Comptabilisée', 'cancelled' => 'Annulée'];
$totalDebit = array_sum(array_map(static fn (array $l): float => (float) $l['debit'], $lines));
$totalCredit = array_sum(array_map(static fn (array $l): float => (float) $l['credit'], $lines));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Écriture n°<?= (int) $entry['id'] ?></h2>
        <p class="text-muted mb-0">
            <span class="badge text-bg-light border"><?= e($entry['journal_code']) ?></span>
            <?= e($entry['journal_label']) ?> · <?= e($entry['fiscal_year_label']) ?> ·
            <?= e(date('d/m/Y', strtotime((string) $entry['entry_date']))) ?>
            <?php if (($entry['reference'] ?? '') !== ''): ?> · réf. <?= e($entry['reference']) ?><?php endif; ?>
        </p>
    </div>
    <span class="badge text-bg-<?= e($statusBadges[$entry['status']] ?? 'secondary') ?>"><?= e($statusLabels[$entry['status']] ?? $entry['status']) ?></span>
</div>

<div class="card mb-3">
    <div class="card-header"><?= e($entry['label']) ?></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Compte</th><th>Libellé</th><th>Tiers</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td class="fw-semibold"><?= e($line['account_code']) ?></td>
                    <td><?= e($line['label']) ?></td>
                    <td class="small text-muted"><?= e($line['third_party']) ?></td>
                    <td class="text-end"><?= (float) $line['debit'] > 0 ? money_format((float) $line['debit']) : '—' ?></td>
                    <td class="text-end"><?= (float) $line['credit'] > 0 ? money_format((float) $line['credit']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr class="table-light fw-semibold">
                <td colspan="3">Totaux</td>
                <td class="text-end"><?= money_format($totalDebit) ?></td>
                <td class="text-end"><?= money_format($totalCredit) ?></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="d-flex gap-2">
    <?php if ($entry['status'] === 'posted'): ?>
        <form method="post" action="/accounting/entries/<?= (int) $entry['id'] ?>/cancel"
              onsubmit="return confirm('Annuler cette écriture ? Elle sortira de la balance.');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Annuler l'écriture</button>
        </form>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="/accounting">Retour à la comptabilité</a>
</div>
