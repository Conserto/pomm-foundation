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

namespace PommProject\Foundation\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\PgInterval;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(PgInterval::class)]
final class PgIntervalTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
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

        // Negative intervals (PostgreSQL signs each component) are read as inverted intervals.
        $negative = $converter->fromPg('P-11D', 'interval', $session);
        self::assertInstanceOf(\DateInterval::class, $negative);
        self::assertSame('-11', $negative->format('%R%d'));

        try {
            $converter->fromPg('not an interval', 'interval', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('is not an ISO8601 interval representation', $e->getMessage());
        }
    }

    /**
     * @throws FoundationException
     */
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

    /**
     * @throws FoundationException
     */
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

    /**
     * Negative intervals used to be unreadable, and written back as positive.
     *
     * @throws FoundationException
     */
    public function testNegativeIntervals(): void
    {
        $session = $this->buildSession();
        $converter = new PgInterval();

        $read = $converter->fromPg('P-1Y-2M-3DT-4H-5M-6S', 'interval', $session);
        self::assertInstanceOf(\DateInterval::class, $read);
        self::assertSame('-1 2 3 4 5 6', $read->format('%R%y %m %d %h %i %s'));

        $diff = new \DateTime('2024-01-10')->diff(new \DateTime('2024-01-01'));
        self::assertSame(1, $diff->invert);
        $roundTrip = $this->sendToPostgres($diff, 'interval', $session);
        self::assertInstanceOf(\DateInterval::class, $roundTrip);
        self::assertSame('-9', $roundTrip->format('%R%d'));

        $positive = $this->sendToPostgres(new \DateInterval('P1DT2H'), 'interval', $session);
        self::assertInstanceOf(\DateInterval::class, $positive);
        self::assertSame('+1 2', $positive->format('%R%d %h'));
    }

    /**
     * @throws FoundationException
     */
    public function testMixedSignIntervalIsRejected(): void
    {
        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage("Interval 'P-1DT2H' mixes positive and negative components");
        new PgInterval()->fromPg('P-1DT2H', 'interval', $this->buildSession());
    }
}
