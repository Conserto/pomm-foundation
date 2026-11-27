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
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Converter\Geometry\PgCircle;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Converter\Type\Point;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tests\Unit\Converter\BaseConverterTestCase;

#[CoversClass(PgCircle::class)]
final class PgCircleTest extends BaseConverterTestCase
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
            "circle('<(1.2345,-9.87654),3.141596>')",
            $converter->toPg($circle, 'circle', $session)
        );
        self::assertSame(
            "circle('<(1.2345,-9.87654),3.141596>')",
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

    /**
     * The center and the radius keep all their digits and the special values are written and read back.
     *
     * @throws FoundationException|SqlException
     */
    public function testKeepsEveryDigitAndSpecialValues(): void
    {
        $session = $this->buildSession();
        $circle = new Circle('<(0.3333333333333333,-Infinity),Infinity>');

        self::assertSame([0.3333333333333333, -\INF, \INF], [$circle->center->x, $circle->center->y, $circle->radius]);
        self::assertSame('<(0.3333333333333333,-Infinity),Infinity>', (string) $circle);

        $read = $this->sendToPostgres($circle, 'circle', $session);
        self::assertInstanceOf(Circle::class, $read);
        self::assertSame([0.3333333333333333, -\INF, \INF], [$read->center->x, $read->center->y, $read->radius]);
    }

    /**
     * Every form PostgreSQL reads is read.
     */
    #[TestWith(['<(1,2),3>'])]
    #[TestWith(['((1,2),3)'])]
    #[TestWith(['(1,2),3'])]
    #[TestWith(['1,2,3'])]
    #[TestWith(['<1,2,3>'])]
    #[TestWith([' < ( 1 , 2 ) , 3 > '])]
    #[TestWith(["<(1,2)\t,3>"])]
    #[TestWith(['( (1,2), 3 )'])]
    public function testReadsEveryForm(string $description): void
    {
        $circle = new Circle($description);

        self::assertSame([1.0, 2.0, 3.0], [$circle->center->x, $circle->center->y, $circle->radius]);
    }

    /**
     * A radius or a center that is not a number is refused: it was read 0.
     */
    #[TestWith(['<(1,2),abc>'])]
    #[TestWith(['<(1,2),e>'])]
    #[TestWith(['<(1,2),3) junk'])]
    #[TestWith(['<(1,2,3>'])]
    #[TestWith(['<(1,x),3>'])]
    #[TestWith(['<(1,2),3'])]
    #[TestWith(['>1,2,3<'])]
    #[TestWith(['(1,2,3)'])]
    #[TestWith(['<(1,2),-3>'])]
    #[TestWith(['<(1,2),1e400>'])]
    public function testRefusesMalformedDescription(string $description): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Circle($description);
    }

    /**
     * A subclass overriding getPointConverter() is warned that it is no longer called.
     */
    #[IgnoreDeprecations]
    public function testWarnsSubclassOverridingPointConverter(): void
    {
        $notices = [];
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            new PgCircle();
            new class () extends PgCircle {
                #[\Override]
                protected function getPointConverter(Session $session): ConverterClient
                {
                    return parent::getPointConverter($session);
                }
            };
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $notices);
        self::assertStringStartsWith(
            'Since 5.0, "PommProject\\Foundation\\Converter\\Geometry\\PgCircle::getPointConverter()" is deprecated',
            $notices[0]
        );
    }

    /**
     * The given type is written, so a domain checks its constraints and mixes with NULL in an array. circle(point(...),
     * radius) was written: it failed on a domain, which has no function of that name.
     *
     * @throws FoundationException|SqlException
     */
    public function testDomain(): void
    {
        $session = $this->buildSession();
        $connection = $session->getConnection();
        $connection->executeAnonymousQuery('CREATE DOMAIN pg_temp."BigCircle" AS circle CHECK (radius(VALUE) > 1)');
        $converter = new PgCircle();

        $sql = $converter->toPg(new Circle('<(1,2),3>'), 'pg_temp.BigCircle', $session);
        self::assertSame("pg_temp.\"BigCircle\"('<(1,2),3>')", $sql);
        self::assertSame(
            ['v' => '{"<(1,2),3>",NULL}'],
            $session->getQueryManager()
                ->query(sprintf('select ARRAY[%s, %s]::text as v', $sql, $converter->toPg(null, 'pg_temp.BigCircle', $session)))
                ->current()
        );

        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('violates check constraint');
        $session->getQueryManager()->query(
            sprintf('select %s', $converter->toPg(new Circle('<(1,2),0.5>'), 'pg_temp.BigCircle', $session))
        );
    }
}
