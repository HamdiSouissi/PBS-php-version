<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $contract
 * @var ?array<string,mixed> $pdfDoc
 * @var list<array<string,mixed>> $history
 */
$cycleLabels = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'half_yearly' => 'Semestriel', 'yearly' => 'Annuel', 'one_shot' => 'Ponctuel'];
$renewalLabels = ['none' => 'Aucun', 'tacit' => 'Tacite', 'express' => 'Exprès'];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-1"><?= e($contract['contract_number']) ?></h2>
        <span class="text-muted small"><?= e($contract['title']) ?> · <?= e($contract['client_name']) ?></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="/contracts/<?= (int) $contract['id'] ?>/pdf">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Régénérer PDF</button>
        </form>
        <?php if ($pdfDoc !== null): ?>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/<?= (int) $pdfDoc['id'] ?>/preview">
                <i class="bi bi-eye me-1"></i>Aperçu
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="/pdf/<?= (int) $pdfDoc['id'] ?>/download">
                <i class="bi bi-download me-1"></i>Télécharger
            </a>
        <?php endif; ?>
    </div>
</div>

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
                        <span><i class="bi bi-file-earmark-pdf text-danger me-1"></i><?= e($pdfRow['name']) ?>
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
            <div class="card mt-3">
                <div class="card-header">Notes</div>
                <div class="card-body small"><?= nl2br(e((string) $contract['notes'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
