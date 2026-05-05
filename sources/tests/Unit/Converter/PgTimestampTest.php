<?php

/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\PgTimestamp;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(PgTimestamp::class)]
class PgTimestampTest extends BaseConverterTestCase
{
    private const string PG_TS = '2014-09-27 18:51:35.678406+00';
    private const string PG_STANDARD = '2014-09-27 18:51:35.678406+00:00';

    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgTimestamp();

        $date = $converter->fromPg(self::PG_TS, 'timestamptz', $session);
        self::assertInstanceOf(\DateTime::class, $date);
        self::assertSame('2014-09-27 18:51:35.678406', $date->format('Y-m-d H:i:s.u'));

        self::assertNull($converter->fromPg(null, 'timestamptz', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgTimestamp();

        self::assertSame(
            sprintf("timestamptz '%s'", self::PG_STANDARD),
            $converter->toPg(new \DateTime(self::PG_TS), 'timestamptz', $session)
        );
        self::assertSame(
            sprintf("timestamptz '%s'", self::PG_STANDARD),
            $converter->toPg(new \DateTimeImmutable(self::PG_TS), 'timestamptz', $session)
        );
        self::assertSame('NULL::timestamptz', $converter->toPg(null, 'timestamptz', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgTimestamp();
        $dateTime = new \DateTime(self::PG_TS);
        $dateTimeImmutable = new \DateTimeImmutable(self::PG_TS);

        self::assertSame(
            self::PG_STANDARD,
            $converter->toPgStandardFormat($dateTime, 'timestamptz', $session)
        );
        self::assertSame(
            self::PG_STANDARD,
            $converter->toPgStandardFormat($dateTimeImmutable, 'timestamptz', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'timestamptz', $session));

        $roundTripMutable = $this->sendToPostgres($dateTime, 'timestamptz', $session);
        self::assertInstanceOf(\DateTime::class, $roundTripMutable);
        self::assertEquals($dateTime, $roundTripMutable);

        $roundTripImmutable = $this->sendToPostgres($dateTimeImmutable, 'timestamptz', $session);
        self::assertInstanceOf(\DateTime::class, $roundTripImmutable);
        self::assertEquals($dateTimeImmutable, $roundTripImmutable);
    }
}
