<?php

declare(strict_types=1);
/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $quote
 * @var ?array<string,mixed> $pdfDoc
 */
$actions = '<span class="badge text-bg-info">' . e($quote['status']) . '</span>'
    . '<form method="post" action="/invoicing/quotes/' . (int) $quote['id'] . '/pdf">'
    . csrf_field() . '<button class="btn btn-outline-primary btn-sm"><i data-lucide="file-text"></i>Régénérer PDF</button></form>';
if (!empty($pdfDoc)) {
    $actions .= '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/' . (int) $pdfDoc['id'] . '/preview">'
        . '<i data-lucide="eye"></i>Aperçu</a>';
    $actions .= '<a class="btn btn-outline-secondary btn-sm" href="/pdf/' . (int) $pdfDoc['id'] . '/download">'
        . '<i data-lucide="download"></i>PDF</a>';
}
$actions .= '<a href="/invoicing/quotes" class="btn btn-outline-secondary btn-sm"><i data-lucide="arrow-left"></i>Retour</a>';
?>
<?= $viewRenderer->pageHeader(
    'Devis ' . $quote['quote_number'],
    [['label' => 'Facturation'], ['label' => 'Devis', 'href' => '/invoicing/quotes']],
    $actions
) ?>

<div class="page-body">
<div class="card">
    <div class="card-header fw-semibold"><?= e($quote['third_party_name']) ?>
        <span class="small text-muted float-end">Émis le <?= e(date('d/m/Y', strtotime((string) $quote['issue_date']))) ?></span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Libellé</th><th class="text-end">Qté</th><th class="text-end">P.U.</th><th class="text-end">TVA</th><th class="text-end">Total HT</th></tr></thead>
            <tbody>
            <?php foreach ($quote['lines'] as $line): ?>
                <tr><td><?= e($line['label']) ?></td><td class="text-end"><?= e((string) (float) $line['quantity']) ?></td>
                    <td class="text-end tnum"><?= money_format((float) $line['unit_price']) ?></td>
                    <td class="text-end"><?= e((string) (float) $line['vat_rate']) ?> %</td>
                    <td class="text-end tnum"><?= money_format((float) $line['line_ht']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr><td colspan="4" class="text-end fw-semibold">Total HT</td><td class="text-end tnum"><?= money_format((float) $quote['total_ht']) ?></td></tr>
            <tr><td colspan="4" class="text-end">TVA</td><td class="text-end tnum"><?= money_format((float) $quote['total_vat']) ?></td></tr>
            <tr class="table-light"><td colspan="4" class="text-end fw-bold">Total TTC</td><td class="text-end tnum fw-bold"><?= money_format((float) $quote['total_ttc']) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>
</div>
