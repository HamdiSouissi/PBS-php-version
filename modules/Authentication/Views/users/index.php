<?php

declare(strict_types=1);

/**
 * @var \App\Core\Pagination\Paginator $paginator
 * @var list<array<string,mixed>> $roles
 */
$actions = '<button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newUserCard" aria-expanded="false" aria-controls="newUserCard">
        <i data-lucide="user-plus"></i>Nouvel utilisateur
    </button>';
?>
<?= $viewRenderer->pageHeader('Utilisateurs & rôles', [['label' => 'Gouvernance']], $actions) ?>

<div class="page-body">
<div class="collapse" id="newUserCard">
    <div class="card card-body">
        <form method="post" action="/settings/users" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="E-mail" required></div>
            <div class="col-md-2"><input class="form-control" name="first_name" placeholder="Prénom" required></div>
            <div class="col-md-2"><input class="form-control" name="last_name" placeholder="Nom" required></div>
            <div class="col-md-2"><input class="form-control" name="password" type="password" placeholder="Mot de passe" required minlength="12"></div>
            <div class="col-md-2">
                <select name="roles[]" class="form-select" multiple size="1" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"><?= e($role['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-success">Créer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-sortable>
            <thead class="table-light">
            <tr>
                <th>Utilisateur</th><th>E-mail</th><th>Rôles</th><th>2FA</th><th>Statut</th><th>Dernière connexion</th><th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($paginator->items as $row): ?>
                <tr>
                    <td>
                        <span class="avatar me-2"><?= e(mb_strtoupper(mb_substr((string) $row['first_name'], 0, 1) . mb_substr((string) $row['last_name'], 0, 1))) ?></span>
                        <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                    </td>
                    <td><?= e($row['email']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e(str_replace(',', ', ', (string) ($row['role_codes'] ?? '—'))) ?></span></td>
                    <td>
                        <?php if (!empty($row['totp_enrolled']) && $row['totp_enrolled'] !== 'f' && $row['totp_enrolled'] !== false): ?>
                            <span class="badge text-bg-success">TOTP actif</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">Non activé</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= !empty($row['is_active']) && $row['is_active'] !== 'f' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= !empty($row['is_active']) && $row['is_active'] !== 'f' ? 'Actif' : 'Désactivé' ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= e($row['last_login_at'] ? date('d/m/Y H:i', strtotime((string) $row['last_login_at'])) : '—') ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#editUser<?= (int) $row['id'] ?>">
                            <i data-lucide="pencil"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<?php foreach ($paginator->items as $row): ?>
<div class="modal fade" id="editUser<?= (int) $row['id'] ?>" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="editUserTitle<?= (int) $row['id'] ?>">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/settings/users/<?= (int) $row['id'] ?>/update">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="editUserTitle<?= (int) $row['id'] ?>">Modifier <?= e($row['email']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label">Prénom</label>
                    <input class="form-control" name="first_name" value="<?= e($row['first_name']) ?>" required></div>
                <div class="col-6"><label class="form-label">Nom</label>
                    <input class="form-control" name="last_name" value="<?= e($row['last_name']) ?>" required></div>
                <div class="col-6"><label class="form-label">Rôles</label>
                    <select name="roles[]" class="form-select" multiple size="4">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>"><?= e($role['label']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-6 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act<?= (int) $row['id'] ?>"
                            <?= !empty($row['is_active']) && $row['is_active'] !== 'f' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="act<?= (int) $row['id'] ?>">Compte actif</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<p class="text-muted small mt-2">
    Page <?= $paginator->page ?>/<?= $paginator->totalPages() ?> — <?= (int) $paginator->total ?> utilisateur(s).
</p>
</div>
