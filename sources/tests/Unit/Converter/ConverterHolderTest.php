<?php

/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Converter\ConverterHolder;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Fixture\DumbConverter;

#[CoversClass(ConverterHolder::class)]
class ConverterHolderTest extends TestCase
{
    /**
     * @throws FoundationException
     */
    public function testRegisterConverter(): void
    {
        $holder = new ConverterHolder();

        self::assertSame(
            ['schema.type', 'public.dumb'],
            $holder->registerConverter('Dumb', new DumbConverter(), ['schema.type', 'public.dumb'])->getTypes()
        );
        self::assertSame(['Dumb'], $holder->getConverterNames());
        self::assertInstanceOf(DumbConverter::class, $holder->getConverterForType('public.dumb'));
    }

    /**
     * @throws FoundationException
     */
    public function testHasConverterName(): void
    {
        $holder = new ConverterHolder();

        self::assertFalse($holder->hasConverterName('Dumb'));
        self::assertTrue(
            $holder->registerConverter('Dumb', new DumbConverter(), ['schema.type', 'public.dumb'])
                ->hasConverterName('Dumb')
        );
    }

    /**
     * @throws FoundationException
     */
    public function testGetConverter(): void
    {
        $holder = new ConverterHolder();

        self::assertNull($holder->getConverter('Dumb'));
        self::assertInstanceOf(
            DumbConverter::class,
            $holder->registerConverter('Dumb', new DumbConverter(), ['schema.type', 'public.dumb'])
                ->getConverter('Dumb')
        );
    }

    /**
     * @throws FoundationException
     */
    public function testGetConverterNames(): void
    {
        $holder = new ConverterHolder();

        self::assertEmpty($holder->getConverterNames());
        self::assertSame(
            ['Dumb'],
            $holder->registerConverter('Dumb', new DumbConverter(), ['public.dumb'])->getConverterNames()
        );
    }

    /**
     * @throws FoundationException
     */
    public function testAddTypeToConverter(): void
    {
        $holder = new ConverterHolder();
        $holder->registerConverter('Dumb', new DumbConverter(), ['public.dumb']);

        self::assertSame(
            ['public.dumb', 'schema.type'],
            $holder->addTypeToConverter('Dumb', 'schema.type')->getTypes()
        );
        self::assertSame(
            ['public.dumb', 'schema.type', 'pika.chu'],
            $holder->addTypeToConverter('Dumb', 'pika.chu')->getTypes()
        );

        try {
            $holder->addTypeToConverter('No', 'pika.chu');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No such converter', $e->getMessage());
        }
    }

    /**
     * @throws FoundationException
     */
    public function testGetConverterForType(): void
    {
        $holder = new ConverterHolder();
        $holder->registerConverter('Dumb', new DumbConverter(), ['schema.type', 'public.dumb']);

        self::assertInstanceOf(DumbConverter::class, $holder->getConverterForType('schema.type'));

        try {
            $holder->getConverterForType('no.type');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No converters associated with type', $e->getMessage());
        }
    }

    /**
     * @throws FoundationException
     */
    public function testHasType(): void
    {
        $holder = new ConverterHolder();

        self::assertFalse($holder->hasType('pika.chu'));
        self::assertTrue(
            $holder->registerConverter('Dumb', new DumbConverter(), ['pika.chu'])->hasType('pika.chu')
        );
    }
}
