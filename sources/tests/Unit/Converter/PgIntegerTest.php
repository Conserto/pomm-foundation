<?php

declare(strict_types=1);

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
use PommProject\Foundation\Converter\PgInteger;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgInteger::class)]
final class PgIntegerTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException from buildSession() / PgInteger::fromPg()
     */
    #[TestWith([null, null])]
    #[TestWith(['0', 0])]
    #[TestWith(['2015', 2015])]
    #[TestWith(['3.141596', 3])]
    public function testFromPg(?string $input, ?int $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgInteger()->fromPg($input, 'int4', $session));
    }

    /**
     * @throws FoundationException from buildSession() / PgInteger::toPg()
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgInteger();

        self::assertSame("int4 '2014'", $converter->toPg(2014, 'int4', $session));
        // Floats are rounded half to even, as PostgreSQL stores a double precision into an integer column.
        self::assertSame("int4 '2'", $converter->toPg(1.6180339887499, 'int4', $session));
        self::assertSame('NULL::int4', $converter->toPg(null, 'int4', $session));
        self::assertSame("int4 '0'", $converter->toPg(0, 'int4', $session));
        self::assertSame("int4 '-42'", $converter->toPg(-42, 'int4', $session));
        self::assertSame("int4 '2'", $converter->toPg(IntBackedEnum::TWO, 'int4', $session));

        self::assertIncompatibleEnum(
            fn (): string => $converter->toPg(BackedEnum::A, 'int4', $session),
            BackedEnum::class,
            'int4'
        );
        self::assertIncompatibleEnum(
            fn (): string => $converter->toPg(UnitEnum::Active, 'int4', $session),
            UnitEnum::class,
            'int4'
        );
    }

    /**
     * @throws FoundationException from buildSession() / PgInteger::toPgStandardFormat()
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgInteger();

        self::assertSame('2014', $converter->toPgStandardFormat(2014, 'int4', $session));
        self::assertSame('2', $converter->toPgStandardFormat(1.6180339887499, 'int4', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'int4', $session));
        self::assertSame('-42', $converter->toPgStandardFormat(-42, 'int4', $session));
        self::assertSame('2', $converter->toPgStandardFormat(IntBackedEnum::TWO, 'int4', $session));

        self::assertIncompatibleEnum(
            fn (): ?string => $converter->toPgStandardFormat(BackedEnum::A, 'int4', $session),
            BackedEnum::class,
            'int4'
        );
        self::assertIncompatibleEnum(
            fn (): ?string => $converter->toPgStandardFormat(UnitEnum::Active, 'int4', $session),
            UnitEnum::class,
            'int4'
        );
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
     * Strings are passed unchanged, PostgreSQL reads them by its own rules; floats are rounded half to even, as
     * PostgreSQL stores a double precision into an integer column.
     *
     * @throws FoundationException
     */
    #[TestWith([12, '12', 12])]
    #[TestWith(['12', '12', 12])]
    #[TestWith([' 7 ', ' 7 ', 7])]
    #[TestWith(['+3', '+3', 3])]
    #[TestWith(['007', '007', 7])]
    #[TestWith([12.0, '12', 12])]
    #[TestWith([12.5, '12', 12])]
    #[TestWith([13.5, '14', 14])]
    #[TestWith([-12.5, '-12', -12])]
    #[TestWith([12.7, '13', 13])]
    public function testPostgresReadsIntegers(int|float|string $input, string $text, int $expected): void
    {
        $session = $this->buildSession();

        self::assertSame($text, new PgInteger()->toPgStandardFormat($input, 'int4', $session));
        self::assertSame($expected, $this->sendToPostgres($input, 'int4', $session));
        self::assertSame($expected, $session->getQueryManager()->query(sprintf('select %s::float8::int4 as v', var_export($input, true)))->current()['v'] ?? null);
    }

    /**
     * Values PHP used to silently turn into another integer (0, 12, a clamped value) are rejected by PostgreSQL.
     *
     * @throws FoundationException
     */
    #[TestWith([''])]
    #[TestWith(['abc'])]
    #[TestWith(['12.7'])]
    #[TestWith(['99999999999999999999'])]
    #[TestWith([1e20])]
    public function testPostgresRejectsNonIntegers(float|string $input): void
    {
        $this->expectException(SqlException::class);
        $this->sendToPostgres($input, 'int8', $this->buildSession());
    }

    /**
     * Booleans (PostgreSQL does not store them into an integer column), NAN / INF, arrays and objects are rejected
     * before reaching PostgreSQL.
     *
     * @throws FoundationException
     */
    public function testRejectsBooleansAndNonNumbers(): void
    {
        $session = $this->buildSession();
        $converter = new PgInteger();

        foreach ([true, false, NAN, INF, [1], new \stdClass()] as $input) {
            try {
                $converter->toPgStandardFormat($input, 'int4', $session);
                self::fail(sprintf("A ConverterException was expected for a '%s' value.", get_debug_type($input)));
            } catch (ConverterException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
