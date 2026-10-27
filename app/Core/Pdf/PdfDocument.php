<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Moteur de génération PDF maison basé sur FPDF (vendorisé dans /third_party,
 * aucun chargement Composer requis). FPDF utilise l'encodage Windows-1252 :
 * toutes les chaînes UTF-8 (accents français, €) sont converties à l'écriture.
 *
 * Charte graphique des documents :
 *  - fond blanc, texte foncé, aucune teinte bleue ni dégradé ;
 *  - l'accent corail n'est utilisé que pour les filets, titres et totaux ;
 *  - en-tête = logo + identité société uniquement (le type et le numéro de
 *    document vivent dans le corps) ;
 *  - pied de page répété sur chaque page : coordonnées, mentions légales et
 *    pagination « Page X/Y » ;
 *  - tableaux à filets fins, montants alignés à droite, en-têtes répétés à
 *    chaque saut de page et lignes jamais orphelines de leur en-tête.
 */
class PdfDocument
{
    /** Encre principale (texte fort). */
    private const INK = [31, 28, 24];
    /** Texte courant. */
    private const BODY = [45, 41, 34];
    /** Texte secondaire. */
    private const MUTED = [115, 109, 97];
    /** Filets neutres. */
    private const HAIRLINE = [225, 221, 213];
    /** Accent bleu institutionnel (couleur pleine, identique à la charte écran). */
    private const ACCENT = [11, 85, 189];
    /** Fond bleu très atténué pour le total TTC. */
    private const ACCENT_SOFT = [232, 240, 253];
    /** Fond neutre des lignes alternées. */
    private const ZEBRA = [248, 247, 244];

    private \FPDF $pdf;

    /** @var array<string,mixed> identité société (config centrale surchargée par la base) */
    private array $company = [];

    private string $documentTitle = 'Document';

    /** Facteur de densité typographique (1 = normal, <1 = compact pour les bulletins). */
    private float $density = 1.0;

    /**
     * Mode compact des tableaux : hauteurs de lignes resserrées, utilisé par
     * les bulletins chargés afin de garantir la tenue sur une seule page.
     */
    private bool $compactRows = false;

    /**
     * Mode très serré : gouttes et intertitres réduits au minimum, activé
     * uniquement en dernier recours pour garantir la mono-page d'un bulletin.
     */
    private bool $tightSpacing = false;

    public function __construct(string $orientation = 'P', string $format = 'A4')
    {
        $fpdf = base_path('third_party/fpdf/fpdf.php');
        if (!is_file($fpdf)) {
            throw new \RuntimeException('Bibliothèque FPDF introuvable (third_party/fpdf).');
        }
        require_once $fpdf;
        $this->pdf = new class ($orientation, 'mm', $format) extends \FPDF {
            public ?\Closure $headerCallback = null;
            public ?\Closure $footerCallback = null;

            public function Header(): void
            {
                if ($this->headerCallback !== null) {
                    ($this->headerCallback)($this);
                }
            }

            public function Footer(): void
            {
                if ($this->footerCallback !== null) {
                    ($this->footerCallback)($this);
                }
            }
        };
        $this->pdf->SetMargins(16, 30, 16);
        $this->pdf->SetAutoPageBreak(true, 22);
        $this->pdf->AliasNbPages('{nb}');
        $this->pdf->headerCallback = function (\FPDF $pdf): void {
            $this->renderHeader($pdf);
        };
        $this->pdf->footerCallback = function (\FPDF $pdf): void {
            $this->renderFooter($pdf);
        };
    }

    /**
     * Identité société : la configuration centrale (config/company.php) fournit
     * les valeurs officielles par défaut ; la base les surcharge quand elle
     * porte une valeur non vide.
     *
     * @param array<string,mixed> $company
     */
    public function setCompany(array $company): self
    {
        $defaults = function_exists('config') ? (array) config('company') : [];
        $merged = $defaults;
        foreach ($company as $key => $value) {
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }
        $this->company = $merged;
        return $this;
    }

    public function setDocumentTitle(string $title): self
    {
        $this->documentTitle = $title;
        return $this;
    }

    /**
     * Densité typographique utilisée par les bulletins de paie pour tenir
     * sur une seule page (facteur compris entre 0.75 et 1.0).
     */
    public function setDensity(float $factor): self
    {
        $this->density = max(0.75, min(1.0, $factor));
        return $this;
    }

    /**
     * Active le mode lignes compactes pour les tableaux (bulletin de paie
     * volumineux). Réduit les hauteurs de lignes sans toucher aux données.
     */
    public function setCompactRows(bool $compact): self
    {
        $this->compactRows = $compact;
        return $this;
    }

    /**
     * Dernier niveau de compactage : espacements verticaux minimaux.
     */
    public function setTightSpacing(bool $tight): self
    {
        $this->tightSpacing = $tight;
        return $this;
    }

    public function addPage(): self
    {
        $this->pdf->AddPage();
        return $this;
    }

    public function pageCount(): int
    {
        return $this->pdf->PageNo();
    }

    /**
     * Convertit une chaîne UTF-8 vers l'encodage supporté par FPDF
     * (Windows-1252). Les espaces fines insécables (U+202F, U+2009),
     * notamment produites par NumberFormatter pour les montants en euros,
     * n'existent pas en Windows-1252 : elles sont normalisées en espace
     * insécable (U+00A0) avant transcodage pour ne pas devenir des « ? ».
     */
    public function t(string $text): string
    {
        $normalized = strtr($text, [
            "\u{202F}" => "\u{00A0}",
            "\u{2009}" => "\u{00A0}",
            "\u{2007}" => "\u{00A0}",
            // Équivalents hors table Windows-1252 les plus courants dans les documents métier.
            "\u{2022}" => "\u{00B7}",
            "\u{2192}" => '->',
            "\u{2190}" => '<-',
            "\u{2026}" => '...',
            "\u{2122}" => '(TM)',
            "\u{00AE}" => '(R)',
        ]);
        $converted = @mb_convert_encoding($normalized, 'Windows-1252', 'UTF-8');
        if ($converted === '' && $normalized !== '') {
            $converted = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
        }
        return $converted;
    }

    private function font(float $size): float
    {
        return max(6.5, $size * $this->density);
    }

    private function line(float $height): float
    {
        return max(3.6, $height * $this->density);
    }

    /** Espacement vertical souple (gouttes, retraits) : compressible sans plancher. */
    private function space(float $mm): float
    {
        return $mm * $this->density * ($this->tightSpacing ? 0.65 : 1.0);
    }

    /**
     * Hauteur de ligne de tableau : resserrée en mode compact (bulletins
     * volumineux), normale sinon.
     */
    private function rowLine(float $mm): float
    {
        return $this->line($this->compactRows ? max(4.4, $mm - 0.9) : $mm);
    }

    public function h1(string $text): void
    {
        $this->ensureSpace(16);
        $this->pdf->SetTextColor(...self::INK);
        $this->pdf->SetFont('Helvetica', 'B', $this->font(16));
        $this->pdf->Cell(0, $this->line(9), $this->t($text), 0, 1);
        $y = $this->pdf->GetY();
        $this->pdf->SetDrawColor(...self::ACCENT);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(16, $y, 46, $y);
        $this->pdf->Ln($this->space(4));
    }

    public function h2(string $text): void
    {
        $this->ensureSpace(11);
        $this->pdf->Ln($this->space(1));
        $y = $this->pdf->GetY();
        $this->pdf->SetFillColor(...self::ACCENT);
        $this->pdf->Rect(16, $y + 1.2, 2.4, 2.4, 'F');
        $this->pdf->SetTextColor(...self::INK);
        $this->pdf->SetFont('Helvetica', 'B', $this->font(11));
        $this->pdf->SetX(21);
        $this->pdf->Cell(0, 6, $this->t(mb_strtoupper($text)), 0, 1);
        $this->pdf->Ln($this->space(1.5));
    }

    /**
     * Titre d'article numéroté (contrats).
     */
    public function article(string $number, string $title, string $body): void
    {
        $this->ensureSpace(20);
        $this->pdf->Ln($this->space(1));
        $this->pdf->SetTextColor(...self::ACCENT);
        $this->pdf->SetFont('Helvetica', 'B', $this->font(10.5));
        $this->pdf->Cell(0, 6, $this->t('Article ' . $number . ' — ' . $title), 0, 1);
        $this->paragraph($body);
    }

    public function paragraph(string $text): void
    {
        $this->pdf->SetTextColor(...self::BODY);
        $this->pdf->SetFont('Helvetica', '', $this->font(9.5));
        $this->pdf->MultiCell(0, $this->line(5.2), $this->t($text));
        $this->pdf->Ln($this->space(1.2));
    }

    public function smallMuted(string $text): void
    {
        $this->pdf->SetTextColor(...self::MUTED);
        $this->pdf->SetFont('Helvetica', 'I', $this->font(8.2));
        $this->pdf->MultiCell(0, $this->line(4.6), $this->t($text));
        $this->pdf->Ln($this->space(1));
    }

    /**
     * Encadré d'information (ex : alerte de relecture juridique). La hauteur
     * est calculée à partir du nombre de lignes réellement rendues afin que
     * le cadre ne soit jamais trop petit.
     */
    public function noticeBox(string $text, bool $critical = false): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $innerWidth = $pageWidth - 6;
        $lineHeight = $this->line(5.2);
        $this->pdf->SetFont('Helvetica', '', $this->font(9));
        $lineCount = 1;
        foreach (explode("\n", $this->t($text)) as $chunk) {
            $lineCount += max(1, (int) ceil($this->pdf->GetStringWidth($chunk) / max(1, $innerWidth)));
        }
        $height = max(10, $lineCount * $lineHeight + 5);
        $this->ensureSpace($height + 2);

        $y = $this->pdf->GetY();
        $this->pdf->SetFillColor(...self::ACCENT_SOFT);
        $this->pdf->SetDrawColor(...self::ACCENT);
        $this->pdf->SetLineWidth($critical ? 0.7 : 0.3);
        $this->pdf->Rect(16, $y, $pageWidth, $height, 'DF');
        $this->pdf->SetTextColor(...self::ACCENT);
        $this->pdf->SetXY(19, $y + 2.5);
        $this->pdf->MultiCell($innerWidth, $lineHeight, $this->t($text));
        $this->pdf->SetXY(16, $y + $height + $this->space(3));
    }

    /**
     * Ligne de section pleine largeur utilisée pour regrouper visuellement
     * les lignes d'un tableau (ex : groupes de cotisations sur le bulletin).
     * Elle reste dans le flux du tableau et respecte les sauts de page.
     */
    public function sectionRow(string $label, float $totalWidth): void
    {
        $this->ensureSpace(9);
        $rowHeight = $this->rowLine(5.9);
        $y = $this->pdf->GetY();
        $this->pdf->SetFillColor(...self::ACCENT_SOFT);
        $this->pdf->Rect(16, $y, $totalWidth, $rowHeight, 'F');
        $this->pdf->SetTextColor(...self::ACCENT);
        $this->pdf->SetFont('Helvetica', 'B', $this->font(8.6));
        $this->pdf->SetXY(16, $y);
        $this->pdf->Cell($totalWidth, $rowHeight, '  ' . $this->t(mb_strtoupper($label)), 0, 1, 'L');
        $this->pdf->Ln($this->space(0.6));
    }

    /**
     * Tableau clé/valeur sur deux colonnes, à simples filets horizontaux.
     *
     * @param list<array{0:string,1:string}> $rows
     */
    public function keyValueTable(array $rows, float $labelWidth = 62): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $valueWidth = $pageWidth - $labelWidth;
        foreach ($rows as [$label, $value]) {
            $this->ensureSpace(9);
            $x = $this->pdf->GetX();
            $y = $this->pdf->GetY();
            $this->pdf->SetFont('Helvetica', 'B', $this->font(8.8));
            $this->pdf->SetTextColor(...self::MUTED);
            $this->pdf->MultiCell($labelWidth, $this->rowLine(6.2), $this->t($label), 0, 'L');
            $maxY = $this->pdf->GetY();
            $this->pdf->SetXY($x + $labelWidth, $y);
            $this->pdf->SetFont('Helvetica', '', $this->font(9));
            $this->pdf->SetTextColor(...self::BODY);
            $this->pdf->MultiCell($valueWidth, $this->rowLine(6.2), $this->t((string) $value), 0, 'L');
            $maxY = max($maxY, $this->pdf->GetY());
            $this->pdf->SetDrawColor(...self::HAIRLINE);
            $this->pdf->SetLineWidth(0.2);
            $this->pdf->Line($x, $maxY, $x + $pageWidth, $maxY);
            $this->pdf->SetXY($x, $maxY);
        }
        $this->pdf->Ln($this->space(3));
    }

    /**
     * Deux blocs clé/valeur côte à côte (ex : employeur | salarié sur un
     * bulletin de paie). Les hauteurs de lignes sont synchronisées entre les
     * deux colonnes et les filets horizontaux traversent toute la largeur.
     *
     * @param list<array{0:string,1:string}> $leftRows
     * @param list<array{0:string,1:string}> $rightRows
     */
    public function keyValueColumns(
        string $leftTitle,
        array $leftRows,
        string $rightTitle,
        array $rightRows,
        float $labelWidth = 34
    ): void {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $gutter = 8;
        $colWidth = ($pageWidth - $gutter) / 2;
        $rowHeight = $this->rowLine(6.4);

        $this->ensureSpace(12);
        $this->pdf->SetFillColor(...self::ACCENT_SOFT);
        $this->pdf->SetTextColor(...self::ACCENT);
        $this->pdf->SetFont('Helvetica', 'B', $this->font(8.8));
        $titleY = $this->pdf->GetY();
        $titleH = $this->rowLine(6.2);
        $this->pdf->Rect(16, $titleY, $pageWidth, $titleH, 'F');
        $this->pdf->SetXY(16, $titleY);
        $this->pdf->Cell($colWidth, $titleH, '  ' . $this->t(mb_strtoupper($leftTitle)), 0, 0, 'L');
        $this->pdf->SetXY(16 + $colWidth + $gutter, $titleY);
        $this->pdf->Cell($colWidth, $titleH, '  ' . $this->t(mb_strtoupper($rightTitle)), 0, 1, 'L');
        $this->pdf->Ln($this->space(0.8));

        $count = max(count($leftRows), count($rightRows));
        for ($i = 0; $i < $count; $i++) {
            $this->ensureSpace(9);
            $y = $this->pdf->GetY();
            $maxY = $y;
            foreach ([0 => [$leftRows, 16, $colWidth], 1 => [$rightRows, 16 + $colWidth + $gutter, $colWidth]] as $side => [$rows, $x, $width]) {
                if (!isset($rows[$i])) {
                    continue;
                }
                [$label, $value] = $rows[$i];
                $this->pdf->SetXY($x, $y);
                $this->pdf->SetFont('Helvetica', 'B', $this->font(8.4));
                $this->pdf->SetTextColor(...self::MUTED);
                $this->pdf->MultiCell($labelWidth, $rowHeight, $this->t((string) $label), 0, 'L');
                $this->pdf->SetXY($x + $labelWidth, $y);
                $this->pdf->SetFont('Helvetica', '', $this->font(8.6));
                $this->pdf->SetTextColor(...self::BODY);
                $this->pdf->MultiCell($width - $labelWidth, $rowHeight, $this->t((string) $value), 0, 'L');
                $maxY = max($maxY, $this->pdf->GetY());
            }
            $this->pdf->SetDrawColor(...self::HAIRLINE);
            $this->pdf->SetLineWidth(0.2);
            $this->pdf->Line(16, $maxY, 16 + $pageWidth, $maxY);
            $this->pdf->SetXY(16, $maxY);
        }
        $this->pdf->Ln($this->space(3));
    }

    /**
     * Tableau de données : en-têtes répétés à chaque saut de page,
     * lignes alternées sur fond neutre, aucun bleu, montants à droite.
     *
     * @param list<string> $headers
     * @param list<list<string>|PdfSection> $rows une PdfSection insère une ligne de regroupement
     * @param list<float>  $widths largeurs (mm) ; une valeur 0 absorbe le reste
     * @param list<string> $aligns L|R|C par colonne
     * @param bool $flush true (défaut) : espacement normal en fin de tableau ;
     *                    false : espacement réduit quand plusieurs groupes se succèdent.
     */
    public function dataTable(array $headers, array $rows, array $widths, array $aligns = [], bool $flush = true): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $widths = $this->normalizeWidths($widths, $pageWidth);
        $this->ensureSpace(14);

        $drawHeader = function () use ($headers, $widths, $aligns): void {
            $this->pdf->SetFont('Helvetica', 'B', $this->font(8.5));
            $this->pdf->SetTextColor(...self::INK);
            $this->pdf->SetDrawColor(...self::ACCENT);
            $this->pdf->SetLineWidth(0.4);
            $x = 16;
            $y = $this->pdf->GetY();
            foreach ($headers as $i => $header) {
                $this->pdf->SetXY($x, $y);
                $this->pdf->Cell($widths[$i], $this->rowLine(7), $this->t($header), 0, 0, $aligns[$i] ?? 'L');
                $x += $widths[$i];
            }
            $this->pdf->Line(16, $y + $this->rowLine(7), 16 + array_sum($widths), $y + $this->rowLine(7));
            $this->pdf->Ln($this->rowLine(7));
        };

        $drawHeader();

        $this->pdf->SetFont('Helvetica', '', $this->font(8.8));
        $zebra = false;
        $rowHeight = $this->rowLine(5.8);
        $tableWidth = array_sum($widths);
        foreach ($rows as $row) {
            if ($row instanceof PdfSection) {
                if ($this->pdf->GetY() + 10 > $this->pdf->GetPageHeight() - 26) {
                    $this->pdf->AddPage();
                    $drawHeader();
                }
                $this->sectionRow($row->label, $tableWidth);
                $zebra = false;
                continue;
            }
            $needed = $this->rowHeight($row, $widths, $rowHeight);
            // Place pour la ligne + sa marge ; sinon nouvelle page et répétition de l'en-tête.
            if ($this->pdf->GetY() + $needed > $this->pdf->GetPageHeight() - 26) {
                $this->pdf->AddPage();
                $drawHeader();
            }
            $this->pdf->SetTextColor(...self::BODY);
            $x = 16;
            $y = $this->pdf->GetY();
            if ($zebra) {
                $this->pdf->SetFillColor(...self::ZEBRA);
                $this->pdf->Rect($x, $y, array_sum($widths), $needed, 'F');
            }
            $maxY = $y;
            foreach ($row as $i => $cell) {
                $this->pdf->SetXY($x, $y);
                $this->pdf->MultiCell($widths[$i], $rowHeight, $this->t((string) $cell), 0, $aligns[$i] ?? 'L');
                $maxY = max($maxY, $this->pdf->GetY());
                $x += $widths[$i];
            }
            $this->pdf->SetDrawColor(...self::HAIRLINE);
            $this->pdf->SetLineWidth(0.15);
            $this->pdf->Line(16, $maxY, 16 + $tableWidth, $maxY);
            $this->pdf->SetXY(16, $maxY);
            $zebra = !$zebra;
        }
        $this->pdf->Ln($flush ? $this->space(4) : $this->space(1.5));
    }

    /**
     * Bloc de totaux hiérarchisés : HT et TVA neutres, TTC en saillie corail.
     *
     * @param list<array{0:string,1:string,2:bool}> $lines
     */
    public function totalsTable(array $lines): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $labelWidth = $pageWidth - 55;
        foreach ($lines as [$label, $amount, $emphasis]) {
            $this->ensureSpace(9);
            $this->pdf->SetFont('Helvetica', $emphasis ? 'B' : '', $this->font($emphasis ? 10.5 : 9.5));
            if ($emphasis) {
                $y = $this->pdf->GetY();
                $this->pdf->SetFillColor(...self::ACCENT_SOFT);
                $this->pdf->Rect(16, $y, $pageWidth, $this->rowLine(7.6), 'F');
                $this->pdf->SetTextColor(...self::ACCENT);
                $this->pdf->SetXY(16, $y);
                $this->pdf->Cell($labelWidth, $this->rowLine(7.6), $this->t($label), 0, 0, 'L');
                $this->pdf->Cell(55, $this->rowLine(7.6), $this->t((string) $amount), 0, 1, 'R');
            } else {
                $this->pdf->SetTextColor(...self::BODY);
                $this->pdf->Cell($labelWidth, $this->rowLine(7.2), $this->t($label), 0, 0, 'L');
                $this->pdf->Cell(55, $this->rowLine(7.2), $this->t((string) $amount), 0, 1, 'R');
            }
        }
        $this->pdf->Ln($this->space(3));
    }

    /**
     * Bloc de signature double. Les libellés peuvent comporter plusieurs
     * lignes (nom, qualité, date, mention « Lu et approuvé ») ; les filets
     * sont positionnés sous le libellé le plus haut, jamais au milieu du texte.
     */
    public function signatureBlock(string $leftLabel, string $rightLabel): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $colWidth = $pageWidth / 2 - 6;
        $lineHeight = $this->line(5);

        // Estimation de la hauteur nécessaire (retours explicites + wrapping).
        $this->pdf->SetFont('Helvetica', '', $this->font(8.5));
        $needed = 0;
        foreach ([$leftLabel, $rightLabel] as $label) {
            $lines = 0;
            foreach (explode("\n", $this->t($label)) as $chunk) {
                $lines += max(1, (int) ceil($this->pdf->GetStringWidth($chunk) / max(1, $colWidth)));
            }
            $needed = max($needed, $lines * $lineHeight);
        }
        $this->ensureSpace($needed + 10);
        $this->pdf->Ln($this->space(8));

        $startY = $this->pdf->GetY();
        $this->pdf->SetTextColor(...self::MUTED);
        $this->pdf->SetXY(16, $startY);
        $this->pdf->MultiCell($colWidth, $lineHeight, $this->t($leftLabel), 0, 'L');
        $leftEndY = $this->pdf->GetY();
        $this->pdf->SetXY(16 + $pageWidth / 2 + 6, $startY);
        $this->pdf->MultiCell($colWidth, $lineHeight, $this->t($rightLabel), 0, 'L');
        $rightEndY = $this->pdf->GetY();

        $this->pdf->SetDrawColor(...self::HAIRLINE);
        $this->pdf->SetLineWidth(0.3);
        $lineY = max($leftEndY, $rightEndY) + 3;
        $this->pdf->Line(16, $lineY, 16 + $colWidth, $lineY);
        $this->pdf->Line(16 + $pageWidth / 2 + 6, $lineY, 16 + $pageWidth, $lineY);
        $this->pdf->SetXY(16, $lineY + 4);
    }

    public function output(): string
    {
        return $this->pdf->Output('S');
    }

    private function renderHeader(\FPDF $pdf): void
    {
        if ($pdf->PageNo() === 0) {
            return;
        }
        $logo = (string) ($this->company['logo_path'] ?? '');
        $rightX = 16;
        if ($logo !== '' && is_file($logo)) {
            try {
                $info = getimagesize($logo);
                if ($info !== false) {
                    $height = 15;
                    $width = $height * ($info[0] / max(1, $info[1]));
                    $pdf->Image($logo, 16, 12, $width, $height, 'PNG');
                    $rightX = 16 + $width + 6;
                }
            } catch (\Throwable) {
                // Logo illisible : on poursuit sans illustration.
            }
        }

        $pageWidth = $pdf->GetPageWidth() - 16;
        $pdf->SetTextColor(...self::INK);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetXY($rightX, 11);
        $name = (string) ($this->company['legal_name'] ?? 'Prime Business Suite');
        $pdf->Cell($pageWidth - $rightX, 6, $this->t($name), 0, 1);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...self::MUTED);
        $addressLine = implode(' ', array_filter([
            (string) ($this->company['address'] ?? ''),
            trim(($this->company['postal_code'] ?? '') . ' ' . ($this->company['city'] ?? '')),
        ]));
        $contactLine = implode('  ·  ', array_filter([
            (string) ($this->company['phone'] ?? ''),
            (string) ($this->company['email'] ?? ''),
            (string) ($this->company['website'] ?? ''),
        ]));
        $identityLine = implode('  ·  ', array_filter([
            !empty($this->company['siret']) ? 'SIRET ' . $this->company['siret'] : '',
            !empty($this->company['vat_number']) ? 'TVA ' . $this->company['vat_number'] : '',
        ]));
        $pdf->SetX($rightX);
        $pdf->Cell($pageWidth - $rightX, 4.6, $this->t($addressLine), 0, 1);
        if ($contactLine !== '') {
            $pdf->SetX($rightX);
            $pdf->Cell($pageWidth - $rightX, 4.6, $this->t($contactLine), 0, 1);
        }
        if ($identityLine !== '') {
            $pdf->SetX($rightX);
            $pdf->Cell($pageWidth - $rightX, 4.6, $this->t($identityLine), 0, 1);
        }

        // Aucun filet coloré entre l'en-tête et le corps : l'en-tête est
        // délimité par la marge haute, les titres de section marquent le contenu.
    }

    private function renderFooter(\FPDF $pdf): void
    {
        $pageWidth = $pdf->GetPageWidth() - 32;
        $pdf->SetDrawColor(...self::HAIRLINE);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(16, -20, $pdf->GetPageWidth() - 16, -20);

        $pdf->SetFont('Helvetica', '', 7.4);
        $pdf->SetTextColor(...self::MUTED);
        $contactLine = implode('  ·  ', array_filter([
            (string) ($this->company['email'] ?? ''),
            (string) ($this->company['phone'] ?? ''),
        ]));
        $postalLine = implode(', ', array_filter([
            (string) ($this->company['address'] ?? ''),
            trim(($this->company['postal_code'] ?? '') . ' ' . ($this->company['city'] ?? '')),
        ]));
        $mentions = (string) ($this->company['legal_mentions'] ?? '');
        if ($mentions === '') {
            $mentions = (string) ($this->company['legal_name'] ?? '');
        }

        $y = -17.5;
        $pdf->SetXY(16, $y);
        $pdf->Cell($pageWidth - 28, 4, $this->t($contactLine), 0, 0, 'L');
        $pdf->Cell(28, 4, $this->t('Page ' . $pdf->PageNo() . ' / {nb}'), 0, 1, 'R');
        $pdf->SetX(16);
        $pdf->Cell($pageWidth, 4, $this->t($postalLine), 0, 1, 'L');
        $pdf->SetX(16);
        $pdf->SetFont('Helvetica', 'I', 6.8);
        $pdf->MultiCell($pageWidth, 3.6, $this->t($mentions), 0, 'L');
    }

    private function ensureSpace(float $height): void
    {
        if ($this->pdf->GetY() + $height > $this->pdf->GetPageHeight() - 26) {
            $this->pdf->AddPage();
        }
    }

    /** @param list<float> $widths */
    private function normalizeWidths(array $widths, float $pageWidth): array
    {
        $fixed = array_sum(array_filter($widths, static fn ($w) => $w > 0));
        foreach ($widths as $i => $w) {
            if ($w <= 0.0) {
                $widths[$i] = max(10, $pageWidth - $fixed);
            }
        }
        return $widths;
    }

    /**
     * @param list<string> $row
     * @param list<float> $widths
     */
    private function rowHeight(array $row, array $widths, float $lineHeight): float
    {
        $maxLines = 1;
        foreach ($row as $i => $cell) {
            $lines = count(explode("\n", $this->t((string) $cell)));
            $estimation = (int) ceil($this->pdf->GetStringWidth($this->t((string) $cell)) / max(1, $widths[$i] - 2));
            $maxLines = max($maxLines, $lines, $estimation);
        }
        return $lineHeight * min(5, $maxLines);
    }
}
