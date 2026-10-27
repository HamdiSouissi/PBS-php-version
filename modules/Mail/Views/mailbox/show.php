<?php

declare(strict_types=1);

/**
 * @var \Modules\Mail\Models\MailMessage $message
 * @var list<\Modules\Mail\Models\MailFolder> $folders
 * @var string $folder
 */
$replySubject = str_starts_with($message->subject, 'Re:') ? $message->subject : 'Re: ' . $message->subject;
$replyTo = $message->fromEmail;
?>
<?= $viewRenderer->pageHeader($message->subject, [
    ['label' => 'Boîte mail', 'href' => '/mail?folder=' . urlencode($folder)],
    ['label' => $folder],
]) ?>

<div class="page-body">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <a href="/mail?folder=<?= urlencode($folder) ?>" class="btn btn-sm btn-outline-secondary">
                <i data-lucide="arrow-left"></i> Retour
            </a>
            <a class="btn btn-sm btn-primary"
               href="/mail/compose?to=<?= urlencode($replyTo) ?>&subject=<?= urlencode($replySubject) ?>">
                <i data-lucide="reply"></i> Répondre
            </a>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <form method="post" action="/mail/mark" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folder" value="<?= e($folder) ?>">
                    <input type="hidden" name="id" value="<?= $message->sequence ?>">
                    <input type="hidden" name="read" value="0">
                    <button class="btn btn-sm btn-outline-secondary">Marquer non lu</button>
                </form>
                <form method="post" action="/mail/move" class="d-inline d-flex gap-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folder" value="<?= e($folder) ?>">
                    <input type="hidden" name="id" value="<?= $message->sequence ?>">
                    <select name="destination" class="form-select form-select-sm" required>
                        <option value="">Déplacer vers…</option>
                        <?php foreach ($folders as $destination): ?>
                            <?php if ($destination->name === $folder) {
                                continue;
                            } ?>
                            <option value="<?= e($destination->name) ?>"><?= e($destination->label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary">OK</button>
                </form>
                <form method="post" action="/mail/delete" class="d-inline"
                      onsubmit="return confirm('Supprimer ce message ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folder" value="<?= e($folder) ?>">
                    <input type="hidden" name="id" value="<?= $message->sequence ?>">
                    <button class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button>
                </form>
            </div>
        </div>

        <div class="card-body border-bottom">
            <h5 class="mb-2"><?= e($message->subject) ?></h5>
            <dl class="row mb-0 small">
                <dt class="col-sm-1">De</dt>
                <dd class="col-sm-11">
                    <?= e($message->fromName !== '' ? $message->fromName . ' <' . $message->fromEmail . '>' : $message->fromEmail) ?>
                </dd>
                <?php if ($message->to !== []): ?>
                    <dt class="col-sm-1">À</dt>
                    <dd class="col-sm-11">
                        <?= e(implode(', ', array_map(static fn (array $a): string => $a['email'], $message->to))) ?>
                    </dd>
                <?php endif; ?>
                <dt class="col-sm-1">Date</dt>
                <dd class="col-sm-11"><?= e($message->dateText ?? '') ?></dd>
            </dl>
        </div>

        <div class="card-body">
            <?php if ($message->htmlBody !== ''): ?>
                <iframe sandbox="" class="mail-html-frame w-100 border-0" style="min-height:420px"
                        srcdoc="<?= e($message->htmlBody) ?>" title="Contenu du message"></iframe>
            <?php else: ?>
                <div class="mail-text-body" style="white-space:pre-wrap"><?= e($message->textBody) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($message->attachments !== []): ?>
            <div class="card-body border-top">
                <h6 class="text-muted small text-uppercase">Pièces jointes (<?= count($message->attachments) ?>)</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($message->attachments as $position => $attachment): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="/mail/attachment?folder=<?= urlencode($folder) ?>&id=<?= $message->sequence ?>&pos=<?= $position ?>">
                            <i data-lucide="paperclip"></i>
                            <?= e($attachment->filename) ?>
                            <span class="text-muted">(<?= number_format($attachment->size / 1024, 1) ?> Ko)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
