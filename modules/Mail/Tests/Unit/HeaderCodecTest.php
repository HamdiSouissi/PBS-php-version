<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Unit;

use Modules\Mail\Mime\HeaderCodec;
use Tests\Core\TestCase;

final class HeaderCodecTest extends TestCase
{
    public function testDecodesAddressListWithEncodedNames(): void
    {
        $value = '=?UTF-8?B?' . base64_encode('Jean Dupont') . '?= <jean@pbs.fr>, marie@pbs.fr, '
            . '=?UTF-8?B?' . base64_encode('Anaïs Martin') . '?= <anais@pbs.fr>';

        $addresses = HeaderCodec::decodeAddressList($value);

        self::assertCount(3, $addresses);
        self::assertSame('Jean Dupont', $addresses[0]['name']);
        self::assertSame('jean@pbs.fr', $addresses[0]['email']);
        self::assertSame('', $addresses[1]['name']);
        self::assertSame('marie@pbs.fr', $addresses[1]['email']);
        self::assertSame('Anaïs Martin', $addresses[2]['name']);
    }

    public function testRoundTripsEncodedHeader(): void
    {
        $encoded = HeaderCodec::encodeHeader('Facture n° 2026-08');
        self::assertStringContainsString('=?UTF-8?B?', $encoded);
        self::assertSame('Facture n° 2026-08', HeaderCodec::decodeHeader($encoded));
    }

    public function testAsciiHeaderIsLeftUntouched(): void
    {
        self::assertSame('Plain subject', HeaderCodec::encodeHeader('Plain subject'));
    }
}
