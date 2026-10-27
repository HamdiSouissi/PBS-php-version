<?php

declare(strict_types=1);

namespace Modules\Invoicing\Tests\Unit;

use Modules\Invoicing\Services\VatService;
use Tests\Core\TestCase;

final class VatServiceTest extends TestCase
{
    public function testLineComputationAtStandardRate(): void
    {
        $service = new VatService();
        $result = $service->computeLine([
            'label' => 'Prestation', 'quantity' => 10, 'unit_price' => 100.0, 'vat_rate' => 20.0,
        ]);
        self::assertSame(1000.0, $result['line_ht']);
        self::assertSame(200.0, $result['line_vat']);
        self::assertSame(1200.0, $result['line_ttc']);
    }

    public function testCommercialRoundingHalfUp(): void
    {
        $service = new VatService();
        // 3 × 9.99 = 29.97 ; TVA 5.5 % = 1.64835 → 1.65 (arrondi commercial).
        $result = $service->computeLine([
            'label' => 'Article', 'quantity' => 3, 'unit_price' => 9.99, 'vat_rate' => 5.5,
        ]);
        self::assertSame(29.97, $result['line_ht']);
        self::assertSame(1.65, $result['line_vat']);
        self::assertSame(31.62, $result['line_ttc']);
    }

    public function testDiscountReducesLineHt(): void
    {
        $service = new VatService();
        $result = $service->computeLine([
            'label' => 'Soldé', 'quantity' => 1, 'unit_price' => 200.0, 'vat_rate' => 20.0, 'discount_pct' => 10.0,
        ]);
        self::assertSame(180.0, $result['line_ht']);
        self::assertSame(36.0, $result['line_vat']);
    }

    public function testDocumentAggregatesVatByRate(): void
    {
        $service = new VatService();
        $totals = $service->computeDocument([
            ['label' => 'A', 'quantity' => 1, 'unit_price' => 100.0, 'vat_rate' => 20.0],
            ['label' => 'B', 'quantity' => 1, 'unit_price' => 50.0, 'vat_rate' => 5.5],
        ]);
        self::assertSame(150.0, $totals['total_ht']);
        self::assertSame(22.75, $totals['total_vat']); // 20.00 + 2.75 (50 × 5.5 %)
        self::assertSame(172.75, $totals['total_ttc']);
        self::assertArrayHasKey('20', $totals['vat_by_rate']);
        self::assertArrayHasKey('5.5', $totals['vat_by_rate']);
    }

    public function testEmptyDocumentYieldsZeroTotals(): void
    {
        $service = new VatService();
        $totals = $service->computeDocument([]);
        self::assertSame(0.0, $totals['total_ht']);
        self::assertSame(0.0, $totals['total_vat']);
        self::assertSame(0.0, $totals['total_ttc']);
    }
}
