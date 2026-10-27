<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $years
 * @var list<array<string,mixed>> $entries
 * @var list<array<string,mixed>> $balances
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Comptabilité</h2>
        <p class="text-muted mb-0">Exercices, écritures en partie double et balance des comptes.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="/accounting/entries/new"><i class="bi bi-journal-plus me-1"></i>Nouvelle écriture</a>
</div>

<div class="card mb-3">
    <div class="card-header">Exercices</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Exercice</th><th>Début</th><th>Fin</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($years as $year): ?>
                <tr>
                    <td><?= e($year['label']) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $year['starts_on']))) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $year['ends_on']))) ?></td>
                    <td><span class="badge text-bg-<?= $year['status'] === 'open' ? 'success' : 'secondary' ?>"><?= $year['status'] === 'open' ? 'Ouvert' : 'Clôturé' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">50 dernières écritures</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Journal</th><th>Réf.</th><th>Libellé</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php if ($entries === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucune écriture.</td></tr><?php endif; ?>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td class="small"><?= e(date('d/m/Y', strtotime((string) $entry['entry_date']))) ?></td>
                    <td><span class="badge text-bg-light border"><?= e($entry['journal']) ?></span></td>
                    <td><?= e($entry['reference']) ?></td>
                    <td><a href="/accounting/entries/<?= (int) $entry['id'] ?>"><?= e($entry['label']) ?></a></td>
                    <td>
                        <?php if ($entry['status'] === 'posted'): ?>
                            <span class="badge text-bg-success">Comptabilisée</span>
                        <?php elseif ($entry['status'] === 'cancelled'): ?>
                            <span class="badge text-bg-danger">Annulée</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e($entry['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="/accounting/entries/<?= (int) $entry['id'] ?>">Ouvrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Balance des comptes <span class="text-muted small">(écritures comptabilisées)</span></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Compte</th><th>Libellé</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde débiteur</th><th class="text-end">Solde créditeur</th></tr></thead>
            <tbody>
            <?php if ($balances === []): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucun mouvement comptabilisé.</td></tr><?php endif; ?>
            <?php foreach ($balances as $balance): ?>
                <tr>
                    <td class="fw-semibold"><?= e($balance['code']) ?></td>
                    <td><?= e($balance['label']) ?></td>
                    <td class="text-end"><?= money_format((float) $balance['total_debit']) ?></td>
                    <td class="text-end"><?= money_format((float) $balance['total_credit']) ?></td>
                    <td class="text-end"><?= (float) $balance['solde_debit'] !== 0.0 ? money_format((float) $balance['solde_debit']) : '—' ?></td>
                    <td class="text-end"><?= (float) $balance['solde_credit'] !== 0.0 ? money_format((float) $balance['solde_credit']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
