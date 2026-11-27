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

namespace PommProject\Foundation\Tests\Unit\Converter\Geometry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\Geometry\PgPoint;
use PommProject\Foundation\Converter\Type\Point;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tests\Unit\Converter\BaseConverterTestCase;

#[CoversClass(PgPoint::class)]
final class PgPointTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
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

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgPoint();
        $point = new Point('(1.2345, -9.87654)');

        self::assertSame("point('(1.2345,-9.87654)')", $converter->toPg($point, 'point', $session));
        self::assertSame(
            "point('(1.2345,-9.87654)')",
            $converter->toPg('(1.2345,-9.87654)', 'point', $session)
        );
        self::assertSame('NULL::subpoint', $converter->toPg(null, 'subpoint', $session));

        $this->expectException(ConverterException::class);
        $converter->toPg('azsdf', 'point', $session);
    }

    /**
     * @throws FoundationException
     */
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

    /**
     * Coordinates keep all their digits (14 were kept: 1/3 was stored 0.33333333333333), and the special values are
     * written and read back (INF wrote invalid SQL, and Infinity could not be read).
     *
     * @throws FoundationException|SqlException
     */
    public function testKeepsEveryDigitAndSpecialValues(): void
    {
        $session = $this->buildSession();
        $converter = new PgPoint();
        $point = new Point('(1,1)');
        $point->x = 1 / 3;
        $point->y = 1e-300;

        self::assertSame('(0.3333333333333333,1.0e-300)', (string) $point);
        self::assertSame("point('(0.3333333333333333,1.0e-300)')", $converter->toPg($point, 'point', $session));

        $special = new Point('(Infinity,-Infinity)');
        self::assertSame(\INF, $special->x);
        self::assertSame(-\INF, $special->y);
        self::assertNan(new Point('(NaN,1)')->x);

        foreach ([$point, $special] as $value) {
            foreach ([$this->select($converter->toPg($value, 'point', $session), $session), $this->sendToPostgres($value, 'point', $session)] as $read) {
                self::assertInstanceOf(Point::class, $read);
                self::assertSame([$value->x, $value->y], [$read->x, $read->y]);
            }
        }
    }

    /**
     * A description with anything else than two numbers is refused: 'xInfinity,1' was read (Infinity,1).
     */
    #[TestWith(['xInfinity,1'])]
    #[TestWith(['(1,2) junk'])]
    #[TestWith(['1,2,3'])]
    #[TestWith(['(1,2'])]
    #[TestWith(['((1,2))'])]
    #[TestWith(['1e400,1'])]
    #[TestWith(['1,-1e-400'])]
    #[TestWith(['1,0.1e-330'])]
    #[TestWith(['e,-'])]
    #[TestWith(['1-2,3e'])]
    #[TestWith(['1..2,3'])]
    public function testRefusesMalformedDescription(string $description): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Point($description);
    }

    /**
     * Numbers as PostgreSQL reads them are read.
     */
    public function testReadsNumberForms(): void
    {
        $point = new Point('(1e3, .5)');
        self::assertSame([1000.0, 0.5], [$point->x, $point->y]);
        $point = new Point('+1,-2.');
        self::assertSame([1.0, -2.0], [$point->x, $point->y]);
        $point = new Point('1.5E-3,-1e+2');
        self::assertSame([0.0015, -100.0], [$point->x, $point->y]);
        $point = new Point('0e400,0.0e-400');
        self::assertSame([0.0, 0.0], [$point->x, $point->y]);
        $point = new Point(str_repeat('0', 400) . '1,2');
        self::assertSame([1.0, 2.0], [$point->x, $point->y]);
        $point = new Point("\t( 1 ,\n2 )\n");
        self::assertSame([1.0, 2.0], [$point->x, $point->y]);
        // The special values in any case and with a sign, as PostgreSQL reads them.
        $point = new Point('(+Infinity,-inf)');
        self::assertSame([\INF, -\INF], [$point->x, $point->y]);
        self::assertNan(new Point('(nan,1)')->x);
        self::assertNan(new Point('(-NaN,1)')->x);
    }

    /**
     * @throws FoundationException|SqlException
     */
    private function select(string $sql, Session $session): mixed
    {
        $result = $session->getQueryManager()->query(sprintf('select %s as my_test', $sql))->current();

        return $result['my_test'] ?? null;
    }
}
