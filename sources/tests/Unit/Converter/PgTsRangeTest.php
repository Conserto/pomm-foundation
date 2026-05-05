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
use PommProject\Foundation\Converter\PgTsRange;
use PommProject\Foundation\Converter\Type\BaseRange;
use PommProject\Foundation\Converter\Type\TsRange;

#[CoversClass(PgTsRange::class)]
class PgTsRangeTest extends BaseConverterTestCase
{
    #[DataProvider('fromPgDataProvider')]
    public function testExtraFromPg(
        string $textRange,
        mixed $expectedStartLimit,
        mixed $expectedEndLimit,
        ?bool $expectedStartIncl,
        ?bool $expectedEndIncl,
    ): void {
        $session = $this->buildSession();
        $instance = new PgTsRange()->fromPg($textRange, 'tstzrange', $session);

        self::assertInstanceOf(TsRange::class, $instance);
        self::assertSame($expectedStartLimit, $instance->startLimit);
        self::assertSame($expectedEndLimit, $instance->endLimit);
        self::assertSame($expectedStartIncl, $instance->startIncl);
        self::assertSame($expectedEndIncl, $instance->endIncl);
    }

    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgTsRange();
        $textRange = '["2014-08-15 15:29:24.395639+00","2014-10-15 15:29:24.395639+00")';
        $textRangeWithoutQuotes = '[2014-08-15 15:29:24.395639+00,2014-10-15 15:29:24.395639+00)';

        self::assertInstanceOf(TsRange::class, $converter->fromPg($textRange, 'tstzrange', $session));
        self::assertNull($converter->fromPg(null, 'whatever', $session));
        self::assertNull($converter->fromPg('', 'whatever', $session));

        $range = $converter->fromPg($textRange, 'tstzrange', $session);
        self::assertInstanceOf(\DateTime::class, $range->startLimit);

        $rangeWithoutQuotes = $converter->fromPg($textRangeWithoutQuotes, 'tstzrange', $session);
        self::assertInstanceOf(\DateTime::class, $rangeWithoutQuotes->startLimit);
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgTsRange();
        $textRange = '["2014-08-15 15:29:24.395639+00","2014-10-15 15:29:24.395639+00")';
        $range = $converter->fromPg($textRange, 'tstzrange', $session);

        self::assertSame(
            sprintf("tstzrange('%s')", $textRange),
            $converter->toPg($range, 'tstzrange', $session)
        );
        self::assertSame('NULL::mytsrange', $converter->toPg(null, 'mytsrange', $session));
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgTsRange();
        $textRange = '["2014-08-15 15:29:24.395639+00","2014-10-15 15:29:24.395639+00")';
        $range = $converter->fromPg($textRange, 'tstzrange', $session);

        self::assertSame(
            '[""2014-08-15 15:29:24.395639+00"",""2014-10-15 15:29:24.395639+00"")',
            $converter->toPgStandardFormat($range, 'tstzrange', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'mytsrange', $session));

        if (!$this->isPgVersionAtLeast('9.2', $session)) {
            self::markTestSkipped('Skipping some PgTsRange tests because Pg version < 9.2.');
        }

        self::assertInstanceOf(
            TsRange::class,
            $this->sendToPostgres($range, 'tsrange', $session)
        );
    }

    /**
     * @return iterable<string, array{
     *     textRange: string,
     *     expectedStartLimit: string|null,
     *     expectedEndLimit: string|null,
     *     expectedStartIncl: bool|null,
     *     expectedEndIncl: bool|null,
     * }>
     */
    public static function fromPgDataProvider(): iterable
    {
        yield 'Test with infinity and null' => [
            'textRange' => '(infinity,)',
            'expectedStartLimit' => BaseRange::INFINITY_MAX,
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
        yield 'Test with null and minus infinity' => [
            'textRange' => '(, -infinity)',
            'expectedStartLimit' => null,
            'expectedEndLimit' => BaseRange::INFINITY_MIN,
            'expectedStartIncl' => false,
            'expectedEndIncl' => false,
        ];
        yield 'Test with infinities' => [
            'textRange' => '(-infinity, infinity)',
            'expectedStartLimit' => BaseRange::INFINITY_MIN,
            'expectedEndLimit' => BaseRange::INFINITY_MAX,
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
