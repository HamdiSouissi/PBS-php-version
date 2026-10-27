<?php

declare(strict_types=1);

/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var array<string,mixed> $contract
 * @var ?array<string,mixed> $pdfDoc
 * @var list<array<string,mixed>> $history
 */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'];
$renewalLabels = ['none' => 'Aucun', 'tacit' => 'Tacite', 'express' => 'Exprès'];

$actions = '<form method="post" action="/contracts/' . (int) $contract['id'] . '/pdf">'
    . csrf_field() . '<button class="btn btn-primary btn-sm"><i data-lucide="file-text"></i>Régénérer PDF</button></form>';
if ($pdfDoc !== null) {
    $actions .= '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/' . (int) $pdfDoc['id'] . '/preview">'
        . '<i data-lucide="eye"></i>Aperçu</a>';
    $actions .= '<a class="btn btn-outline-secondary btn-sm" href="/pdf/' . (int) $pdfDoc['id'] . '/download">'
        . '<i data-lucide="download"></i>Télécharger</a>';
}
?>
<?= $viewRenderer->pageHeader(
    (string) $contract['contract_number'],
    [['label' => 'Contrats clients', 'href' => '/contracts']],
    $actions,
    $contract['title'] . ' · ' . $contract['client_name']
) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Caractéristiques</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>Client</span><strong><?= e($contract['client_name']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Date d'effet</span><span><?= e(date('d/m/Y', strtotime((string) $contract['starts_on']))) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Date de fin</span><span><?= e($contract['ends_on'] ? date('d/m/Y', strtotime((string) $contract['ends_on'])) : 'Indéterminée') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Cycle</span><span><?= e($cycleLabels[$contract['billing_cycle']] ?? $contract['billing_cycle']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Renouvellement</span><span><?= e($renewalLabels[$contract['renewal_mode']] ?? $contract['renewal_mode']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Préavis</span><span><?= (int) $contract['notice_days'] ?> jours</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Montant HT</span><strong><?= money_format((float) $contract['amount_ht']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Statut</span><span class="badge text-bg-info"><?= e($contract['status']) ?></span></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Versions PDF générées</span>
                <span class="badge text-bg-light rounded-pill"><?= count($history) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if ($history === []): ?><li class="list-group-item text-muted">Aucun PDF généré.</li><?php endif; ?>
                <?php foreach ($history as $pdfRow): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i data-lucide="file-text" class="text-danger me-1"></i><?= e($pdfRow['name']) ?>
                            <span class="text-muted ms-2"><?= e(date('d/m/Y H:i', strtotime((string) $pdfRow['created_at']))) ?></span></span>
                        <span class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/pdf/<?= (int) $pdfRow['id'] ?>/preview">Aperçu</a>
                            <a class="btn btn-sm btn-outline-primary" href="/pdf/<?= (int) $pdfRow['id'] ?>/download">Télécharger</a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (($contract['notes'] ?? '') !== ''): ?>
            <div class="card">
                <div class="card-header">Notes</div>
                <div class="card-body small"><?= nl2br(e((string) $contract['notes'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
