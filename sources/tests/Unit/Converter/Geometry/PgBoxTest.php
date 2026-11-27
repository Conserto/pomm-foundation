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
use PommProject\Foundation\Converter\Geometry\PgBox;
use PommProject\Foundation\Converter\Type\Box;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Unit\Converter\BaseConverterTestCase;

#[CoversClass(PgBox::class)]
final class PgBoxTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgBox();

        self::assertInstanceOf(
            Box::class,
            $converter->fromPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session)
        );
        self::assertNull($converter->fromPg(null, 'box', $session));

        $box = $converter->fromPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session);
        self::assertSame(1.2345, $box->topX);
        self::assertSame(-9.87654, $box->topY);
        self::assertSame(0.1234, $box->bottomX);
        self::assertSame(-10.98765, $box->bottomY);
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgBox();
        $box = new Box('( (1.2345, -9.87654 ), (0.1234, -10.98765 ) )');

        self::assertSame(
            'box((1.2345,-9.87654),(0.1234,-10.98765))',
            $converter->toPg($box, 'box', $session)
        );
        self::assertSame(
            'box((1.2345,-9.87654),(0.1234,-10.98765))',
            $converter->toPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session)
        );
        self::assertSame('NULL::subbox', $converter->toPg(null, 'subbox', $session));

        $this->expectException(ConverterException::class);
        $converter->toPg('azsdf', 'box', $session);
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgBox();
        $box = new Box('((1.2345, -9.87654), (0.1234, -10.98765))');

        self::assertSame(
            '((1.2345,-9.87654),(0.1234,-10.98765))',
            $converter->toPgStandardFormat($box, 'box', $session)
        );
        self::assertSame(
            '((1.2345,-9.87654),(0.1234,-10.98765))',
            $converter->toPgStandardFormat('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'subbox', $session));

        $this->expectException(ConverterException::class);
        $converter->toPgStandardFormat('azsdf', 'box', $session);
    }

    /**
     * A subclass mapping to a non Stringable type gets a ConverterException, not a PHP Error.
     *
     * @throws FoundationException
     */
    public function testNonStringableTypeRaisesConverterException(): void
    {
        $session = $this->buildSession();
        $converter = new class () extends PgBox {
            public function getTypeClassName(): string
            {
                return \stdClass::class;
            }
        };

        try {
            $converter->toPgStandardFormat(new \stdClass(), 'box', $session);
            self::fail('A ConverterException was expected.');
        } catch (ConverterException $e) {
            self::assertSame("Unable to transform a 'stdClass' instance to string.", $e->getMessage());
        }

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage("Unable to transform a 'stdClass' instance to string.");
        $converter->toPg(new \stdClass(), 'box', $session);
    }
}
