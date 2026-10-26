<?php

declare(strict_types=1);
/**
 * @var array<string,mixed> $employee
 * @var list<array<string,mixed>> $contracts
 * @var ?array<string,mixed> $activeContract
 * @var list<array<string,mixed>> $documents
 */
$contractLabels = ['CDI' => 'CDI', 'CDD' => 'CDD', 'apprentissage' => 'Apprentissage', 'professionnalisation' => 'Professionnalisation', 'stage' => 'Stage', 'alternance' => 'Alternance', 'interim' => 'Intérim'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-0"><?= e(mb_strtoupper((string) $employee['last_name']) . ' ' . $employee['first_name']) ?></h2>
        <span class="text-muted small"><?= e($employee['employee_number']) ?> · <?= e($employee['job_title']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#portalModal">
            <i class="bi bi-person-badge me-1"></i>Portail salarié
        </button>
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#terminateModal">
            <i class="bi bi-box-arrow-right me-1"></i>Sortie
        </button>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Informations</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>Statut</span><strong><?= e($employee['status']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Naissance</span><span><?= e($employee['birth_date'] ? date('d/m/Y', strtotime((string) $employee['birth_date'])) : '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>NIR</span><span><?= e($employee['social_security_number'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>E-mail perso</span><span><?= e($employee['personal_email'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>E-mail pro</span><span><?= e($employee['professional_email'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Téléphone</span><span><?= e($employee['phone'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Service</span><span><?= e($employee['department_name'] ?? '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Établissement</span><span><?= e($employee['establishment_name'] ?? '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Embauche</span><span><?= e($employee['hire_date'] ? date('d/m/Y', strtotime((string) $employee['hire_date'])) : '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>IBAN</span><span><?= e($employee['iban'] ?: '—') ?></span></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Contrats de travail</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#contractModal">Nouveau contrat</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th>Période</th><th>Durée hebdo.</th><th>Salaire brut</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($contracts === []): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucun contrat.</td></tr><?php endif; ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><?= e($contractLabels[$contract['type']] ?? $contract['type']) ?></td>
                            <td class="small"><?= e(date('d/m/Y', strtotime((string) $contract['start_date']))) ?>
                                → <?= e($contract['end_date'] ? date('d/m/Y', strtotime((string) $contract['end_date'])) : 'indéterminée') ?></td>
                            <td><?= e($contract['weekly_hours']) ?> h</td>
                            <td><?= e(money_format($contract['monthly_gross'])) ?></td>
                            <td><span class="badge text-bg-<?= $contract['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($contract['status']) ?></span></td>
                            <td>
                                <?php if ($contract['status'] !== 'active'): ?>
                                    <form method="post" action="/employees/<?= (int) $employee['id'] ?>/contracts/<?= (int) $contract['id'] ?>/activate">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-success">Activer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Documents RH</div>
            <ul class="list-group list-group-flush small">
                <?php if ($documents === []): ?><li class="list-group-item text-muted">Aucun document.</li><?php endif; ?>
                <?php foreach ($documents as $document): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($document['name']) ?></span>
                        <span class="text-muted"><?= e(date('d/m/Y', strtotime((string) $document['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Modal contrat -->
<div class="modal fade" id="contractModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post" action="/employees/<?= (int) $employee['id'] ?>/contracts">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Nouveau contrat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-md-4"><label class="form-label small">Type</label>
                    <select class="form-select form-select-sm" name="type" required>
                        <?php foreach ($contractLabels as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label small">Début</label><input type="date" class="form-control form-control-sm" name="start_date" required></div>
                <div class="col-md-4"><label class="form-label small">Fin (si CDD)</label><input type="date" class="form-control form-control-sm" name="end_date"></div>
                <div class="col-md-4"><label class="form-label small">Heures hebdo.</label><input type="number" step="0.01" class="form-control form-control-sm" name="weekly_hours" value="35" required></div>
                <div class="col-md-4"><label class="form-label small">Salaire mensuel brut</label><input type="number" step="0.01" class="form-control form-control-sm" name="monthly_gross" required></div>
                <div class="col-md-4"><label class="form-label small">Statut</label>
                    <select class="form-select form-select-sm" name="status"><option value="draft">Brouillon</option><option value="active">Actif</option></select></div>
                <div class="col-md-6"><label class="form-label small">Poste</label><input class="form-control form-control-sm" name="position"></div>
                <div class="col-md-6"><label class="form-label small">Classification</label><input class="form-control form-control-sm" name="classification"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
        </form>
    </div>
</div>

<!-- Modal sortie -->
<div class="modal fade" id="terminateModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="/employees/<?= (int) $employee['id'] ?>/terminate">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Enregistrer la sortie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small">Date de sortie</label><input type="date" class="form-control form-control-sm" name="termination_date" required></div>
                <div><label class="form-label small">Motif</label>
                    <select class="form-select form-select-sm" name="termination_reason" required>
                        <option value="licenciement">Licenciement</option>
                        <option value="demission">Démission</option>
                        <option value="rupture_conventionnelle">Rupture conventionnelle</option>
                        <option value="fin_cdd">Fin de CDD</option>
                        <option value="retraite">Retraite</option>
                    </select></div>
            </div>
            <div class="modal-footer"><button class="btn btn-danger btn-sm">Confirmer la sortie</button></div>
        </form>
    </div>
</div>

<!-- Modal portail -->
<div class="modal fade" id="portalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Portail salarié</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">L'activation génère un jeton d'invitation à transmettre de façon sécurisée.</p>
                <button class="btn btn-primary btn-sm" id="portalBtn">Activer / régénérer le jeton</button>
                <input class="form-control form-control-sm mt-2" id="portalToken" readonly placeholder="Le jeton s'affichera ici">
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('portalBtn').addEventListener('click', async () => {
    const res = await fetch('/employees/<?= (int) $employee['id'] ?>/portal', {
        method: 'POST', headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'}
    });
    const data = await res.json();
    if (data.success) document.getElementById('portalToken').value = data.portal_token;
});
</script>
