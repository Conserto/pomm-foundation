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
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgFloat::class)]
class PgFloatTest extends BaseConverterTestCase
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
}
