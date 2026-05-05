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
use PommProject\Foundation\Converter\PgString;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgString::class)]
class PgStringTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgString();
        $string = <<<_
            \\" ''\r
            _;

        self::assertSame("\\\" ''\r", $converter->fromPg($string, 'text', $session));
        self::assertNull($converter->fromPg(null, 'text', $session));
        self::assertSame(' ', $converter->fromPg(' ', 'text', $session));
        self::assertSame('', $converter->fromPg('', 'text', $session));
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgString();
        $string = <<<_
            \\"	!'
            _;

        self::assertSame("varchar  E'\\\\\"\t!'''", $converter->toPg($string, 'varchar', $session));
        self::assertSame('NULL::varchar', $converter->toPg(null, 'varchar', $session));
        self::assertSame("bpchar ''", $converter->toPg('', 'bpchar', $session));
        self::assertSame("inet '10.2.3.4'", $converter->toPg('10.2.3.4', 'inet', $session));
        self::assertSame("varchar 'a'", $converter->toPg(BackedEnum::A, 'varchar', $session));
        self::assertSame("varchar 'Active'", $converter->toPg(UnitEnum::Active, 'varchar', $session));

        try {
            $converter->toPg(IntBackedEnum::TWO, 'varchar', $session);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertSame(
                sprintf("Enum '%s' is not compatible with Pg type 'varchar'.", IntBackedEnum::class),
                $e->getMessage()
            );
        }
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgString();
        $string = <<<_
            \\"\t!'\n
            _;

        self::assertSame($string, $converter->toPgStandardFormat($string, 'varchar', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'varchar', $session));
        self::assertSame('', $converter->toPgStandardFormat('', 'bpchar', $session));
        self::assertSame('10.2.3.4', $converter->toPgStandardFormat('10.2.3.4', 'inet', $session));
        self::assertSame('a', $converter->toPgStandardFormat(BackedEnum::A, 'varchar', $session));
        self::assertSame('Active', $converter->toPgStandardFormat(UnitEnum::Active, 'varchar', $session));

        try {
            $converter->toPgStandardFormat(IntBackedEnum::TWO, 'varchar', $session);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertSame(
                sprintf("Enum '%s' is not compatible with Pg type 'varchar'.", IntBackedEnum::class),
                $e->getMessage()
            );
        }
    }
}
