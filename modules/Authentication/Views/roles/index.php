<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $roles
 * @var array<string,list<array<string,string>>> $matrix
 * @var array<string,string> $moduleLabels
 * @var array<string,mixed>|null $editing
 */
$checked = static fn (array $permission): string => (
    $editing !== null && in_array($permission['code'], $editing['permission_codes'], true)
) ? 'checked' : '';
$actions = '<button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newRoleCard" aria-expanded="false" aria-controls="newRoleCard">
        <i data-lucide="shield-plus"></i>Nouveau rôle
    </button>';
?>
<?= $viewRenderer->pageHeader(
    'Rôles & permissions',
    [['label' => 'Gouvernance']],
    $actions,
    'Chaque utilisateur hérite des permissions des rôles qui lui sont attribués. Les entrées du menu latéral suivent ces mêmes permissions.'
) ?>

<div class="page-body">
<div class="collapse" id="newRoleCard">
    <div class="card">
        <div class="card-header">Créer un rôle</div>
        <form method="post" action="/settings/roles" data-guard>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Code *</label>
                        <input class="form-control form-control-sm" name="code" required pattern="[a-zA-Z0-9_\-]+" placeholder="ex : commercial">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Libellé *</label>
                        <input class="form-control form-control-sm" name="label" required maxlength="120" placeholder="ex : Commercial">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Description</label>
                        <input class="form-control form-control-sm" name="description" maxlength="500">
                    </div>
                </div>
                <?php foreach ($matrix as $module => $permissions): ?>
                    <div class="mb-2">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><?= e($moduleLabels[$module] ?? $module) ?></div>
                        <div class="row g-1">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]"
                                               value="<?= e($permission['code']) ?>" id="new-<?= e($permission['code']) ?>">
                                        <label class="form-check-label small" for="new-<?= e($permission['code']) ?>">
                                            <?= e($permission['label']) ?> <code class="small text-muted"><?= e($permission['code']) ?></code>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary btn-sm">Créer le rôle</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Rôles existants</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-sortable>
            <thead class="table-light">
            <tr><th>Libellé</th><th>Code</th><th>Description</th><th class="text-end">Permissions</th><th class="text-end">Utilisateurs</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td class="fw-semibold"><?= e($role['label']) ?></td>
                    <td><code><?= e($role['code']) ?></code></td>
                    <td class="small text-muted"><?= e($role['description']) ?></td>
                    <td class="text-end"><span class="badge text-bg-light border"><?= (int) $role['permission_count'] ?></span></td>
                    <td class="text-end"><span class="badge text-bg-<?= (int) $role['user_count'] > 0 ? 'info' : 'secondary' ?>"><?= (int) $role['user_count'] ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="/settings/roles?edit=<?= (int) $role['id'] ?>">
                            <i data-lucide="pencil"></i>Modifier
                        </a>
                        <?php if ((int) $role['user_count'] === 0): ?>
                            <form method="post" action="/settings/roles/<?= (int) $role['id'] ?>/delete" class="d-inline"
                                  data-confirm="Supprimer le rôle « <?= e($role['label']) ?> » ?"
                                  data-confirm-title="Suppression d'un rôle">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" aria-label="Supprimer le rôle"><i data-lucide="trash-2"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($editing !== null): ?>
<div class="card" id="edit-card" data-scroll-into-view>
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Modifier le rôle « <?= e($editing['label']) ?> » <code class="small"><?= e($editing['code']) ?></code></span>
        <a class="btn btn-sm btn-outline-secondary" href="/settings/roles">Fermer</a>
    </div>
    <form method="post" action="/settings/roles/<?= (int) $editing['id'] ?>/update" data-guard>
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Libellé *</label>
                    <input class="form-control form-control-sm" name="label" required maxlength="120" value="<?= e($editing['label']) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Description</label>
                    <input class="form-control form-control-sm" name="description" maxlength="500" value="<?= e($editing['description']) ?>">
                </div>
            </div>
            <?php foreach ($matrix as $module => $permissions): ?>
                <div class="mb-2">
                    <div class="small fw-semibold text-uppercase text-muted mb-1"><?= e($moduleLabels[$module] ?? $module) ?></div>
                    <div class="row g-1">
                        <?php foreach ($permissions as $permission): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           value="<?= e($permission['code']) ?>" id="edit-<?= e($permission['code']) ?>" <?= $checked($permission) ?>>
                                    <label class="form-check-label small" for="edit-<?= e($permission['code']) ?>">
                                        <?= e($permission['label']) ?> <code class="small text-muted"><?= e($permission['code']) ?></code>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary btn-sm"><i data-lucide="circle-check"></i>Enregistrer les permissions</button>
        </div>
    </form>
</div>
<?php endif; ?>
</div>
