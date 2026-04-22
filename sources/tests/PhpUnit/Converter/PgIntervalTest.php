<?php

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\PhpUnit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\PgInterval;
use PommProject\Foundation\Exception\ConverterException;

#[CoversClass(PgInterval::class)]
class PgIntervalTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgInterval();

        self::assertNull($converter->fromPg(null, 'interval', $session));

        // Sub-second precision is truncated
        self::assertEquals(
            new \DateInterval('P14346DT22H47M3S'),
            $converter->fromPg('P14346DT22H47M3.138892S', 'interval', $session)
        );

        try {
            $converter->fromPg('P-11D', 'interval', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('is not an ISO8601 interval representation', $e->getMessage());
        }
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgInterval();

        self::assertSame('NULL::interval', $converter->toPg(null, 'interval', $session));
        self::assertSame(
            "interval '00 years 00 months 14346 days 22:47:03'",
            $converter->toPg(new \DateInterval('P14346DT22H47M3S'), 'interval', $session)
        );

        try {
            $converter->toPg('foo', 'interval', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('First argument is not a \DateInterval instance', $e->getMessage());
        }
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgInterval();

        self::assertNull($converter->toPgStandardFormat(null, 'interval', $session));
        self::assertSame(
            '"00 years 00 months 14346 days 22:47:03"',
            $converter->toPgStandardFormat(new \DateInterval('P14346DT22H47M3S'), 'interval', $session)
        );
    }
}
