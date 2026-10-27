<?php

declare(strict_types=1);
/**
 * @var array<string,mixed> $invoice
 * @var list<array<string,mixed>> $bankAccounts
 * @var list<array<string,mixed>> $outbox
 */
$badges = ['draft' => 'secondary', 'issued' => 'primary', 'partial' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Facture <?= e($invoice['invoice_number']) ?>
        <span class="badge text-bg-<?= e($badges[$invoice['status']] ?? 'secondary') ?>"><?= e($invoice['status']) ?></span>
        <span class="badge text-bg-info"><?= e($invoice['electronic_status']) ?></span>
    </h2>
    <div class="btn-group">
        <?php if ($invoice['status'] === 'draft'): ?>
            <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/issue" class="d-inline">
                <?= csrf_field() ?><button class="btn btn-success btn-sm">Émettre</button>
            </form>
        <?php endif; ?>
        <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/pdf" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Régénérer PDF</button>
        </form>
        <?php if (!empty($pdfDoc)): ?>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="/pdf/<?= (int) $pdfDoc['id'] ?>/preview">
                <i class="bi bi-eye me-1"></i>Aperçu
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="/pdf/<?= (int) $pdfDoc['id'] ?>/download">
                <i class="bi bi-download me-1"></i>PDF
            </a>
        <?php endif; ?>
        <?php if (!in_array($invoice['status'], ['draft', 'cancelled', 'paid'], true)): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">Encaisser</button>
        <?php endif; ?>
        <?php if (in_array($invoice['status'], ['issued', 'partial', 'overdue'], true)): ?>
            <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/credit-note" class="d-inline"
                  onsubmit="return confirm('Créer un avoir pour cette facture ?')">
                <?= csrf_field() ?><button class="btn btn-outline-warning btn-sm">Avoir</button>
            </form>
        <?php endif; ?>
        <?php if ($invoice['status'] !== 'cancelled' && $invoice['status'] !== 'paid'): ?>
            <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/cancel" class="d-inline"
                  onsubmit="return confirm('Annuler cette facture ?')">
                <?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Annuler</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Lignes</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Libellé</th><th class="text-end">Qté</th><th class="text-end">P.U. HT</th><th class="text-end">TVA</th><th class="text-end">Total HT</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoice['lines'] as $line): ?>
                        <tr>
                            <td><?= e($line['label']) ?></td>
                            <td class="text-end"><?= e((string) (float) $line['quantity']) ?></td>
                            <td class="text-end"><?= money_format((float) $line['unit_price']) ?></td>
                            <td class="text-end"><?= e((string) (float) $line['vat_rate']) ?> %</td>
                            <td class="text-end"><?= money_format((float) $line['line_ht']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr><td colspan="4" class="text-end fw-semibold">Total HT</td><td class="text-end"><?= money_format((float) $invoice['total_ht']) ?></td></tr>
                    <tr><td colspan="4" class="text-end">TVA</td><td class="text-end"><?= money_format((float) $invoice['total_vat']) ?></td></tr>
                    <tr class="table-light"><td colspan="4" class="text-end fw-bold">Total TTC</td><td class="text-end fw-bold"><?= money_format((float) $invoice['total_ttc']) ?></td></tr>
                    <tr><td colspan="4" class="text-end text-success">Encaissé</td><td class="text-end text-success"><?= money_format((float) $invoice['amount_paid']) ?></td></tr>
                    <tr><td colspan="4" class="text-end text-danger fw-semibold">Solde dû</td><td class="text-end text-danger fw-semibold"><?= money_format((float) $invoice['balance']) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Paiements</div>
            <ul class="list-group list-group-flush small">
                <?php if ($invoice['payments'] === []): ?><li class="list-group-item text-muted">Aucun encaissement.</li><?php endif; ?>
                <?php foreach ($invoice['payments'] as $payment): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e(date('d/m/Y', strtotime((string) $payment['paid_on']))) ?> — <?= e($payment['method']) ?>
                            <?php if ($payment['reference'] !== ''): ?>(<?= e($payment['reference']) ?>)<?php endif; ?></span>
                        <strong><?= money_format((float) $payment['amount']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Client</div>
            <div class="card-body small">
                <div class="fw-semibold"><?= e($invoice['third_party_name']) ?></div>
                <div class="text-muted"><?= e($invoice['address']) ?><br><?= e($invoice['postal_code'] . ' ' . $invoice['city']) ?></div>
                <div class="mt-2">SIRET : <?= e($invoice['siret']) ?></div>
                <div>TVA : <?= e($invoice['vat_number']) ?></div>
                <div class="mt-2">Émission : <?= e(date('d/m/Y', strtotime((string) $invoice['issue_date']))) ?></div>
                <div>Échéance : <?= e(date('d/m/Y', strtotime((string) $invoice['due_date']))) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Facturation électronique</div>
            <div class="card-body">
                <?php if (in_array($invoice['status'], ['issued', 'partial', 'overdue', 'paid'], true)): ?>
                    <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/e-invoice" class="d-grid gap-2 mb-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="format" value="UBL">
                        <button class="btn btn-outline-primary btn-sm">Générer UBL 2.1 (EN 16931)</button>
                    </form>
                    <form method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/e-invoice" class="d-grid gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="format" value="CII">
                        <button class="btn btn-outline-secondary btn-sm">Générer CII (UN/CEFACT)</button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-0">La dématérialisation est disponible après émission.</p>
                <?php endif; ?>
                <hr>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($outbox as $message): ?>
                        <li class="mb-2">
                            <div class="fw-semibold"><?= e($message['format']) ?> — <span class="badge text-bg-secondary"><?= e($message['status']) ?></span></div>
                            <div class="text-muted">SHA-256 : <code class="small"><?= e(substr((string) $message['checksum_sha256'], 0, 16)) ?>…</code></div>
                            <a href="/invoicing/e-invoice/<?= (int) $message['id'] ?>/download" class="small">Télécharger le XML</a>
                            <?php if ($message['status'] === 'generated'): ?>
                                <form method="post" action="/invoicing/e-invoice/<?= (int) $message['id'] ?>/exported" class="mt-1">
                                    <?= csrf_field() ?>
                                    <input name="external_reference" class="form-control form-control-sm mb-1" placeholder="Référence de dépôt externe">
                                    <button class="btn btn-sm btn-outline-success w-100">Marquer exporté (dépôt manuel PPF/PDP)</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="small text-warning mt-2 mb-0">⚠ La télétransmission PPF/PDP/Chorus Pro n'est pas intégrée : aucun accusvé n'est fabriqué, la preuve de dépôt reste externe.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/invoicing/invoices/<?= (int) $invoice['id'] ?>/payments">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Encaisser un paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-6"><label class="form-label">Montant (€)</label>
                    <input type="number" step="0.01" min="0.01" max="<?= e((string) $invoice['balance']) ?>" name="amount" class="form-control" value="<?= e((string) $invoice['balance']) ?>" required></div>
                <div class="col-6"><label class="form-label">Date</label><input type="date" name="paid_on" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-6"><label class="form-label">Mode</label>
                    <select name="method" class="form-select">
                        <option value="virement">Virement</option><option value="cheque">Chèque</option>
                        <option value="prelevement">Prélèvement</option><option value="carte">Carte</option><option value="especes">Espèces</option>
                    </select></div>
                <div class="col-6"><label class="form-label">Compte bancaire</label>
                    <select name="bank_account_id" class="form-select">
                        <option value="">— Aucun —</option>
                        <?php foreach ($bankAccounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"><?= e($account['label']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-12"><label class="form-label">Référence</label><input name="reference" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Valider l'encaissement</button></div>
        </form>
    </div>
</div>
