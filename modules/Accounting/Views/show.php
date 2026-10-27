<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $entry
 * @var list<array<string,mixed>> $lines
 */
$statusBadges = ['draft' => 'secondary', 'posted' => 'success', 'cancelled' => 'danger'];
$statusLabels = ['draft' => 'Brouillon', 'posted' => 'Comptabilisée', 'cancelled' => 'Annulée'];
$totalDebit = array_sum(array_map(static fn (array $l): float => (float) $l['debit'], $lines));
$totalCredit = array_sum(array_map(static fn (array $l): float => (float) $l['credit'], $lines));

$actions = '<span class="badge text-bg-' . e($statusBadges[$entry['status']] ?? 'secondary') . '">'
    . e($statusLabels[$entry['status']] ?? $entry['status']) . '</span>';
$subtitle = $entry['journal_code'] . ' · ' . $entry['journal_label'] . ' · ' . $entry['fiscal_year_label'] . ' · '
    . date('d/m/Y', strtotime((string) $entry['entry_date']))
    . (($entry['reference'] ?? '') !== '' ? ' · réf. ' . $entry['reference'] : '');
?>
<?= $viewRenderer->pageHeader(
    'Écriture n°' . (int) $entry['id'],
    [['label' => 'Comptabilité', 'href' => '/accounting']],
    $actions,
    $subtitle
) ?>

<div class="page-body">
    <div class="card">
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
                        <td class="text-end tnum"><?= (float) $line['debit'] > 0 ? money_format((float) $line['debit']) : '—' ?></td>
                        <td class="text-end tnum"><?= (float) $line['credit'] > 0 ? money_format((float) $line['credit']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="table-light fw-semibold">
                    <td colspan="3">Totaux</td>
                    <td class="text-end tnum"><?= money_format($totalDebit) ?></td>
                    <td class="text-end tnum"><?= money_format($totalCredit) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="page-actions">
        <?php if ($entry['status'] === 'posted'): ?>
            <form method="post" action="/accounting/entries/<?= (int) $entry['id'] ?>/cancel"
                  data-confirm="Annuler cette écriture ? Elle sortira de la balance."
                  data-confirm-title="Annulation d'écriture">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i data-lucide="circle-x"></i>Annuler l'écriture</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="/accounting"><i data-lucide="arrow-left"></i>Retour à la comptabilité</a>
    </div>
</div>
