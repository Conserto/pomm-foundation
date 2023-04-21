<?php
/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Converter;

use Atoum;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Test\Fixture\DumbConverter;

class ConverterHolder extends Atoum
{
    public function testRegisterConverter(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->array($converterHolder->registerConverter(
            'Dumb',
            new DumbConverter(),
            ['schema.type', 'public.dumb']
        )->getTypes())
            ->isIdenticalTo(['schema.type', 'public.dumb'])
            ->array($converterHolder->getConverterNames())
            ->isIdenticalTo(['Dumb'])
            ->object($converterHolder->getConverterForType('public.dumb'))
            ->isInstanceOf(DumbConverter::class);
    }

    public function testHasConverterName(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->boolean($converterHolder->hasConverterName('Dumb'))
            ->isFalse()
            ->boolean($converterHolder->registerConverter(
                'Dumb',
                new DumbConverter(),
                ['schema.type', 'public.dumb']
            )->hasConverterName('Dumb'))
            ->isTrue();
    }

    public function testGetConverter(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->variable($converterHolder->getConverter('Dumb'))
            ->isNull()
            ->object($converterHolder->registerConverter(
                'Dumb',
                new DumbConverter(),
                ['schema.type', 'public.dumb']
            )->getConverter('Dumb'))
            ->isInstanceOf(DumbConverter::class);
    }

    public function testGetConverterNames(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->array($converterHolder->getConverterNames())
            ->isEmpty()
            ->array($converterHolder->registerConverter(
                'Dumb',
                new DumbConverter(),
                ['public.dumb']
            )->getConverterNames())
            ->isIdenticalTo(['Dumb']);
    }

    public function testAddTypeToConverter(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->array($converterHolder->registerConverter(
            'Dumb',
            new DumbConverter(),
            ['public.dumb']
        )->addTypeToConverter('Dumb', 'schema.type')->getTypes())
            ->isIdenticalTo(['public.dumb', 'schema.type'])
            ->array($converterHolder->addTypeToConverter('Dumb', 'pika.chu')->getTypes())
            ->isIdenticalTo(['public.dumb', 'schema.type', 'pika.chu'])
            ->exception(
                function () use ($converterHolder) {
                    $converterHolder->addTypeToConverter('No', 'pika.chu');
                })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('No such converter');
    }

    public function testGetConverterForType(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->object($converterHolder->registerConverter(
            'Dumb',
            new DumbConverter(),
            ['schema.type', 'public.dumb']
        )->getConverterForType('schema.type'))
            ->isInstanceOf(DumbConverter::class)
            ->exception(function () use ($converterHolder) {
                $converterHolder->getConverterForType('no.type');
            })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('No converters associated with type');
    }

    public function testHasType(): void
    {
        $converterHolder = $this->newTestedInstance();
        $this->boolean($converterHolder->hasType('pika.chu'))
            ->isFalse()
            ->boolean($converterHolder->registerConverter(
                'Dumb',
                new DumbConverter(),
                ['pika.chu']
            )->hasType('pika.chu'))
            ->isTrue();
    }
}
