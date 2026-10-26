<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;
use App\Core\Database\Connection;

/**
 * Plan de comptes général français (PCG) - comptes usuels et journaux.
 */
final class ChartOfAccountsSeeder
{
    /**
     * @return list<array{code:string,label:string,class:int,type:string,auxiliary?:bool}>
     */
    public static function accounts(): array
    {
        return [
            // Classe 1 - Capitaux
            ['101000', 'Capital social', 1, 'equity'],
            ['106100', 'Réserve légale', 1, 'equity'],
            ['106800', 'Autres réserves', 1, 'equity'],
            ['108000', 'Compte de l\'exploitant', 1, 'equity'],
            ['164000', 'Emprunts auprès des établissements de crédit', 1, 'liability'],
            ['168000', 'Autres emprunts et dettes', 1, 'liability'],
            // Classe 2 - Immobilisations
            ['218300', 'Matériel de bureau et informatique', 2, 'asset'],
            ['218400', 'Mobilier', 2, 'asset'],
            ['281830', 'Amortissement du matériel informatique', 2, 'asset'],
            ['281840', 'Amortissement du mobilier', 2, 'asset'],
            // Classe 3 - Stocks
            ['311000', 'Stocks de matières premières', 3, 'asset'],
            ['370000', 'Stocks de marchandises', 3, 'asset'],
            // Classe 4 - Tiers
            ['401000', 'Fournisseurs', 4, 'liability', true],
            ['404000', 'Fournisseurs d\'immobilisations', 4, 'liability', true],
            ['411000', 'Clients', 4, 'asset', true],
            ['416000', 'Clients douteux', 4, 'asset', true],
            ['421000', 'Personnel - rémunérations dues', 4, 'liability'],
            ['425000', 'Personnel - avances et acomptes', 4, 'asset'],
            ['428000', 'Personnel - charges à payer', 4, 'liability'],
            ['431000', 'Sécurité sociale (URSSAF)', 4, 'liability'],
            ['437200', 'Cotisations retraite complémentaire', 4, 'liability'],
            ['437300', 'Cotisations assurance chômage', 4, 'liability'],
            ['437800', 'Autres organismes sociaux', 4, 'liability'],
            ['442100', 'TVA collectée', 4, 'liability'],
            ['445510', 'TVA à décaisser', 4, 'liability'],
            ['445660', 'TVA déductible sur autres biens et services', 4, 'asset'],
            ['445710', 'TVA collectée (attente)', 4, 'liability'],
            ['444000', 'État - impôts sur les bénéfices', 4, 'liability'],
            ['447000', 'Autres impôts et taxes', 4, 'liability'],
            ['455000', 'Associés - comptes courants', 4, 'liability'],
            ['457000', 'Associés - dividendes à payer', 4, 'liability'],
            ['486000', 'Charges constatées d\'avance', 4, 'asset'],
            // Classe 5 - Trésorerie
            ['512000', 'Banque', 5, 'asset'],
            ['514000', 'Chèques postaux', 5, 'asset'],
            ['530000', 'Caisse', 5, 'asset'],
            // Classe 6 - Charges
            ['601000', 'Achats de matières premières', 6, 'expense'],
            ['606100', 'Fournitures non stockables (énergie)', 6, 'expense'],
            ['606400', 'Fournitures administratives', 6, 'expense'],
            ['607000', 'Achats de marchandises', 6, 'expense'],
            ['613200', 'Locations immobilières', 6, 'expense'],
            ['615600', 'Maintenance', 6, 'expense'],
            ['616000', 'Primes d\'assurance', 6, 'expense'],
            ['618000', 'Documentation et formation', 6, 'expense'],
            ['622600', 'Honoraires (expert-comptable, avocat)', 6, 'expense'],
            ['626000', 'Frais de postes et télécommunications', 6, 'expense'],
            ['627000', 'Services bancaires', 6, 'expense'],
            ['641100', 'Salaires de base', 6, 'expense'],
            ['641300', 'Primes et gratifications', 6, 'expense'],
            ['641400', 'Indemnités et avantments divers', 6, 'expense'],
            ['645100', 'Cotisations URSSAF', 6, 'expense'],
            ['645300', 'Cotisations retraite', 6, 'expense'],
            ['645400', 'Cotisations ASSEDIC / chômage', 6, 'expense'],
            ['647000', 'Autres charges sociales', 6, 'expense'],
            ['661100', 'Intérêts des emprunts', 6, 'expense'],
            ['671000', 'Charges exceptionnelles sur opération de gestion', 6, 'expense'],
            // Classe 7 - Produits
            ['701000', 'Ventes de produits finis', 7, 'income'],
            ['706000', 'Prestations de services', 7, 'income'],
            ['707000', 'Ventes de marchandises', 7, 'income'],
            ['708000', 'Produits des activités annexes', 7, 'income'],
            ['740000', 'Subventions d\'exploitation', 7, 'income'],
            ['758000', 'Produits divers de gestion courante', 7, 'income'],
            ['764000', 'Revenus des valeurs mobilières', 7, 'income'],
            // Classe 8
            ['890000', 'Bilan d\'ouverture', 8, 'neutral', false],
        ];
    }

    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        $db = $this->container->get(Connection::class);

        $journals = [
            ['AC', 'Journal des achats', 'purchases'],
            ['VT', 'Journal des ventes', 'sales'],
            ['BQ', 'Journal de banque', 'bank'],
            ['CA', 'Journal de caisse', 'cash'],
            ['OD', 'Opérations diverses', 'od'],
            ['AN', 'À-nouveaux', 'closing'],
        ];
        foreach ($journals as [$code, $label, $type]) {
            $db->execute(
                'INSERT INTO journals (code, label, type) VALUES (:c, :l, :t)
                 ON CONFLICT (code) DO NOTHING',
                ['c' => $code, 'l' => $label, 't' => $type]
            );
        }

        foreach (self::accounts() as $account) {
            [$code, $label, $class, $type] = $account;
            $auxiliary = $account[4] ?? false;
            $db->execute(
                'INSERT INTO chart_accounts
                    (code, label, account_class, account_type, is_auxiliary, allow_entries, parent_code)
                 VALUES
                    (:code, :label, :class, :type, :aux, TRUE, NULL)
                 ON CONFLICT (code) DO NOTHING',
                [
                    'code' => $code,
                    'label' => $label,
                    'class' => $class,
                    'type' => $type,
                    'aux' => $auxiliary,
                ]
            );
        }
    }
}
