<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;
use App\Core\Pdf\PdfLabels;

/**
 * Contrat commercial de prestations PDF. Le document reprend les données
 * réelles du contrat (montant, cycle de facturation, renouvellement, préavis)
 * et les clauses essentielles du droit commercial français, notamment les
 * pénalités de retard et l'indemnité forfaitaire de recouvrement.
 *
 * Il s'agit d'un modèle type : une mention visible oblige à une relecture
 * juridique avant toute signature.
 */
final class ClientContractPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $contract = $this->db->fetchOne(
            'SELECT c.*, t.name AS client_name, t.address AS client_address, t.postal_code AS client_pc,
                    t.city AS client_city, t.siret AS client_siret, co.legal_name, co.address AS company_address,
                    co.postal_code AS company_pc, co.city AS company_city, co.siret AS company_siret
             FROM client_contracts c
             JOIN third_parties t ON t.id = c.third_party_id
             JOIN companies co ON co.id = (SELECT id FROM companies ORDER BY is_default DESC, id ASC LIMIT 1)
             WHERE c.id = :id',
            ['id' => $entityId]
        );
        if ($contract === null) {
            throw new NotFoundException('Contrat client introuvable.');
        }

        $title = 'Contrat client ' . $contract['contract_number'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('CONTRAT DE PRESTATIONS');

        $pdf->noticeBox(
            "DOCUMENT TYPE — RELECTURE JURIDIQUE OBLIGATOIRE.\n"
            . "Ce contrat est généré à partir des données enregistrées dans l'application. "
            . "Il doit être relu, vérifié et adapté par un juriste ou un conseil juridique "
            . "avant toute signature, en fonction des conditions particulières de la prestation."
        );

        $pdf->keyValueTable([
            ['Référence', (string) $contract['contract_number']],
            ['Intitulé', (string) $contract['title']],
            ['Date de signature', $contract['signed_on'] ? date('d/m/Y', strtotime((string) $contract['signed_on'])) : '—'],
            ['Date d\'effet', date('d/m/Y', strtotime((string) $contract['starts_on']))],
            ['Date de fin', $contract['ends_on'] ? date('d/m/Y', strtotime((string) $contract['ends_on'])) : 'Reconduction'],
            ['Statut', PdfLabels::contractStatus((string) $contract['status'])],
        ]);

        $pdf->article('1', 'Parties',
            "Le présent contrat est conclu entre :\n"
            . "Le Prestataire : {$contract['legal_name']}, {$contract['company_address']}, "
            . trim($contract['company_pc'] . ' ' . $contract['company_city'])
            . ", SIRET {$contract['company_siret']},\nCi-après « le Prestataire », d'une part,\n\n"
            . "Et le Client : {$contract['client_name']}, "
            . implode(', ', array_filter([
                $contract['client_address'],
                trim($contract['client_pc'] . ' ' . $contract['client_city']),
                $contract['client_siret'] ? 'SIRET ' . $contract['client_siret'] : '',
            ])) . ",\nCi-après « le Client », d'autre part."
        );

        $pdf->article('2', 'Objet et prestations',
            "Le présent contrat porte sur la prestation suivante : {$contract['title']}. "
            . "Le périmètre, les livrables, les niveaux de service et les délais de réalisation sont "
            . "définis d'un commun accord entre les parties et formalisés par les bons de commande, "
            . "devis et éventuelles annexes, qui font partie intégrante du contrat. Le Prestataire "
            . "réalise la prestation avec tout le soin et la diligence attendus d'un professionnel."
        );

        $renewal = match ((string) $contract['renewal_mode']) {
            'tacit' => 'reconduction tacite aux mêmes conditions',
            'express' => 'renouvellement exprès requis par avenant signé',
            default => 'pas de reconduction automatique',
        };
        $pdf->article('3', 'Durée, renouvellement et préavis',
            "Le contrat prend effet le " . date('d/m/Y', strtotime((string) $contract['starts_on'])) . '. '
            . ($contract['ends_on']
                ? "Il prend fin le " . date('d/m/Y', strtotime((string) $contract['ends_on'])) . ". "
                : "Sa durée est indéterminée. ")
            . "Le mode de renouvellement est le suivant : {$renewal}. Chaque partie peut mettre fin au "
            . "contrat sous réserve d'un préavis de " . (int) $contract['notice_days']
            . " jours, notifié par lettre ou courriel avec accusé de réception, sans indemnité "
            . "sauf faute ou engagement particulier précisé en annexe."
        );

        $pdf->article('4', 'Obligations des parties',
            "Le Prestataire s'engage à exécuter la prestation conformément aux règles de l'art, à "
            . "respecter la réglementation applicable et à informer le Client de tout risque de retard. "
            . "Le Client s'engage à fournir au Prestataire, en temps utile, l'ensemble des informations, "
            . "accès et moyens nécessaires à la réalisation de la prestation, et à désigner un interlocuteur "
            . "habilité. Tout retard imputable au Client décale d'autant les délais du Prestataire."
        );

        $pdf->article('5', 'Prix et modalités de paiement',
            "Les conditions financières du contrat sont les suivantes. Les prix s'entendent hors taxes ; "
            . "la TVA est facturée au taux en vigueur au jour de la facturation.");
        $pdf->keyValueTable([
            ['Montant annuel HT', money_format((float) $contract['amount_ht'])],
            ['TVA', 'Taux en vigueur applicable au jour de la facturation'],
            ['Cycle de facturation', PdfLabels::billingCycle((string) $contract['billing_cycle'])],
            ['Modalités de règlement', '30 jours nets à réception de facture, par virement bancaire'],
        ]);

        $pdf->article('6', 'Retards de paiement et recouvrement',
            "En l'absence de paiement à l'échéance, le Prestataire applique, sans qu'un rappel soit "
            . "nécessaire, des pénalités de retard exigibles le jour suivant la date de règlement figurant "
            . "sur la facture. Leur taux est égal au taux d'intérêt appliqué par la Banque centrale "
            . "européenne à sa plus récente opération de refinancement, majoré de 10 points de pourcentage "
            . "(article L. 441-10 du Code de commerce). Une indemnité forfaitaire pour frais de "
            . "recouvrement d'un montant de 40 euros est due de plein droit pour chaque facture impayée "
            . "(article D. 441-5 du Code de commerce), sans préjudice d'une indemnisation complémentaire "
            . "sur justification lorsque les frais exposés dépassent ce montant."
        );

        $pdf->article('7', 'Propriété intellectuelle',
            "Les créations, études, documents, logiciels et livrables spécifiquement réalisés pour le "
            . "Client dans le cadre de la prestation lui sont transférés, pour le monde entier et pour la "
            . "durée légale de protection, au fur et à mesure de leur paiement intégral. Les éléments "
            . "préexistants du Prestataire (savoir-faire, méthodes, composants réutilisables) restent sa "
            . "propriété exclusive et font l'objet d'un droit d'usage concédé au Client pour ses besoins "
            . "internes. Chaque partie reste propriétaire de ses marques et signes distinctifs."
        );

        $pdf->article('8', 'Confidentialité',
            "Chaque partie s'interdit de communiquer à des tiers, pendant la durée du contrat et pendant "
            . "les trois années suivant sa fin, toute information technique, commerciale, financière ou "
            . "organisationnelle dont elle aurait connaissance à l'occasion de son exécution, sauf "
            . "obligation légale ou information déjà publique. Cette obligation s'étend aux salariés et "
            . "sous-traitants, qui sont soumis aux mêmes engagements."
        );

        $pdf->article('9', 'Données personnelles (RGPD)',
            "Lorsque la prestation implique un traitement de données à caractère personnel pour le compte "
            . "du Client, le Prestataire agit en qualité de sous-traitant au sens du règlement (UE) "
            . "2016/679 (RGPD) : il ne traite les données que sur instructions documentées du Client, "
            . "assure leur sécurité et leur confidentialité, ne recourt qu'à des sous-traitants présentant "
            . "des garanties suffisantes et aide le Client à respecter ses obligations (violation de "
            . "données, demandes d'exercice des droits, analyses d'impact). À la fin du contrat, les "
            . "données sont restituées ou supprimées, selon les instructions du Client."
        );

        $pdf->article('10', 'Responsabilité et assurance',
            "Chaque partie est responsable des dommages directs qu'elle cause dans l'exécution du contrat, "
            . "dans les limites prévues par la loi. Le Prestataire est assuré au titre de sa responsabilité "
            . "civile professionnelle et fournit une attestation sur demande. Sauf faute lourde ou "
            . "intentionnelle, la responsabilité du Prestataire est limitée au montant des sommes versées "
            . "par le Client au titre des douze derniers mois d'exécution. Les dommages indirects (perte "
            . "de chiffre d'affaires, de données, de bénéfices) sont exclus, dans les limites légales."
        );

        $pdf->article('11', 'Force majeure',
            "En cas de force majeure au sens de l'article 1218 du Code civil et de la jurisprudence, "
            . "l'exécution des obligations affectées est suspendue. La partie empêchée avertit l'autre "
            . "sans délai et justifie de l'événement ; elle met en œuvre tous les moyens pour en limiter "
            . "les effets. Si l'empêchement se prolonge au-delà de trente jours, chaque partie pourra "
            . "résilier le contrat sans indemnité."
        );

        $pdf->article('12', 'Résiliation',
            "Outre l'arrivée du terme et le préavis prévu à l'article 3, chaque partie peut résilier le "
            . "contrat de plein droit en cas de manquement grave de l'autre partie non régularisé dans un "
            . "délai de quinze jours après mise en demeure par lettre recommandée avec accusé de réception. "
            . "La résiliation entraîne l'exigibilité immédiate des prestations déjà réalisées et des frais "
            . "engagés. Les clauses de confidentialité, propriété intellectuelle et responsabilité "
            . "survivent à la fin du contrat."
        );

        $pdf->article('13', 'Litiges et droit applicable',
            "Le présent contrat est soumis au droit français. Les parties s'efforcent de résoudre à "
            . "l'amiable tout différend relatif à sa validité, son interprétation ou son exécution. À "
            . "défaut d'accord dans un délai de trente jours, le litige est porté devant le tribunal de "
            . "commerce du ressort du siège du Prestataire, même en cas de pluralité de défendeurs ou "
            . "d'appel en garantie."
        );

        $pdf->article('14', 'Dispositions générales',
            "Le contrat et ses annexes constituent l'intégralité de l'accord des parties ; ils remplacent "
            . "tous les échanges antérieurs portant sur le même objet. Toute modification fait l'objet d'un "
            . "avenant signé. Le fait pour une partie de ne pas se prévaloir d'une clause ne vaut pas "
            . "renonciation. Si une clause était déclarée nulle, les autres conserveraient leurs effets. "
            . "Les notifications sont valablement faites aux adresses figurant dans l'article 1er."
        );

        if (($contract['notes'] ?? '') !== '') {
            $pdf->h2('Conditions particulières');
            $pdf->paragraph((string) $contract['notes']);
        }

        $pdf->h2('Signatures');
        $pdf->paragraph('Fait en deux exemplaires originaux remis à chacune des parties.');
        $pdf->signatureBlock(
            "Pour le Prestataire\n{$contract['legal_name']}\nNom et prénom : ………………………………\nQualité : ………………………………\nFait à ……………………, le ……………………\nSignature (mention « Lu et approuvé »)",
            "Pour le Client\n{$contract['client_name']}\nNom et prénom : ………………………………\nQualité : ………………………………\nFait à ……………………, le ……………………\nSignature (mention « Lu et approuvé »)"
        );

        return $title;
    }
}
