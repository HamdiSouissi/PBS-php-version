<?php

declare(strict_types=1);

/**
 * Identité officielle de l'entreprise éditrice, source de vérité unique
 * utilisée par tous les documents PDF (factures, devis, contrats, bulletins).
 *
 * Données vérifiées sur le site institutionnel et ses mentions légales
 * (https://primevisionsolutions.fr/mentions-legales, consultées le 2026-10-27).
 * La table `companies` reste modifiable dans l'application : ses valeurs
 * surchargent cette configuration quand elles sont renseignées.
 *
 * Point d'attention : la forme juridique n'est pas publiée sur le site ;
 * la clé `legal_form` est donc volontairement vide et doit être confirmée
 * par l'entreprise avant tout usage contractuel.
 */
return [
    'legal_name' => 'PRIME VISION SOLUTIONS',
    'commercial_name' => 'Prime Vision Solutions',
    'siren' => '130493273',
    'siret' => '13049327300010',
    'vat_number' => 'FR53130493273',
    'legal_form' => '',
    'address' => '25 rue de Ponthieu',
    'postal_code' => '75008',
    'city' => 'Paris',
    'country' => 'France',
    'phone' => '01 88 31 89 12',
    'email' => 'contact@primevisionsolutions.fr',
    'website' => 'primevisionsolutions.fr',
    'activity' => "Agence digitale : sites web, applications métier sur mesure, "
        . "boutiques en ligne, applications mobiles, assistants IA et automatisation, "
        . "data engineering, design UX/UI, maintenance et DevOps.",
    // Logo utilisé dans les PDF (chemin système, rendu FPDF) et dans les vues web.
    'logo_path' => base_path('public/assets/img/logo-mark.png'),
    'logo_web' => '/assets/img/logo-mark.png',
    // Mentions reprises en pied de page des documents commerciaux.
    // La forme juridique et le capital, non publiés sur le site, sont volontairement absents.
    'legal_mentions' => 'Siège social : 25 rue de Ponthieu, 75008 Paris — '
        . 'SIRET 13049327300010 — TVA intracommunautaire FR53130493273',
];
