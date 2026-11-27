<?php

declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\PgTimestamp;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;

#[CoversClass(PgTimestamp::class)]
final class PgTimestampTest extends BaseConverterTestCase
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

    /**
     * 'infinity' has no DateTime equivalent: a ConverterException instead of PHP's DateMalformedStringException.
     *
     * @throws FoundationException
     */
    public function testInfinityFromPostgres(): void
    {
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage("Cannot convert PostgreSQL value 'infinity' to a DateTime.");
        $session->getQueryManager()->query("select 'infinity'::timestamp as v")->current();
    }

    /**
     * Strings are passed unchanged, PostgreSQL reads them by its own rules.
     *
     * @throws FoundationException
     */
    #[TestWith(['2024-01-02', 'date', '2024-01-02'])]
    #[TestWith(['2024-01-02 12:00:00+02:00', 'timestamptz', '2024-01-02'])]
    #[TestWith(['19940623', 'date', '1994-06-23'])]
    #[TestWith(['2050/03/29', 'date', '2050-03-29'])]
    #[TestWith(['epoch', 'date', '1970-01-01'])]
    public function testPostgresReadsDates(string $input, string $type, string $day): void
    {
        $session = $this->buildSession();

        self::assertSame($input, new PgTimestamp()->toPgStandardFormat($input, $type, $session));
        $date = $this->sendToPostgres($input, $type, $session);
        self::assertInstanceOf(\DateTimeInterface::class, $date);
        self::assertSame($day, $date->format('Y-m-d'));
    }

    /**
     * Values PHP used to silently turn into "now" or another date are rejected by PostgreSQL.
     *
     * @throws FoundationException
     */
    #[TestWith([''])]
    #[TestWith(['2024-02-30'])]
    #[TestWith(['+1 day'])]
    #[TestWith(['t'])]
    #[TestWith(['12.7'])]
    #[TestWith(['26-11-25'])]
    #[TestWith(['03:04:05'])]
    public function testPostgresRejectsInvalidDates(string $input): void
    {
        $this->expectException(SqlException::class);
        $this->sendToPostgres($input, 'date', $this->buildSession());
    }

    /**
     * Booleans and numbers (PostgreSQL does not store them into a date column; false used to become now), arrays and
     * non DateTime objects are rejected before reaching PostgreSQL.
     *
     * @throws FoundationException
     */
    public function testRejectsBooleansAndNumbers(): void
    {
        $session = $this->buildSession();
        $converter = new PgTimestamp();

        foreach ([false, true, 20240101, 1.5, NAN, ['2024-01-02'], new \stdClass()] as $input) {
            try {
                $converter->toPgStandardFormat($input, 'date', $session);
                self::fail(sprintf("A ConverterException was expected for a '%s' value.", get_debug_type($input)));
            } catch (ConverterException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * A DateTime with __toString() (Carbon...) is Stringable: it is still formatted, its string form could lose the
     * time zone or the microseconds.
     *
     * @throws FoundationException
     */
    public function testFormatsStringableDateTime(): void
    {
        $date = new class ('2024-01-02 03:04:05.123456+09:00') extends \DateTime {
            public function __toString(): string
            {
                return $this->format('Y-m-d H:i:s');
            }
        };

        self::assertSame(
            '2024-01-02 03:04:05.123456+09:00',
            new PgTimestamp()->toPgStandardFormat($date, 'timestamptz', $this->buildSession())
        );
    }
}
