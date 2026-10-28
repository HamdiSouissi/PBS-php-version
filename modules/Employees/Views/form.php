<?php

declare(strict_types=1);
/** @var ?array<string,mixed> $employee @var list<array<string,mixed>> $departments @var list<array<string,mixed>> $establishments */
$action = $employee ? '/employees/' . $employee['id'] . '/update' : '/employees';
$formTitle = $employee ? 'Modifier le salarié' : 'Nouveau salarié';
$headerActions = '<a href="/employees" class="btn btn-outline-secondary btn-sm">Annuler</a>'
    . '<button form="employee-form" class="btn btn-primary btn-sm" type="submit"><i data-lucide="save"></i>Enregistrer</button>';
?>
<?= $viewRenderer->pageHeader($formTitle, [['label' => 'Salariés', 'href' => '/employees']], $headerActions) ?>

<div class="page-body form-narrow">
<div class="card">
    <div class="card-body">
        <form method="post" action="<?= e($action) ?>" class="row g-3" id="employee-form" data-guard>
            <?= csrf_field() ?>
            <?php if ($employee === null): ?>
                <div class="col-12">
                    <div class="alert alert-info py-2 small mb-0">
                        <i data-lucide="info"></i>
                        Un compte de connexion (rôle « salarié ») est créé automatiquement à l'enregistrement,
                        avec le mot de passe temporaire <code>change_me@</code>, à changer à la première connexion.
                    </div>
                </div>
            <?php endif; ?>
            <?php
            $sections = [
                'État civil' => [
                    'last_name' => ['Nom', 'text', true], 'first_name' => ['Prénom', 'text', true],
                    'birth_date' => ['Date de naissance', 'date', false], 'birth_place' => ['Lieu de naissance', 'text', false],
                    'gender' => ['Genre', 'select', false], 'nationality' => ['Nationalité', 'text', false],
                    'social_security_number' => ['N° sécurité sociale (15 chiffres)', 'text', false],
                ],
                'Coordonnées' => [
                    'personal_email' => ['E-mail personnel', 'email', false], 'professional_email' => ['E-mail professionnel', 'email', $employee === null],
                    'phone' => ['Téléphone', 'text', false], 'address' => ['Adresse', 'text', false],
                    'postal_code' => ['Code postal', 'text', false], 'city' => ['Ville', 'text', false],
                ],
                'Poste' => [
                    'job_title' => ['Intitulé de poste', 'text', false], 'qualification' => ['Qualification', 'text', false],
                    'coefficient' => ['Coefficient', 'text', false], 'hire_date' => ['Date d\'embauche', 'date', false],
                    'status' => ['Statut', 'select', false],
                    'department_id' => ['Service', 'select', false], 'establishment_id' => ['Établissement', 'select', false],
                ],
                'Bancaire' => [
                    'iban' => ['IBAN', 'text', false], 'bic' => ['BIC', 'text', false],
                ],
            ];
            foreach ($sections as $section => $fields): ?>
                <div class="col-12"><h6 class="text-muted text-uppercase small mt-2"><?= e($section) ?></h6><hr class="mt-1"></div>
                <?php foreach ($fields as $name => [$label, $type, $required]): ?>
                    <div class="col-md-4">
                        <label class="form-label small"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
                        <?php if ($name === 'gender'): ?>
                            <select class="form-select form-select-sm" name="gender">
                                <?php foreach (['non_renseigne' => 'Non renseigné', 'm' => 'Masculin', 'f' => 'Féminin'] as $code => $lib): ?>
                                    <option value="<?= e($code) ?>" <?= ($employee['gender'] ?? '') === $code ? 'selected' : '' ?>><?= e($lib) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($name === 'status'): ?>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['candidate' => 'Candidat', 'active' => 'Actif', 'on_leave' => 'En congé', 'suspended' => 'Suspendu', 'terminated' => 'Sorti'] as $code => $lib): ?>
                                    <option value="<?= e($code) ?>" <?= ($employee['status'] ?? 'candidate') === $code ? 'selected' : '' ?>><?= e($lib) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($name === 'department_id'): ?>
                            <select class="form-select form-select-sm" name="department_id">
                                <option value="">—</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" <?= (int) ($employee['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($name === 'establishment_id'): ?>
                            <select class="form-select form-select-sm" name="establishment_id">
                                <option value="">—</option>
                                <?php foreach ($establishments as $es): ?>
                                    <option value="<?= (int) $es['id'] ?>" <?= (int) ($employee['establishment_id'] ?? 0) === (int) $es['id'] ? 'selected' : '' ?>><?= e($es['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="<?= e($type) ?>" class="form-control form-control-sm" name="<?= e($name) ?>"
                                   value="<?= e($employee[$name] ?? '') ?>" <?= $required ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </form>
    </div>
</div>
</div>
