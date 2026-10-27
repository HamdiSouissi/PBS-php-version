<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

/**
 * Calculs de TVA selon les règles françaises :
 * les totaux de ligne HT sont arrondis à 2 décimales (arrondi commercial),
 * la TVA de ligne est calculée sur le HT arrondi, et les totaux document
 * sont la somme des lignes (jamais arrondis une seconde fois).
 */
final class VatService
{
    public const RATES = [20.0, 10.0, 5.5, 2.1, 0.0];

    /**
     * @param array<string,mixed> $line label, quantity, unit_price, vat_rate, discount_pct
     * @return array<string,float> line_ht, line_vat, line_ttc
     */
    public function computeLine(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 1);
        $unitPrice = (float) ($line['unit_price'] ?? 0);
        $vatRate = (float) ($line['vat_rate'] ?? 20.0);
        $discountPct = (float) ($line['discount_pct'] ?? 0);

        $grossHt = $quantity * $unitPrice;
        $lineHt = $this->round2($grossHt * (1 - $discountPct / 100));
        $lineVat = $this->round2($lineHt * $vatRate / 100);

        return [
            'label' => (string) ($line['label'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => $vatRate,
            'discount_pct' => $discountPct,
            'line_ht' => $lineHt,
            'line_vat' => $lineVat,
            'line_ttc' => $this->round2($lineHt + $lineVat),
        ];
    }

    /**
     * @param list<array<string,mixed>> $lines
     * @return array{total_ht:float,total_vat:float,total_ttc:float,vat_by_rate:array<int,float>,lines:list<array<string,mixed>>}
     */
    public function computeDocument(array $lines): array
    {
        $totalHt = 0.0;
        $totalVat = 0.0;
        $vatByRate = [];
        $computed = [];

        foreach ($lines as $index => $line) {
            $amounts = $this->computeLine($line);
            $computed[] = array_merge($amounts, ['sort_order' => $index]);
            $totalHt += $amounts['line_ht'];
            $totalVat += $amounts['line_vat'];
            $rateKey = (string) (float) ($line['vat_rate'] ?? 20.0);
            $vatByRate[$rateKey] = ($vatByRate[$rateKey] ?? 0.0) + $amounts['line_vat'];
        }

        return [
            'total_ht' => $this->round2($totalHt),
            'total_vat' => $this->round2($totalVat),
            'total_ttc' => $this->round2($totalHt + $totalVat),
            'vat_by_rate' => array_map($this->round2(...), $vatByRate),
            'lines' => $computed,
        ];
    }

    /**
     * Arrondi commercial à 2 décimales (half away from zero), norme comptable FR.
     */
    public function round2(float $value): float
    {
        return (float) number_format(round($value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
