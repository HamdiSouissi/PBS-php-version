<?php

declare(strict_types=1);
/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var list<array<string,mixed>> $parties
 * @var string $type
 * @var string $q
 */
$actions = '<a href="/invoicing/third-parties/new" class="btn btn-primary btn-sm">'
    . '<i data-lucide="user-plus"></i>Nouveau tiers</a>';
?>
<?= $viewRenderer->pageHeader('Tiers — clients & fournisseurs', [['label' => 'Facturation']], $actions) ?>

<div class="page-body">
<form class="table-toolbar row g-2" method="get">
    <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nom, code, SIRET…"></div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" name="type">
            <option value="client" <?= $type === 'client' ? 'selected' : '' ?>>Clients</option>
            <option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>Fournisseurs</option>
            <option value="" <?= $type === '' ? 'selected' : '' ?>>Tous</option>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary btn-sm"><i data-lucide="search"></i>Filtrer</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" data-sortable>
            <thead class="table-light"><tr><th>Code</th><th>Nom</th><th>Type</th><th>SIRET</th><th>Échéance</th><th>Ville</th><th>Contact</th></tr></thead>
            <tbody>
            <?php if ($parties === []): ?><tr><td colspan="7" class="text-center text-muted py-3">Aucun tiers.</td></tr><?php endif; ?>
            <?php foreach ($parties as $party): ?>
                <tr>
                    <td><?= e($party['code']) ?></td>
                    <td class="fw-semibold"><?= e($party['name']) ?></td>
                    <td><span class="badge text-bg-info"><?= e($party['type']) ?></span></td>
                    <td class="small"><?= e($party['siret']) ?></td>
                    <td><?= (int) $party['payment_terms_days'] ?> j</td>
                    <td class="small"><?= e($party['postal_code'] . ' ' . $party['city']) ?></td>
                    <td class="small text-muted"><?= e($party['email']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
