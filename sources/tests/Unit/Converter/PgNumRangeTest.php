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
use PHPUnit\Framework\Attributes\DataProvider;
use PommProject\Foundation\Converter\PgNumRange;
use PommProject\Foundation\Converter\Type\BaseRange;
use PommProject\Foundation\Converter\Type\NumRange;

#[CoversClass(PgNumRange::class)]
class PgNumRangeTest extends BaseConverterTestCase
{
    /**
     * @param int|float|string|null $expectedStartLimit
     * @param int|float|string|null $expectedEndLimit
     */
    #[DataProvider('fromPgDataProvider')]
    public function testExtraFromPg(
        string $textRange,
        mixed $expectedStartLimit,
        mixed $expectedEndLimit,
        ?bool $expectedStartIncl,
        ?bool $expectedEndIncl,
    ): void {
        $session = $this->buildSession();
        $instance = new PgNumRange()->fromPg($textRange, 'tstzrange', $session);

        self::assertInstanceOf(NumRange::class, $instance);
        self::assertSame($expectedStartLimit, $instance->startLimit);
        self::assertSame($expectedEndLimit, $instance->endLimit);
        self::assertSame($expectedStartIncl, $instance->startIncl);
        self::assertSame($expectedEndIncl, $instance->endIncl);
    }

    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgNumRange();

        self::assertInstanceOf(NumRange::class, $converter->fromPg('[1,3)', 'int4range', $session));
        self::assertNull($converter->fromPg(null, 'point', $session));

        $intRange = $converter->fromPg('[1,3)', 'int4range', $session);
        self::assertSame(1, $intRange->startLimit);
        self::assertSame(3, $intRange->endLimit);
        self::assertTrue($intRange->startIncl);
        self::assertFalse($intRange->endIncl);

        $floatRange = $converter->fromPg('(-3.1415, -1.6180]', 'numrange', $session);
        self::assertSame(-3.1415, $floatRange->startLimit);
        self::assertSame(-1.618, $floatRange->endLimit);
        self::assertFalse($floatRange->startIncl);
        self::assertTrue($floatRange->endIncl);
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgNumRange();

        self::assertSame(
            "myrange('[1,3)')",
            $converter->toPg(new NumRange('[1,3)'), 'myrange', $session)
        );
        self::assertSame('NULL::myrange', $converter->toPg(null, 'myrange', $session));
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgNumRange();

        self::assertSame(
            '[1,3)',
            $converter->toPgStandardFormat(new NumRange('[1,3)'), 'myrange', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'myrange', $session));
    }

    /**
     * @return iterable<string, array{
     *     textRange: string,
     *     expectedStartLimit: int|float|string|null,
     *     expectedEndLimit: int|float|string|null,
     *     expectedStartIncl: bool|null,
     *     expectedEndIncl: bool|null,
     * }>
     */
    public static function fromPgDataProvider(): iterable
    {
        yield 'Test with value and null' => [
            'textRange' => '(-10,)',
            'expectedStartLimit' => -10,
            'expectedEndLimit' => null,
            'expectedStartIncl' => false,
            'expectedEndIncl' => false,
        ];
        yield 'Test with null and null' => [
            'textRange' => '[,]',
            'expectedStartLimit' => null,
            'expectedEndLimit' => null,
            'expectedStartIncl' => true,
            'expectedEndIncl' => true,
        ];
        yield 'Test with null and value' => [
            'textRange' => '(, 99)',
            'expectedStartLimit' => null,
            'expectedEndLimit' => 99,
            'expectedStartIncl' => false,
            'expectedEndIncl' => false,
        ];
        yield 'Test with empty' => [
            'textRange' => 'empty',
            'expectedStartLimit' => BaseRange::EMPTY_RANGE,
            'expectedEndLimit' => BaseRange::EMPTY_RANGE,
            'expectedStartIncl' => null,
            'expectedEndIncl' => null,
        ];
    }
}
