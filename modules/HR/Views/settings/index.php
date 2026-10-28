<?php

declare(strict_types=1);
/**
 * @var float $monthlyAccrual
 * @var float $annualEquivalent
 * @var float $defaultAccrual
 * @var float $minAccrual
 * @var float $maxAccrual
 */
?>
<?= $viewRenderer->pageHeader('Paramètres RH', [['label' => 'Ressources humaines']]) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Acquisition des congés payés</div>
            <div class="card-body">
                <form method="post" action="/hr/settings" class="row g-3" data-guard>
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Jours de congés payés acquis par mois de travail effectif</label>
                        <div class="input-group" style="max-width: 320px;">
                            <input type="number" step="0.0001" min="<?= e((string) $minAccrual) ?>" max="<?= e((string) $maxAccrual) ?>"
                                   class="form-control" name="leave_monthly_accrual"
                                   value="<?= e(number_format($monthlyAccrual, 4, '.', '')) ?>" required>
                            <span class="input-group-text">jour(s) / mois</span>
                        </div>
                        <div class="form-text">
                            Plage autorisée : <?= e(number_format($minAccrual, 2, ',', ' ')) ?>
                            à <?= e(number_format($maxAccrual, 2, ',', ' ')) ?> jours par mois.
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 small mb-0">
                            <i data-lucide="info"></i>
                            Équivalent annuel théorique :
                            <strong><?= e(number_format($annualEquivalent, 2, ',', ' ')) ?> jours</strong>
                            pour une année complète.
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i data-lucide="save"></i>Enregistrer</button>
                        <form method="post" action="/hr/settings/reset" data-confirm="Rétablir la valeur par défaut française (2,0833 j/mois) ?">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i data-lucide="rotate-ccw"></i>Rétablir la valeur par défaut
                            </button>
                        </form>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Référence légale française</div>
            <div class="card-body small text-muted">
                <p class="mb-2">
                    Le droit commun français ouvre <strong>2,5 jours ouvrables</strong> de congés payés par mois
                    de travail effectif, soit 30 jours ouvrables par an (5 semaines).
                </p>
                <p class="mb-2">
                    La plupart des logiciels de paie raisonnent en <strong>jours ouvrés</strong> :
                    25 jours par an, soit <strong><?= e(number_format($defaultAccrual, 4, ',', ' ')) ?> jours par mois</strong>.
                    C'est la valeur appliquée par défaut dans l'application, cohérente avec le type de congé
                    « Congés payés » (25 jours).
                </p>
                <p class="mb-0">
                    Ce paramètre pilote le calcul automatique du solde affiché sur chaque fiche salarié.
                    Sa modification est réservée au super-administrateur et aux responsables RH.
                </p>
            </div>
        </div>
    </div>
</div>
</div>
