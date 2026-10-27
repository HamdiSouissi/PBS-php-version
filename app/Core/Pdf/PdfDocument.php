<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Moteur de génération PDF maison basé sur FPDF (vendorisé dans /third_party,
 * aucun chargement Composer requis). FPDF utilise l'encodage Windows-1252 :
 * toutes les chaînes UTF-8 (accents français, €) sont converties à l'écriture.
 */
class PdfDocument
{
    private \FPDF $pdf;

    /** @var array<string,mixed> */
    private array $company = [];

    private string $documentTitle = 'Document';

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
        $this->pdf->SetAutoPageBreak(true, 18);
        $this->pdf->SetMargins(16, 30, 16);
        $this->pdf->headerCallback = function (\FPDF $pdf): void {
            $this->renderHeader($pdf);
        };
        $this->pdf->footerCallback = function (\FPDF $pdf): void {
            $this->renderFooter($pdf);
        };
    }

    /** @param array<string,mixed> $company */
    public function setCompany(array $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function setDocumentTitle(string $title): self
    {
        $this->documentTitle = $title;
        return $this;
    }

    public function addPage(): self
    {
        $this->pdf->AddPage();
        return $this;
    }

    /** Convertit une chaîne UTF-8 vers l'encodage supporté par FPDF. */
    public function t(string $text): string
    {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        return $converted === '' && $text !== '' ? mb_convert_encoding($text, 'Windows-1252//TRANSLIT', 'UTF-8') : $converted;
    }

    public function h1(string $text): void
    {
        $this->pdf->SetTextColor(12, 33, 64);
        $this->pdf->SetFont('Helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, $this->t($text), 0, 1);
        $this->pdf->SetDrawColor(31, 111, 235);
        $this->pdf->SetLineWidth(0.6);
        $this->pdf->Line(16, $this->pdf->GetY(), 60, $this->pdf->GetY());
        $this->pdf->Ln(4);
    }

    public function h2(string $text): void
    {
        $this->ensureSpace(12);
        $this->pdf->SetTextColor(12, 33, 64);
        $this->pdf->SetFont('Helvetica', 'B', 11.5);
        $this->pdf->Cell(0, 8, $this->t(mb_strtoupper($text)), 0, 1);
        $this->pdf->Ln(1);
    }

    public function paragraph(string $text): void
    {
        $this->pdf->SetTextColor(28, 43, 66);
        $this->pdf->SetFont('Helvetica', '', 9.5);
        $this->pdf->MultiCell(0, 5.2, $this->t($text));
        $this->pdf->Ln(1);
    }

    public function smallMuted(string $text): void
    {
        $this->pdf->SetTextColor(100, 116, 139);
        $this->pdf->SetFont('Helvetica', 'I', 8.5);
        $this->pdf->MultiCell(0, 4.8, $this->t($text));
        $this->pdf->Ln(1);
    }

    /**
     * Tableau clé/valeur sur deux colonnes.
     *
     * @param list<array{0:string,1:string}> $rows
     */
    public function keyValueTable(array $rows, float $labelWidth = 62): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $valueWidth = $pageWidth - $labelWidth;
        foreach ($rows as [$label, $value]) {
            $this->ensureSpace(8);
            $x = $this->pdf->GetX();
            $y = $this->pdf->GetY();
            $this->pdf->SetFont('Helvetica', 'B', 9);
            $this->pdf->SetTextColor(90, 107, 133);
            $this->pdf->MultiCell($labelWidth, 7, $this->t($label), 1, 'L', true);
            $maxY = $this->pdf->GetY();
            $this->pdf->SetXY($x + $labelWidth, $y);
            $this->pdf->SetFont('Helvetica', '', 9);
            $this->pdf->SetTextColor(28, 43, 66);
            $this->pdf->MultiCell($valueWidth, 7, $this->t((string) $value), 1, 'L');
            $maxY = max($maxY, $this->pdf->GetY());
            $this->pdf->SetXY($x, $maxY);
        }
        $this->pdf->Ln(2);
    }

    /**
     * Tableau de données avec en-têtes stylisés.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param list<float>   $widths  largeurs (mm) ; la dernière peut être 0 pour absorber le reste
     * @param list<string>  $aligns  L|R|C par colonne
     */
    public function dataTable(array $headers, array $rows, array $widths, array $aligns = []): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $widths = $this->normalizeWidths($widths, $pageWidth);
        $this->ensureSpace(10);

        // En-tête
        $this->pdf->SetFont('Helvetica', 'B', 8.5);
        $this->pdf->SetFillColor(12, 33, 64);
        $this->pdf->SetTextColor(255, 255, 255);
        foreach ($headers as $i => $header) {
            $this->pdf->Cell($widths[$i], 8, $this->t($header), 1, 0, $aligns[$i] ?? 'L', true);
        }
        $this->pdf->Ln();

        // Corps
        $this->pdf->SetFont('Helvetica', '', 8.8);
        $fill = false;
        foreach ($rows as $row) {
            $lineHeight = 6.4;
            $needed = $this->rowHeight($row, $widths, $lineHeight);
            $this->ensureSpace($needed);
            $this->pdf->SetFillColor($fill ? 241 : 248, $fill ? 246 : 251, $fill ? 254 : 253);
            $this->pdf->SetTextColor(28, 43, 66);
            $x = $this->pdf->GetX();
            $y = $this->pdf->GetY();
            $maxY = $y;
            foreach ($row as $i => $cell) {
                $this->pdf->SetXY($x + array_sum(array_slice($widths, 0, $i)), $y);
                $this->pdf->MultiCell($widths[$i], $lineHeight, $this->t((string) $cell), 1, $aligns[$i] ?? 'L', $fill);
                $maxY = max($maxY, $this->pdf->GetY());
            }
            $this->pdf->SetXY($x, $maxY);
            $fill = !$fill;
        }
        $this->pdf->Ln(3);
    }

    public function totalsTable(array $lines): void
    {
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $labelWidth = $pageWidth - 55;
        foreach ($lines as [$label, $amount, $emphasis]) {
            $this->ensureSpace(8);
            $this->pdf->SetFont('Helvetica', $emphasis ? 'B' : '', 9.5);
            if ($emphasis) {
                $this->pdf->SetFillColor(12, 33, 64);
                $this->pdf->SetTextColor(255, 255, 255);
            } else {
                $this->pdf->SetFillColor(245, 248, 253);
                $this->pdf->SetTextColor(28, 43, 66);
            }
            $this->pdf->Cell($labelWidth, 8, $this->t($label), 1, 0, 'L', true);
            $this->pdf->Cell(55, 8, $this->t((string) $amount), 1, 1, 'R', true);
        }
        $this->pdf->Ln(3);
    }

    /** Bloc de signature double (entreprise / cocontractant). */
    public function signatureBlock(string $leftLabel, string $rightLabel): void
    {
        $this->ensureSpace(34);
        $this->pdf->Ln(6);
        $pageWidth = $this->pdf->GetPageWidth() - 32;
        $startY = $this->pdf->GetY();
        $this->pdf->SetTextColor(90, 107, 133);
        $this->pdf->SetFont('Helvetica', '', 8.5);
        $this->pdf->SetXY(16, $startY);
        $this->pdf->MultiCell($pageWidth / 2 - 4, 5, $this->t($leftLabel), 0, 'L');
        $this->pdf->SetXY(16 + $pageWidth / 2 + 4, $startY);
        $this->pdf->MultiCell($pageWidth / 2 - 4, 5, $this->t($rightLabel), 0, 'L');
        $this->pdf->Line(16, $startY + 18, 16 + $pageWidth / 2 - 4, $startY + 18);
        $this->pdf->Line(16 + $pageWidth / 2 + 4, $startY + 18, 16 + $pageWidth, $startY + 18);
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
        // Bandeau navy
        $pdf->SetFillColor(12, 33, 64);
        $pdf->Rect(0, 0, $pdf->GetPageWidth(), 22, 'F');
        // Accent bleu électrique
        $pdf->SetFillColor(31, 111, 235);
        $pdf->Rect(0, 22, $pdf->GetPageWidth(), 0.8, 'F');

        $pdf->SetXY(16, 5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 12);
        $name = (string) ($this->company['legal_name'] ?? 'Prime Business Suite');
        $pdf->Cell(0, 7, $this->t($name), 0, 1);
        $pdf->SetX(16);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(180, 196, 220);
        $details = array_filter([
            $this->company['address'] ?? '',
            trim(($this->company['postal_code'] ?? '') . ' ' . ($this->company['city'] ?? '')),
            $this->company['siret'] ? 'SIRET ' . $this->company['siret'] : '',
        ]);
        $pdf->Cell(0, 5, $this->t(implode(' · ', $details)), 0, 1);

        // Titre du document à droite
        $pdf->SetXY(16, 12.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(47, 129, 247);
        $pdf->Cell(0, 6, $this->t($this->documentTitle), 0, 0, 'R');
    }

    private function renderFooter(\FPDF $pdf): void
    {
        $pdf->SetY(-14);
        $pdf->SetDrawColor(228, 234, 243);
        $pdf->Line(16, $pdf->GetY(), $pdf->GetPageWidth() - 16, $pdf->GetY());
        $pdf->SetFont('Helvetica', 'I', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 6, $this->t(($this->company['legal_name'] ?? 'Prime Business Suite') . ' — document généré par Prime Business Suite'), 0, 0, 'L');
        $pdf->Cell(0, 6, $this->t('Page ' . $pdf->PageNo()), 0, 0, 'R');
    }

    private function ensureSpace(float $height): void
    {
        if ($this->pdf->GetY() + $height > $this->pdf->GetPageHeight() - 20) {
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

    /** @param list<string> $row @param list<float> $widths */
    private function rowHeight(array $row, array $widths, float $lineHeight): float
    {
        $maxLines = 1;
        foreach ($row as $i => $cell) {
            $lines = count(explode("\n", $this->t((string) $cell)));
            $estimation = (int) ceil($this->pdf->GetStringWidth($this->t((string) $cell)) / max(1, $widths[$i] - 2));
            $maxLines = max($maxLines, $lines, $estimation);
        }
        return $lineHeight * min(4, $maxLines);
    }
}
