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
use PommProject\Foundation\Converter\Geometry\PgCircle;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Converter\Type\Point;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Unit\Converter\BaseConverterTestCase;

#[CoversClass(PgCircle::class)]
class PgCircleTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgCircle();

        self::assertInstanceOf(
            Circle::class,
            $converter->fromPg('<(1.2345,-9.87654),3.141596>', 'circle', $session)
        );
        self::assertNull($converter->fromPg(null, 'circle', $session));

        $circle = $converter->fromPg('<(1.2345,-9.87654),3.141596>', 'circle', $session);
        self::assertInstanceOf(Point::class, $circle->center);
        self::assertSame(1.2345, $circle->center->x);
        self::assertSame(3.141596, $circle->radius);
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgCircle();
        $circle = new Circle('<(1.2345,-9.87654),3.141596>');

        self::assertSame(
            'circle(point(1.2345,-9.87654),3.141596)',
            $converter->toPg($circle, 'circle', $session)
        );
        self::assertSame(
            'circle(point(1.2345,-9.87654),3.141596)',
            $converter->toPg('<(1.2345,-9.87654),3.141596>', 'circle', $session)
        );
        self::assertSame('NULL::mycircle', $converter->toPg(null, 'mycircle', $session));

        $this->expectException(ConverterException::class);
        $converter->toPg('azsdf', 'circle', $session);
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgCircle();
        $circle = new Circle('<(1.2345,-9.87654),3.141596>');

        self::assertSame(
            '<(1.2345,-9.87654),3.141596>',
            $converter->toPgStandardFormat($circle, 'circle', $session)
        );
        self::assertSame(
            '<(1.2345,-9.87654),3.141596>',
            $converter->toPgStandardFormat('<(1.2345,-9.87654),3.141596>', 'circle', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'mycircle', $session));

        $this->expectException(ConverterException::class);
        $converter->toPgStandardFormat('azsdf', 'circle', $session);
    }
}
