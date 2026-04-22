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

namespace PommProject\Foundation\Tests\Unit\Converter\Geometry;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\Geometry\PgPoint;
use PommProject\Foundation\Converter\Type\Point;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Tests\Unit\Converter\BaseConverterTestCase;

#[CoversClass(PgPoint::class)]
class PgPointTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgPoint();

        self::assertInstanceOf(Point::class, $converter->fromPg('(1.2345,-9.87654)', 'point', $session));
        self::assertNull($converter->fromPg(null, 'point', $session));

        $point = $converter->fromPg('(1.2345,-9.87654)', 'point', $session);
        self::assertSame(1.2345, $point->x);
        self::assertSame(-9.87654, $point->y);
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgPoint();
        $point = new Point('(1.2345, -9.87654)');

        self::assertSame('point(1.2345,-9.87654)', $converter->toPg($point, 'point', $session));
        self::assertSame(
            'point(1.2345,-9.87654)',
            $converter->toPg('(1.2345,-9.87654)', 'point', $session)
        );
        self::assertSame('NULL::subpoint', $converter->toPg(null, 'subpoint', $session));

        $this->expectException(ConverterException::class);
        $converter->toPg('azsdf', 'point', $session);
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgPoint();
        $point = new Point('(1.2345, -9.87654)');

        self::assertSame(
            '(1.2345,-9.87654)',
            $converter->toPgStandardFormat($point, 'point', $session)
        );
        self::assertSame(
            '(1.2345,-9.87654)',
            $converter->toPgStandardFormat('(1.2345,-9.87654)', 'point', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'subpoint', $session));

        $this->expectException(ConverterException::class);
        $converter->toPgStandardFormat('azsdf', 'point', $session);
    }
}
