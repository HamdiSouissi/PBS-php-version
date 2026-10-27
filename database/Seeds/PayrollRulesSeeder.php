<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;
use App\Core\Database\Connection;

/**
 * Règles de cotisations versionnées - taux légaux conventionnels de référence.
 *
 * Source : barèmes URSSAF / Agirc-Arrco 2025 (taux de droit commun, siège).
 * Le plafond mensuel de la sécurité sociale (PMSS) 2025 est de 4 010 €.
 * Ces taux sont fournis à titre de référence et DOIVENT être vérifiés par un
 * spécialiste paie avant toute utilisation en production (taux AT, accords
 * collectifs, exonérations spécifiques non gérés ici).
 */
final class PayrollRulesSeeder
{
    /** Plafond mensuel Sécurité sociale 2025. */
    public const PMSS_2025 = 4010.00;

    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        $db = $this->container->get(Connection::class);
        $from = '2025-01-01';

        foreach ($this->rules() as $rule) {
            $exists = $db->fetchScalar(
                'SELECT id FROM payroll_rule_versions WHERE code = :c AND effective_from = :d',
                ['c' => $rule['code'], 'd' => $from]
            );
            if ($exists !== false && $exists !== null) {
                continue;
            }
            $db->execute(
                'INSERT INTO payroll_rule_versions
                    (code, label, category, base_type, rate_employee, rate_employer,
                     ceiling_month, is_deductible, is_taxable, effective_from, source, notes)
                 VALUES
                    (:code, :label, :category, :base_type, :re, :rem, :ceiling,
                     :deductible, :taxable, :from, :source, :notes)',
                [
                    'code' => $rule['code'],
                    'label' => $rule['label'],
                    'category' => $rule['category'],
                    'base_type' => $rule['base_type'],
                    're' => $rule['rate_employee'],
                    'rem' => $rule['rate_employer'],
                    'ceiling' => $rule['ceiling'],
                    'deductible' => $this->boolean($rule['is_deductible']),
                    'taxable' => $this->boolean($rule['is_taxable']),
                    'from' => $from,
                    'source' => $rule['source'],
                    'notes' => $rule['notes'],
                ]
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rules(): array
    {
        return [
            [
                'code' => 'MALADIE', 'label' => 'Assurance maladie, maternité, invalidité, décès',
                'category' => 'sante', 'base_type' => 'brut',
                'rate_employee' => 0.000, 'rate_employer' => 13.000, 'ceiling' => null,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'URSSAF 2025',
                'notes' => 'Taux employeur de droit commun (7% + 6% complémentaire).',
            ],
            [
                'code' => 'VIEIL_PLAF', 'label' => 'Vieillesse plafonnée',
                'category' => 'vieillesse', 'base_type' => 'plafond',
                'rate_employee' => 6.900, 'rate_employer' => 8.550, 'ceiling' => self::PMSS_2025,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
            [
                'code' => 'VIEIL_DEP', 'label' => 'Vieillesse déplafonnée',
                'category' => 'vieillesse', 'base_type' => 'brut',
                'rate_employee' => 0.400, 'rate_employer' => 2.050, 'ceiling' => null,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
            [
                'code' => 'FAMILLE', 'label' => 'Allocations familiales',
                'category' => 'famille', 'base_type' => 'brut',
                'rate_employee' => 0.000, 'rate_employer' => 3.500, 'ceiling' => null,
                'is_deductible' => false, 'is_taxable' => false, 'source' => 'URSSAF 2025',
                'notes' => 'Taux normal de droit commun.',
            ],
            [
                'code' => 'AT', 'label' => 'Accidents du travail et maladies professionnelles',
                'category' => 'accident_travail', 'base_type' => 'brut',
                'rate_employee' => 0.000, 'rate_employer' => 1.000, 'ceiling' => null,
                'is_deductible' => false, 'is_taxable' => false, 'source' => 'Taux notifié entreprise',
                'notes' => 'TAUX PARAMÉTRABLE : le taux réel est notifié par la CPAM/ Carsat.',
            ],
            [
                'code' => 'AGIRC_ARRCO_A', 'label' => 'Retraite complémentaire Agirc-Arrco tranche A',
                'category' => 'retraite_complementaire', 'base_type' => 'plafond',
                'rate_employee' => 3.150, 'rate_employer' => 4.720, 'ceiling' => self::PMSS_2025,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'Agirc-Arrco 2025',
                'notes' => 'Taux d\'appel 127,28% non intégré (taux de base contractuel).',
            ],
            [
                'code' => 'AGIRC_ARRCO_B', 'label' => 'Retraite complémentaire Agirc-Arrco tranche B',
                'category' => 'retraite_complementaire', 'base_type' => 'brut_et_plafond',
                'rate_employee' => 3.150, 'rate_employer' => 4.720, 'ceiling' => self::PMSS_2025 * 8,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'Agirc-Arrco 2025',
                'notes' => 'Tranche B (rémunération entre 1 et 8 plafonds).',
            ],
            [
                'code' => 'CEG_A', 'label' => 'Contribution d\'équilibre général (CEG) tranche A',
                'category' => 'retraite_complementaire', 'base_type' => 'plafond',
                'rate_employee' => 0.860, 'rate_employer' => 1.290, 'ceiling' => self::PMSS_2025,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'Agirc-Arrco 2025', 'notes' => '',
            ],
            [
                'code' => 'CEG_B', 'label' => 'Contribution d\'équilibre général (CEG) tranche B',
                'category' => 'retraite_complementaire', 'base_type' => 'brut_et_plafond',
                'rate_employee' => 1.080, 'rate_employer' => 1.620, 'ceiling' => self::PMSS_2025 * 8,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'Agirc-Arrco 2025', 'notes' => '',
            ],
            [
                'code' => 'CHOMAGE', 'label' => 'Assurance chômage',
                'category' => 'chomage', 'base_type' => 'plafond',
                'rate_employee' => 0.000, 'rate_employer' => 4.050, 'ceiling' => self::PMSS_2025 * 4,
                'is_deductible' => false, 'is_taxable' => false, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
            [
                'code' => 'AGS', 'label' => 'AGS (garantie des salaires)',
                'category' => 'autres', 'base_type' => 'plafond',
                'rate_employee' => 0.000, 'rate_employer' => 0.184, 'ceiling' => self::PMSS_2025 * 4,
                'is_deductible' => false, 'is_taxable' => false, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
            [
                'code' => 'CSG_DED', 'label' => 'CSG déductible',
                'category' => 'csg_crds', 'base_type' => 'csg',
                'rate_employee' => 9.200, 'rate_employer' => 0.000, 'ceiling' => null,
                'is_deductible' => true, 'is_taxable' => false, 'source' => 'URSSAF 2025',
                'notes' => 'Assiette forfaitaire 98,25% du brut + cotisations salariales obligatoires.',
            ],
            [
                'code' => 'CSG_NONDED', 'label' => 'CSG non déductible',
                'category' => 'csg_crds', 'base_type' => 'csg',
                'rate_employee' => 2.400, 'rate_employer' => 0.000, 'ceiling' => null,
                'is_deductible' => false, 'is_taxable' => true, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
            [
                'code' => 'CRDS', 'label' => 'CRDS',
                'category' => 'csg_crds', 'base_type' => 'csg',
                'rate_employee' => 0.500, 'rate_employer' => 0.000, 'ceiling' => null,
                'is_deductible' => false, 'is_taxable' => true, 'source' => 'URSSAF 2025', 'notes' => '',
            ],
        ];
    }

    private function boolean(bool $value): bool
    {
        return $value;
    }
}
