<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Core\Pdf\OnePageDocument;
use App\Core\Pdf\PdfDocument;
use App\Core\Pdf\PdfSection;
use Tests\Core\TestCase;

/**
 * Tests unitaires du moteur PDF : encodage Windows-1252, accent bleu de marque,
 * pagination, compactage mono-page et validité des binaires produits.
 */
final class PdfDocumentTest extends TestCase
{
    public function testThinNarrowNoBreakSpaceIsNormalized(): void
    {
        $pdf = new PdfDocument();
        // U+202F (espace fine insécable de NumberFormatter) ne doit pas devenir « ? ».
        $encoded = $pdf->t("3\u{202F}200,00 €");
        self::assertFalse(str_contains($encoded, '?'), 'Aucun point d\'interrogation de substitution attendu.');
        self::assertStringContainsString("\xA0", $encoded); // espace insécable Windows-1252
        self::assertStringContainsString("\x80", $encoded); // symbole euro Windows-1252
    }

    public function testGenuineQuestionMarkIsPreserved(): void
    {
        $pdf = new PdfDocument();
        self::assertSame('Pourquoi ?', $pdf->t('Pourquoi ?'));
    }

    public function testAccentsAndDashAreEncodedWithoutSubstitution(): void
    {
        $pdf = new PdfDocument();
        $encoded = $pdf->t('Rémunération — Société « à » …');
        self::assertFalse(str_contains($encoded, '?'), 'Aucun point d\'interrogation de substitution attendu.');
        self::assertStringContainsString("\x97", $encoded); // tiret cadratin
        self::assertStringContainsString("\xE9", $encoded); // é
    }

    public function testOutputIsValidPdfWithPaginationFooter(): void
    {
        $pdf = new PdfDocument();
        $pdf->setCompany(['legal_name' => 'Société test']);
        $pdf->setDocumentTitle('Test')->addPage();
        $pdf->h1('Titre');
        $pdf->keyValueTable([['Clé', 'Valeur']]);
        $binary = $pdf->output();

        self::assertStringContainsString('%PDF-', substr($binary, 0, 5));
        // Le pied de page porte la pagination réelle (alias {nb} résolu),
        // recherchée dans les flux décompressés (FlateDecode).
        self::assertTrue(
            str_contains($this->decompressedText($binary), $pdf->t('Page 1 / 1')),
            'Le pied de page doit contenir la pagination « Page 1 / 1 ».'
        );
    }

    public function testPaletteUsesBrandBlueAccent(): void
    {
        $reflection = new \ReflectionClass(PdfDocument::class);
        $accent = $reflection->getConstant('ACCENT');
        self::assertSame([11, 85, 189], $accent);
        // Le bleu est la composante dominante de l'accent de marque.
        self::assertGreaterThan($accent[0], $accent[2], "L'accent PDF doit être un bleu (composante bleue dominante).");
    }

    public function testRenderedStreamsContainBrandBlueOperator(): void
    {
        $pdf = new PdfDocument();
        $pdf->setDocumentTitle('Test')->addPage();
        $pdf->h1('Titre accentué');
        $pdf->totalsTable([['Total TTC', '5 040,00 €', true]]);
        $binary = $pdf->output();

        $expected = [11 / 255, 85 / 255, 189 / 255];
        $found = false;
        foreach ($this->extractColorOperators($binary) as [$red, $green, $blue]) {
            if (abs($red - $expected[0]) < 0.01 && abs($green - $expected[1]) < 0.01 && abs($blue - $expected[2]) < 0.01) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'L\'opérateur de couleur bleu de marque doit apparaître dans le flux PDF.');
    }

    public function testHeaderHasNoColoredSeparatorLine(): void
    {
        // Le filet coloré entre l'en-tête et le corps a été supprimé : sur une
        // page sans contenu accentué, aucune opération de couleur bleue de
        // marque ne doit être émise (l'en-tête ne dessine plus de trait).
        $accent = (new \ReflectionClass(PdfDocument::class))->getConstant('ACCENT');
        $expected = array_map(static fn (int $channel): float => $channel / 255, $accent);

        $pdf = new PdfDocument();
        $pdf->setCompany(['legal_name' => 'Société test']);
        $pdf->setDocumentTitle('Test')->addPage();
        $binary = $pdf->output();

        foreach ($this->extractColorOperators($binary) as [$red, $green, $blue]) {
            $matchesAccent = abs($red - $expected[0]) < 0.01
                && abs($green - $expected[1]) < 0.01
                && abs($blue - $expected[2]) < 0.01;
            self::assertFalse($matchesAccent, 'Un trait ou remplissage bleu de marque subsiste dans l\'en-tête.');
        }
    }

    public function testDenseBulletinStaysOnOnePage(): void
    {
        $pdf = new PdfDocument();
        $pdf->setCompany(['legal_name' => 'Société test'])
            ->setDensity(0.75)
            ->setCompactRows(true)
            ->setTightSpacing(true);
        $pdf->setDocumentTitle('Bulletin')->addPage();
        $pdf->h1('BULLETIN DE PAIE');
        $pdf->keyValueColumns(
            'Employeur',
            [['Raison', 'Société test'], ['Adresse', '25 rue de Ponthieu, 75008 Paris'], ['SIRET', '13049327300010']],
            'Salarié',
            [['Nom', 'Jean Dupont'], ['Matricule', 'EMP-001'], ['Poste', 'Développeur'], ['NIR', '188044412345678']]
        );
        $rows = [];
        foreach (['Santé', 'Retraite', 'Famille', 'Chômage', 'CSG'] as $group) {
            $rows[] = PdfSection::make($group);
            for ($i = 0; $i < 5; $i++) {
                $rows[] = ["Cotisation réaliste numéro {$i}", '3 200,00', '6,9 %', '220,80 €', '273,60 €'];
            }
        }
        $pdf->dataTable(
            ['Cotisation', 'Base', 'Taux', 'Part salariale', 'Part patronale'],
            $rows,
            [0, 26, 24, 31, 31],
            ['L', 'R', 'C', 'R', 'R']
        );
        $pdf->totalsTable([
            ['Brut', '3 200,00 €', false],
            ['Net imposable', '2 413,47 €', false],
            ['Net à payer', '2 322,29 €', true],
        ]);
        $pdf->smallMuted('Cumuls annuels et solde de congés sur une ligne récapitulative.');

        self::assertSame(1, $pdf->pageCount(), 'Un bulletin dense doit tenir sur une seule page.');
        self::assertStringContainsString('%PDF-', substr($pdf->output(), 0, 5));
    }

    public function testLongTableFlowsOnSeveralPagesWithoutError(): void
    {
        $pdf = new PdfDocument();
        $pdf->setDocumentTitle('Grand tableau')->addPage();
        $rows = [];
        for ($i = 0; $i < 80; $i++) {
            $rows[] = ["Ligne {$i}", 'Valeur', '100,00 €'];
        }
        $pdf->dataTable(['Libellé', 'Colonne', 'Montant'], $rows, [0, 50, 40]);

        self::assertGreaterThan(1, $pdf->pageCount(), 'Un tableau très long doit provoquer une pagination.');
        self::assertStringContainsString('%PDF-', substr($pdf->output(), 0, 5));
    }

    public function testNoticeBoxArticleAndSignaturesRender(): void
    {
        $pdf = new PdfDocument();
        $pdf->setDocumentTitle('Contrat')->addPage();
        $pdf->noticeBox("DOCUMENT TYPE — RELECTURE JURIDIQUE OBLIGATOIRE.\nTexte assez long pour vérifier que le cadre s'agrandit correctement sur plusieurs lignes sans jamais rogner le contenu de l'encadré.");
        $pdf->article('1', 'Parties', 'Corps de l\'article sur plusieurs lignes avec des accents français et des montants 3 200,00 €.');
        $pdf->signatureBlock(
            "Pour l'employeur\nSociété test\nNom et prénom : …………\nQualité : …………\nFait à ……, le ……\nSignature (mention « Lu et approuvé »)",
            "Le salarié\nJean Dupont\nNom et prénom : …………\nFait à ……, le ……\nSignature précédée de la mention « Lu et approuvé »"
        );

        $binary = $pdf->output();
        self::assertStringContainsString('%PDF-', substr($binary, 0, 5));
        self::assertTrue(
            str_contains($this->decompressedText($binary), $pdf->t('RELECTURE JURIDIQUE OBLIGATOIRE')),
            'L\'encadré de relecture juridique doit être présent dans le document.'
        );
    }

    public function testOnePageDocumentInterfaceExists(): void
    {
        self::assertTrue(interface_exists(OnePageDocument::class));
    }

    /**
     * Concatène le contenu de tous les flux, décompressés si nécessaire
     * (FlateDecode), pour permettre des recherches textuelles.
     */
    private function decompressedText(string $binary): string
    {
        $text = '';
        preg_match_all('/stream\r?\n(.*?)endstream/s', $binary, $matches);
        foreach ($matches[1] as $raw) {
            $decoded = @gzuncompress($raw);
            $text .= $decoded === false ? $raw : $decoded;
        }
        return $text;
    }

    /**
     * Extrait les opérateurs de couleur RVB (rg/RG) des flux, décompressés
     * si nécessaire (FlateDecode).
     *
     * @return list<array{0:float,1:float,2:float}>
     */
    private function extractColorOperators(string $binary): array
    {
        $operators = [];
        if (preg_match_all('/(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(?:rg|RG)/', $this->decompressedText($binary), $colors, PREG_SET_ORDER)) {
            foreach ($colors as $color) {
                $operators[] = [(float) $color[1], (float) $color[2], (float) $color[3]];
            }
        }
        return $operators;
    }
}
