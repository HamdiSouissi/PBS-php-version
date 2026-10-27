<?php

declare(strict_types=1);
/** @var list<array<string,mixed>> $employees @var string $q @var string $status */
$statusLabels = ['candidate' => 'Candidat', 'active' => 'Actif', 'on_leave' => 'En congé', 'suspended' => 'Suspendu', 'terminated' => 'Sorti'];
$badges = ['candidate' => 'secondary', 'active' => 'success', 'on_leave' => 'warning', 'suspended' => 'dark', 'terminated' => 'danger'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Salariés</h2>
    <a href="/employees/create" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Nouveau salarié</a>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-5"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Nom, prénom, matricule…"></div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" name="status">
            <option value="">Tous statuts</option>
            <?php foreach ($statusLabels as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary btn-sm">Filtrer</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th>Matricule</th><th>Nom</th><th>Poste</th><th>Service</th><th>Statut</th><th>Embauche</th><th></th></tr>
            </thead>
            <tbody>
            <?php if ($employees === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun salarié.</td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $employee): ?>
                <tr>
                    <td class="small"><?= e($employee['employee_number']) ?></td>
                    <td><a href="/employees/<?= (int) $employee['id'] ?>" class="text-decoration-none fw-semibold">
                        <?= e(mb_strtoupper((string) $employee['last_name']) . ' ' . $employee['first_name']) ?></a></td>
                    <td><?= e($employee['job_title']) ?></td>
                    <td class="small"><?= e($employee['department_name'] ?? '—') ?></td>
                    <td><span class="badge text-bg-<?= e($badges[$employee['status']] ?? 'secondary') ?>">
                        <?= e($statusLabels[$employee['status']] ?? $employee['status']) ?></span></td>
                    <td class="small"><?= e($employee['hire_date'] ? date('d/m/Y', strtotime((string) $employee['hire_date'])) : '—') ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="/employees/<?= (int) $employee['id'] ?>"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
