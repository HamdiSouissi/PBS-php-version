<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $clients
 * @var string $q
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Clients</h2>
        <p class="text-muted mb-0"><?= count($clients) ?> client(s) — espace de gestion commerciale.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="/clients/new"><i class="bi bi-plus-lg me-1"></i>Nouveau client</a>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" class="d-flex gap-2">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= e($q) ?>"
                   placeholder="Rechercher par nom, code ou ville…">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Filtrer</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
            <tr>
                <th>Code</th><th>Raison sociale</th><th>Ville</th><th class="text-end">Factures</th>
                <th class="text-end">Total facturé TTC</th><th class="text-end">Encours</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($clients === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun client. Créez le premier client pour démarrer.</td></tr>
            <?php endif; ?>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td class="fw-semibold"><?= e($client['code']) ?></td>
                    <td><a class="text-decoration-none" href="/clients/<?= (int) $client['id'] ?>"><?= e($client['name']) ?></a></td>
                    <td><?= e(trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')) ?: '—') ?></td>
                    <td class="text-end"><?= (int) $client['invoice_count'] ?></td>
                    <td class="text-end"><?= money_format((float) $client['billed_ttc']) ?></td>
                    <td class="text-end">
                        <?php if ((float) $client['outstanding'] > 0): ?>
                            <span class="badge text-bg-warning"><?= money_format((float) $client['outstanding']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="/clients/<?= (int) $client['id'] ?>">
                            <i class="bi bi-folder2-open me-1"></i>Ouvrir
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
