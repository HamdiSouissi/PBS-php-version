<?php

declare(strict_types=1);
/** @var array<string,mixed> $quote */
?>
<h2 class="h4 mb-3">Devis <?= e($quote['quote_number']) ?> <span class="badge text-bg-info"><?= e($quote['status']) ?></span></h2>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold"><?= e($quote['third_party_name']) ?>
        <span class="small text-muted float-end">Émis le <?= e(date('d/m/Y', strtotime((string) $quote['issue_date']))) ?></span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Libellé</th><th class="text-end">Qté</th><th class="text-end">P.U.</th><th class="text-end">TVA</th><th class="text-end">Total HT</th></tr></thead>
            <tbody>
            <?php foreach ($quote['lines'] as $line): ?>
                <tr><td><?= e($line['label']) ?></td><td class="text-end"><?= e((string) (float) $line['quantity']) ?></td>
                    <td class="text-end"><?= money_format((float) $line['unit_price']) ?></td>
                    <td class="text-end"><?= e((string) (float) $line['vat_rate']) ?> %</td>
                    <td class="text-end"><?= money_format((float) $line['line_ht']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr><td colspan="4" class="text-end fw-semibold">Total HT</td><td class="text-end"><?= money_format((float) $quote['total_ht']) ?></td></tr>
            <tr><td colspan="4" class="text-end">TVA</td><td class="text-end"><?= money_format((float) $quote['total_vat']) ?></td></tr>
            <tr class="table-light"><td colspan="4" class="text-end fw-bold">Total TTC</td><td class="text-end fw-bold"><?= money_format((float) $quote['total_ttc']) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>
<a href="/invoicing/quotes" class="btn btn-link btn-sm">Retour</a>
