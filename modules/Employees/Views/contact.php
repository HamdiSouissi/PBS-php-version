<?php

declare(strict_types=1);
$id = (int) $employee['id'];
$fields = [
    'personal_email' => ['E-mail personnel', 'email', 180],
    'professional_email' => ['E-mail professionnel', 'email', 180],
    'phone' => ['Téléphone', 'text', 40],
    'address' => ['Adresse', 'text', 255],
    'postal_code' => ['Code postal', 'text', 20],
    'city' => ['Ville', 'text', 120],
    'iban' => ['IBAN', 'text', 34],
    'bic' => ['BIC', 'text', 11],
];
?>
<?= $viewRenderer->pageHeader('Modifier les coordonnées', [['label' => 'Salariés', 'href' => '/employees'], ['label' => $employee['first_name'] . ' ' . $employee['last_name'], 'href' => '/employees/' . $id]]) ?>
<div class="page-body form-narrow"><div class="card"><div class="card-body">
    <p class="text-muted small">La modification des e-mails de la fiche ne change pas l’identifiant du compte de connexion.</p>
    <form method="post" action="/employees/<?= $id ?>/contact" class="row g-3" data-guard>
        <?= csrf_field() ?>
        <?php foreach ($fields as $name => [$label, $type, $max]): ?>
            <div class="col-md-6"><label class="form-label" for="contact-<?= e($name) ?>"><?= e($label) ?></label>
                <input class="form-control" id="contact-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" value="<?= e($employee[$name] ?? '') ?>"></div>
        <?php endforeach; ?>
        <div class="col-12"><button class="btn btn-primary" type="submit">Enregistrer</button> <a class="btn btn-outline-secondary" href="/employees/<?= $id ?>">Annuler</a></div>
    </form>
</div></div></div>
