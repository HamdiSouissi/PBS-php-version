<?php

declare(strict_types=1);

/**
 * @var array<string,mixed>|null $account
 * @var list<array<string,mixed>> $availableUsers
 * @var array<string,mixed> $defaults
 * @var list<string> $encryptions
 */
$errors = $container->get(\App\Core\Session\FlashBag::class)->errors();
$isEdit = $account !== null;
$action = $isEdit ? '/mail/admin/accounts/' . (int) $account['id'] : '/mail/admin/accounts';

$val = static function (string $key, mixed $default = '') use ($account): string {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return (string) $fromOld;
    }
    if ($account !== null && array_key_exists($key, $account)) {
        return (string) $account[$key];
    }
    return (string) $default;
};
$encryptionLabels = ['ssl' => 'SSL/TLS implicite', 'tls' => 'STARTTLS', 'none' => 'Aucun (non sécurisé)'];
$activeChecked = (bool) $val('is_active', '1');
?>
<?= $viewRenderer->pageHeader($isEdit ? 'Modifier la boîte mail' : 'Nouvelle boîte mail', [
    ['label' => 'Comptes mail', 'href' => '/mail/admin/accounts'],
    ['label' => $isEdit ? 'Modification' : 'Création'],
]) ?>

<div class="page-body">
    <div class="card">
        <div class="card-body">
            <?php if (isset($errors['form'])): ?>
                <div class="alert alert-danger"><?= e($errors['form']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e($action) ?>" autocomplete="off">
                <?= csrf_field() ?>

                <h6 class="text-uppercase text-muted small">Affectation</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="user_id">Utilisateur <span class="text-danger">*</span></label>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                            <input class="form-control" value="<?= e(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')) ?>" disabled>
                        <?php else: ?>
                            <select class="form-control <?= isset($errors['user_id']) ? 'is-invalid' : '' ?>" name="user_id" id="user_id" required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?= (int) $user['id'] ?>" <?= $val('user_id') === (string) $user['id'] ? 'selected' : '' ?>>
                                        <?= e($user['last_name'] . ' ' . $user['first_name'] . ' (' . $user['email'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($availableUsers === []): ?>
                                <div class="form-text text-warning">Tous les utilisateurs possèdent déjà une boîte mail.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (isset($errors['user_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['user_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="is_active">État</label>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Boîte active</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="email">Adresse e-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email"
                               name="email" value="<?= e($val('email')) ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="display_name">Nom affiché</label>
                        <input type="text" class="form-control" id="display_name" name="display_name"
                               value="<?= e($val('display_name')) ?>">
                    </div>
                </div>

                <h6 class="text-uppercase text-muted small mt-4">Serveur entrant (IMAP)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="imap_host">Hôte</label>
                        <input class="form-control" id="imap_host" name="imap_host"
                               value="<?= e($val('imap_host', $defaults['imap']['host'])) ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label" for="imap_port">Port</label>
                        <input type="number" class="form-control" id="imap_port" name="imap_port"
                               value="<?= e($val('imap_port', $defaults['imap']['port'])) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="imap_encryption">Chiffrement</label>
                        <select class="form-control" id="imap_encryption" name="imap_encryption">
                            <?php foreach ($encryptions as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $val('imap_encryption', $defaults['imap']['encryption']) === $mode ? 'selected' : '' ?>>
                                    <?= e($encryptionLabels[$mode]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="imap_username">Identifiant</label>
                        <input class="form-control" id="imap_username" name="imap_username"
                               value="<?= e($val('imap_username', $val('email'))) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="imap_password">Mot de passe IMAP <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" class="form-control <?= isset($errors['imap_password']) ? 'is-invalid' : '' ?>"
                               id="imap_password" name="imap_password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($isEdit): ?><div class="form-text">Laissez vide pour conserver le mot de passe actuel.</div><?php endif; ?>
                        <?php if (isset($errors['imap_password'])): ?><div class="invalid-feedback d-block"><?= e($errors['imap_password']) ?></div><?php endif; ?>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted small mt-4">Serveur sortant (SMTP)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="smtp_host">Hôte</label>
                        <input class="form-control" id="smtp_host" name="smtp_host"
                               value="<?= e($val('smtp_host', $defaults['smtp']['host'])) ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label" for="smtp_port">Port</label>
                        <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                               value="<?= e($val('smtp_port', $defaults['smtp']['port'])) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="smtp_encryption">Chiffrement</label>
                        <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                            <?php foreach ($encryptions as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $val('smtp_encryption', $defaults['smtp']['encryption']) === $mode ? 'selected' : '' ?>>
                                    <?= e($encryptionLabels[$mode]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="smtp_username">Identifiant</label>
                        <input class="form-control" id="smtp_username" name="smtp_username"
                               value="<?= e($val('smtp_username', $val('email'))) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="smtp_password">Mot de passe SMTP <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" class="form-control <?= isset($errors['smtp_password']) ? 'is-invalid' : '' ?>"
                               id="smtp_password" name="smtp_password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($isEdit): ?><div class="form-text">Laissez vide pour conserver le mot de passe actuel.</div><?php endif; ?>
                        <?php if (isset($errors['smtp_password'])): ?><div class="invalid-feedback d-block"><?= e($errors['smtp_password']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary"><i data-lucide="save"></i> Enregistrer</button>
                    <a href="/mail/admin/accounts" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>
