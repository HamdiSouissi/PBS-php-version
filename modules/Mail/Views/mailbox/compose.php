<?php

declare(strict_types=1);

/**
 * @var string $prefillTo
 * @var string $prefillSubject
 * @var float $maxSizeMb
 * @var int $maxRecipients
 */
$errors = $container->get(\App\Core\Session\FlashBag::class)->errors();
$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'is-invalid' : '';
?>
<?= $viewRenderer->pageHeader('Nouveau message', [['label' => 'Boîte mail', 'href' => '/mail'], ['label' => 'Écrire']]) ?>

<div class="page-body">
    <div class="card">
        <div class="card-body">
            <?php if (isset($errors['form'])): ?>
                <div class="alert alert-danger"><?= e($errors['form']) ?></div>
            <?php endif; ?>
            <form method="post" action="/mail/send" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="to">À <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= $fieldClass('to') ?>" id="to" name="to"
                           value="<?= e(old('to', $prefillTo)) ?>" placeholder="destinataire@exemple.fr, autre@exemple.fr" required>
                    <div class="form-text">Plusieurs adresses séparées par des virgules (<?= $maxRecipients ?> destinataires maximum, Cc/Cci compris).</div>
                    <?php if (isset($errors['to'])): ?><div class="invalid-feedback d-block"><?= e($errors['to']) ?></div><?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="cc">Cc</label>
                        <input type="text" class="form-control <?= $fieldClass('cc') ?>" id="cc" name="cc"
                               value="<?= e(old('cc', '')) ?>">
                        <?php if (isset($errors['cc'])): ?><div class="invalid-feedback d-block"><?= e($errors['cc']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="bcc">Cci</label>
                        <input type="text" class="form-control <?= $fieldClass('bcc') ?>" id="bcc" name="bcc"
                               value="<?= e(old('bcc', '')) ?>">
                        <div class="form-text">Adresses en copie invisible, absentes des en-têtes du message.</div>
                        <?php if (isset($errors['bcc'])): ?><div class="invalid-feedback d-block"><?= e($errors['bcc']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subject">Sujet <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= $fieldClass('subject') ?>" id="subject" name="subject"
                           value="<?= e(old('subject', $prefillSubject)) ?>" required>
                    <?php if (isset($errors['subject'])): ?><div class="invalid-feedback d-block"><?= e($errors['subject']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="text_body">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= $fieldClass('text_body') ?>" id="text_body" name="text_body"
                              rows="12" required><?= e(old('text_body', '')) ?></textarea>
                    <?php if (isset($errors['text_body'])): ?><div class="invalid-feedback d-block"><?= e($errors['text_body']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="attachments">Pièces jointes</label>
                    <input type="file" class="form-control <?= $fieldClass('attachments') ?>" id="attachments"
                           name="attachments[]" multiple>
                    <div class="form-text">Taille cumulée maximum : <?= $maxSizeMb ?> Mo.</div>
                    <?php if (isset($errors['attachments'])): ?><div class="invalid-feedback d-block"><?= e($errors['attachments']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary"><i data-lucide="send"></i> Envoyer</button>
                    <a href="/mail" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>
