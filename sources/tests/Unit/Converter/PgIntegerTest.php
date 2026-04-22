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
use PommProject\Foundation\Converter\PgInteger;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgInteger::class)]
class PgIntegerTest extends BaseConverterTestCase
{
    #[TestWith([null, null])]
    #[TestWith(['0', 0])]
    #[TestWith(['2015', 2015])]
    #[TestWith(['3.141596', 3])]
    public function testFromPg(?string $input, ?int $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgInteger()->fromPg($input, 'int4', $session));
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgInteger();

        self::assertSame("int4 '2014'", $converter->toPg(2014, 'int4', $session));
        self::assertSame("int4 '1'", $converter->toPg(1.6180339887499, 'int4', $session));
        self::assertSame('NULL::int4', $converter->toPg(null, 'int4', $session));
        self::assertSame("int4 '0'", $converter->toPg(0, 'int4', $session));
        self::assertSame("int4 '-42'", $converter->toPg(-42, 'int4', $session));
        self::assertSame("int4 '2'", $converter->toPg(IntBackedEnum::TWO, 'int4', $session));

        self::assertIncompatibleEnum(
            fn () => $converter->toPg(BackedEnum::A, 'int4', $session),
            BackedEnum::class,
            'int4'
        );
        self::assertIncompatibleEnum(
            fn () => $converter->toPg(UnitEnum::Active, 'int4', $session),
            UnitEnum::class,
            'int4'
        );
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgInteger();

        self::assertSame('2014', $converter->toPgStandardFormat(2014, 'int4', $session));
        self::assertSame('1', $converter->toPgStandardFormat(1.6180339887499, 'int4', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'int4', $session));
        self::assertSame('-42', $converter->toPgStandardFormat(-42, 'int4', $session));
        self::assertSame('2', $converter->toPgStandardFormat(IntBackedEnum::TWO, 'int4', $session));

        self::assertIncompatibleEnum(
            fn () => $converter->toPgStandardFormat(BackedEnum::A, 'int4', $session),
            BackedEnum::class,
            'int4'
        );
        self::assertIncompatibleEnum(
            fn () => $converter->toPgStandardFormat(UnitEnum::Active, 'int4', $session),
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
}
