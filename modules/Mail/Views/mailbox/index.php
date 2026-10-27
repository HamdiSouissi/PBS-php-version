<?php

declare(strict_types=1);

/**
 * @var list<\Modules\Mail\Models\MailFolder> $folders
 * @var array<string,mixed> $result
 * @var string $folder
 * @var string $query
 * @var bool $hasMailbox
 */
$messages = $result['messages'];
?>
<?= $viewRenderer->pageHeader('Boîte mail', [['label' => 'Messagerie']]) ?>

<div class="page-body">
<?php if (!$hasMailbox): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="mail-warning" class="text-muted" style="width:42px;height:42px"></i>
            <h5 class="mt-3">Aucune boîte mail ne vous est affectée</h5>
            <p class="text-muted mb-0">Contactez le gestionnaire de l'application pour qu'il configure votre adresse professionnelle.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body p-2">
                    <a href="/mail/compose" class="btn btn-primary w-100 mb-2">
                        <i data-lucide="pen-square" class="me-1"></i> Écrire
                    </a>
                    <nav class="nav flex-column nav-mail">
                        <?php foreach ($folders as $mailFolder): ?>
                            <a class="nav-link <?= $mailFolder->name === $folder ? 'active' : '' ?>"
                               href="/mail?folder=<?= urlencode($mailFolder->name) ?>">
                                <span><?= e($mailFolder->label) ?></span>
                                <?php if ($mailFolder->unseen > 0): ?>
                                    <span class="badge text-bg-danger rounded-pill"><?= $mailFolder->unseen ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h6 class="mb-0 me-auto"><?= e($folder === 'INBOX' ? 'Boîte de réception' : $folder) ?>
                        <span class="text-muted small">(<?= (int) $result['total'] ?> message<?= $result['total'] > 1 ? 's' : '' ?>)</span>
                    </h6>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="folder" value="<?= e($folder) ?>">
                        <input type="search" class="form-control form-control-sm" name="q"
                               value="<?= e($query) ?>" placeholder="Rechercher dans le dossier…">
                        <button class="btn btn-sm btn-outline-primary"><i data-lucide="search"></i></button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                        <?php if ($messages === []): ?>
                            <tr><td class="text-center text-muted py-4">Aucun message dans ce dossier.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($messages as $message): ?>
                            <tr class="<?= $message->seen ? '' : 'table-light fw-semibold' ?>">
                                <td class="text-center" style="width:42px">
                                    <form method="post" action="/mail/flag" title="Marqueur">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="folder" value="<?= e($folder) ?>">
                                        <input type="hidden" name="id" value="<?= $message->sequence ?>">
                                        <input type="hidden" name="flagged" value="<?= $message->flagged ? 0 : 1 ?>">
                                        <button class="btn btn-link btn-sm p-0 text-<?= $message->flagged ? 'warning' : 'muted' ?>">
                                            <i data-lucide="star"></i>
                                        </button>
                                    </form>
                                </td>
                                <td style="width:220px">
                                    <a class="text-decoration-none text-reset"
                                       href="/mail/message?folder=<?= urlencode($folder) ?>&id=<?= $message->sequence ?>">
                                        <?= e($message->fromName !== '' ? $message->fromName : $message->fromEmail) ?>
                                    </a>
                                </td>
                                <td>
                                    <a class="text-decoration-none text-reset d-block text-truncate" style="max-width:420px"
                                       href="/mail/message?folder=<?= urlencode($folder) ?>&id=<?= $message->sequence ?>">
                                        <?= e($message->subject) ?>
                                    </a>
                                </td>
                                <td class="text-end small text-muted text-nowrap" style="width:130px">
                                    <?= e($message->dateText ?? '') ?>
                                </td>
                                <td class="text-end" style="width:90px">
                                    <form method="post" action="/mail/delete" class="d-inline"
                                          onsubmit="return confirm('Supprimer ce message ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="folder" value="<?= e($folder) ?>">
                                        <input type="hidden" name="id" value="<?= $message->sequence ?>">
                                        <button class="btn btn-sm btn-link text-danger p-0" title="Supprimer">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($result['pages'] > 1): ?>
                    <div class="card-body">
                        <nav aria-label="Pagination"><ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                                <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="/mail?folder=<?= urlencode($folder) ?>&q=<?= urlencode($query) ?>&page=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul></nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
