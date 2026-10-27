<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $accounts
 */
?>
<?= $viewRenderer->pageHeader('Comptes mail', [
    ['label' => 'Administration'],
    ['label' => 'Comptes mail'],
]) ?>

<div class="page-body">
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h6 class="mb-0 me-auto">Boîtes affectées (<?= count($accounts) ?>)</h6>
            <a href="/mail/admin/accounts/new" class="btn btn-sm btn-primary">
                <i data-lucide="plus"></i> Nouvelle boîte
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Utilisateur</th>
                    <th>Adresse</th>
                    <th>Nom affiché</th>
                    <th>Serveurs</th>
                    <th>État</th>
                    <th>Dernière synchro</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucune boîte mail configurée.</td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td>
                            <?= e(trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? ''))) ?>
                            <div class="small text-muted"><?= e($account['user_email'] ?? '') ?></div>
                        </td>
                        <td><?= e($account['email']) ?></td>
                        <td><?= e($account['display_name']) ?></td>
                        <td class="small text-muted">
                            IMAP <?= e($account['imap_host']) ?>:<?= (int) $account['imap_port'] ?> (<?= e($account['imap_encryption']) ?>)<br>
                            SMTP <?= e($account['smtp_host']) ?>:<?= (int) $account['smtp_port'] ?> (<?= e($account['smtp_encryption']) ?>)
                        </td>
                        <td>
                            <?php if ((int) $account['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Suspendue</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($account['last_sync_at'] ?? '—') ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="/mail/admin/accounts/<?= (int) $account['id'] ?>/edit">
                                <i data-lucide="pencil"></i>
                            </a>
                            <form method="post" action="/mail/admin/accounts/<?= (int) $account['id'] ?>/delete"
                                  class="d-inline" onsubmit="return confirm('Révoquer cette boîte mail ?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-2">
        Les mots de passe IMAP/SMTP sont chiffrés au repos et n'apparaissent jamais à l'écran.
        Laissez les champs de mot de passe vides à la modification pour conserver les secrets existants.
    </p>
</div>
