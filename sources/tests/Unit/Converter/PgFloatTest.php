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
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\PgFloat;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgFloat::class)]
final class PgFloatTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
    #[TestWith([null, 'int4', null])]
    #[TestWith(['0', 'int4', 0.0])]
    #[TestWith(['2015', 'int4', 2015.0])]
    #[TestWith(['3.141596', 'float4', 3.141596])]
    public function testFromPg(?string $input, string $pgType, ?float $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgFloat()->fromPg($input, $pgType, $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgFloat();

        self::assertSame("float8 '2014'", $converter->toPg(2014, 'float8', $session));
        self::assertSame("float8 '1.6180339887499'", $converter->toPg(1.6180339887499, 'float8', $session));
        self::assertSame('NULL::float8', $converter->toPg(null, 'float8', $session));
        self::assertSame("float8 '0'", $converter->toPg(0, 'float8', $session));
        self::assertSame("float8 '2'", $converter->toPg(IntBackedEnum::TWO, 'float8', $session));
        self::assertSame("float8 '42.5'", $converter->toPg(BackedEnum::NUMERIC, 'float8', $session));

        self::assertIncompatibleEnum(
            fn (): string => $converter->toPg(BackedEnum::A, 'float8', $session),
            BackedEnum::class,
            'float8'
        );
        self::assertIncompatibleEnum(
            fn (): string => $converter->toPg(UnitEnum::Active, 'float8', $session),
            UnitEnum::class,
            'float8'
        );
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgFloat();

        self::assertSame('2014', $converter->toPgStandardFormat(2014, 'float8', $session));
        self::assertSame('1.6180339887499', $converter->toPgStandardFormat(1.6180339887499, 'float8', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'float8', $session));
        self::assertSame('2', $converter->toPgStandardFormat(IntBackedEnum::TWO, 'float8', $session));
        self::assertSame('42.5', $converter->toPgStandardFormat(BackedEnum::NUMERIC, 'float8', $session));

        self::assertIncompatibleEnum(
            fn (): ?string => $converter->toPgStandardFormat(BackedEnum::A, 'float8', $session),
            BackedEnum::class,
            'float8'
        );
        self::assertIncompatibleEnum(
            fn (): ?string => $converter->toPgStandardFormat(UnitEnum::Active, 'float8', $session),
            UnitEnum::class,
            'float8'
        );
    }

    /**
     * A float8 is written with the digits needed to read it back, a numeric with 15 significant digits as PostgreSQL's
     * float8 to numeric cast. Converting a float to a string used to keep only 14 digits.
     *
     * @throws FoundationException|SqlException
     */
    public function testFullPrecision(): void
    {
        $session = $this->buildSession();
        $converter = new PgFloat();
        $third = 1 / 3;
        $sum = 0.1 + 0.2;

        self::assertSame('0.3333333333333333', $converter->toPgStandardFormat($third, 'float8', $session));
        self::assertSame('0.30000000000000004', $converter->toPgStandardFormat($sum, 'float8', $session));
        self::assertSame('0.1', $converter->toPgStandardFormat(0.1, 'float8', $session));
        self::assertSame($third, $this->sendToPostgres($third, 'float8', $session));
        self::assertSame($sum, $this->sendToPostgres($sum, 'float8', $session));
        self::assertSame("float8 '0.3333333333333333'", $converter->toPg($third, 'float8', $session));
        self::assertSame("numeric '0.3'", $converter->toPg($sum, 'numeric', $session));

        self::assertSame('0.333333333333333', $converter->toPgStandardFormat($third, 'numeric', $session));
        self::assertSame('0.3', $converter->toPgStandardFormat($sum, 'numeric', $session));
        self::assertSame('1234567890123.45', $converter->toPgStandardFormat(1234567890123.45, 'numeric', $session));
        self::assertSame(0.3, $this->sendToPostgres($sum, 'numeric', $session));
        self::assertSame(1e20, $this->sendToPostgres(1e20, 'numeric', $session));
        self::assertSame(-1.0e-5, $this->sendToPostgres(-1.0e-5, 'float8', $session));
    }

    /**
     * A number is written without the connection: building SQL with a float needs no open session.
     *
     * @throws FoundationException
     */
    public function testToPgWritesNumbersWithoutTheConnection(): void
    {
        $session = $this->buildSession();
        $session->shutdown();
        $converter = new PgFloat();

        self::assertSame("float8 '1.5'", $converter->toPg(1.5, 'float8', $session));
        self::assertSame("float8 '2'", $converter->toPg(2, 'float8', $session));
        self::assertSame("float8 'NaN'", $converter->toPg(\NAN, 'float8', $session));
    }

    /**
     * toPg() escapes the value: a string used to be written between quotes as is.
     *
     * @throws FoundationException
     */
    public function testToPgEscapes(): void
    {
        $session = $this->buildSession();
        $sql = new PgFloat()->toPg("1'; select 1; --", 'float8', $session);

        self::assertSame("float8 '1''; select 1; --'", $sql);
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('invalid input syntax for type double precision');
        $session->getConnection()->executeAnonymousQuery('select ' . $sql);
    }

    private static function assertIncompatibleEnum(callable $call, string $enumClass, string $pgType): void
    {
        try {
            $call();
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertSame(
                sprintf("Enum '%s' is not compatible with Pg type '%s'.", $enumClass, $pgType),
                $e->getMessage()
            );
        }
    }

    /**
     * PostgreSQL's special values used to be read as 0.0.
     *
     * @throws FoundationException
     */
    public function testSpecialValuesFromPostgres(): void
    {
        $session = $this->buildSession();
        $converter = new PgFloat();

        foreach (['float8', 'numeric'] as $type) {
            self::assertNan($converter->fromPg('NaN', $type, $session));
            self::assertSame(\INF, $converter->fromPg('Infinity', $type, $session));
            self::assertSame(-\INF, $converter->fromPg('-Infinity', $type, $session));
            self::assertNan($this->sendToPostgres(\NAN, $type, $session));
            self::assertSame(\INF, $this->sendToPostgres(\INF, $type, $session));
            self::assertSame(-\INF, $this->sendToPostgres(-\INF, $type, $session));
        }
    }
}
