<?php

declare(strict_types=1);

/**
 * Vue générique de liste tabulaire partagée par les modules
 * (IA, comptabilité, conformité, contrats, dividendes, notes de frais,
 * paie, abonnements, trésorerie).
 *
 * Contrat des données transmises par les routes :
 *  - title   : titre de la page (repris par le layout)
 *  - heading : titre affiché dans le contenu
 *  - intro   : paragraphe de présentation (optionnel)
 *  - notice  : alerte d'information / limite fonctionnelle (optionnel)
 *  - sections: liste de sections, chacune contenant :
 *      title   : intitulé de la section
 *      rows    : lignes de données (liste de tableaux associatifs)
 *      empty   : message affiché si rows est vide (optionnel)
 *      columns : table associative champ => [Libellé] ou [Libellé, type]
 *                types supportés : date, datetime, money, badge
 *
 * @var string               $title
 * @var string               $heading
 * @var string               $intro
 * @var string               $notice
 * @var list<array{title:string,rows:list<array<string,mixed>>,empty?:string,columns:array<string,array{0:string,1?:string}>}> $sections
 */

$heading ??= $title ?? '';
$intro ??= '';
$notice ??= '';
$sections ??= [];

/**
 * Couleur sémantique du badge selon le libellé de statut (français).
 */
$badgeClass = static function (string $value): string {
    $normalized = mb_strtolower(trim($value));
    $success = ['actif', 'active', 'activé', 'comptabilisé', 'payé', 'payée', 'validé', 'validée',
        'terminé', 'terminée', 'émis', 'émise', 'oui', 'réussi', 'succès', 'encaissé', 'reconcilié', 'rapproché'];
    $warning = ['partiel', 'relance', 'en retard', 'retard', 'échéance', 'à échoir', 'bientôt', 'préavis'];
    $danger = ['annulé', 'annulée', 'rejeté', 'rejetée', 'échu', 'échue', 'clôturé', 'clôturée',
        'suspendu', 'suspendue', 'impayé', 'impayée', 'erreur', 'désactivé', 'désactivée'];
    $muted = ['brouillon', 'non', 'inactif', 'inactive', 'nouveau', 'nouvelle'];
    foreach ($success as $word) {
        if (str_contains($normalized, $word)) {
            return 'text-bg-success';
        }
    }
    foreach ($warning as $word) {
        if (str_contains($normalized, $word)) {
            return 'text-bg-warning';
        }
    }
    foreach ($danger as $word) {
        if (str_contains($normalized, $word)) {
            return 'text-bg-danger';
        }
    }
    foreach ($muted as $word) {
        if (str_contains($normalized, $word)) {
            return 'text-bg-secondary';
        }
    }
    return 'text-bg-info';
};

/**
 * Formate une valeur cellule selon le type déclaré dans la colonne.
 */
$format = static function (mixed $value, ?string $type) use ($badgeClass): string {
    if ($value === null || $value === '') {
        return '<span class="text-muted">—</span>';
    }
    return match ($type) {
        'date' => e(date('d/m/Y', strtotime((string) $value))),
        'datetime' => e(date('d/m/Y H:i', strtotime((string) $value))),
        'money' => e(money_format($value)),
        'badge' => '<span class="badge ' . $badgeClass((string) $value) . '">' . e($value) . '</span>',
        default => e($value),
    };
};
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-1"><?= e($heading) ?></h2>
        <?php if ($intro !== ''): ?>
            <p class="text-muted mb-0"><?= e($intro) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($notice !== ''): ?>
    <div class="alert alert-info d-flex align-items-start gap-2" role="note">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div class="small mb-0"><?= e($notice) ?></div>
    </div>
<?php endif; ?>

<?php foreach ($sections as $section): ?>
    <?php
    $rows = $section['rows'] ?? [];
    $columns = $section['columns'] ?? [];
    $emptyMessage = $section['empty'] ?? 'Aucune donnée disponible.';
    ?>
    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><?= e($section['title']) ?></span>
            <span class="badge text-bg-light border rounded-pill"><?= count($rows) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($columns as $config): ?>
                            <th><?= e($config[0]) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= max(1, count($columns)) ?>" class="text-muted small py-3 text-center">
                            <?= e($emptyMessage) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $field => $config): ?>
                            <?php $type = $config[1] ?? null; ?>
                            <td><?= $format($row[$field] ?? null, $type) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
