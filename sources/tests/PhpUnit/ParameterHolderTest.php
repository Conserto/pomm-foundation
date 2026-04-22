<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\PhpUnit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\ParameterHolder;

#[CoversClass(ParameterHolder::class)]
class ParameterHolderTest extends TestCase
{
    public function testConstructorEmpty(): void
    {
        $parameterHolder = new ParameterHolder();
        self::assertEmpty($parameterHolder->getIterator()->getArrayCopy());
    }

    public function testConstructorWithParameter(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);
        self::assertSame(['pika' => 'one'], $parameterHolder->getIterator()->getArrayCopy());
    }

    public function testSetParameter(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertSame(
            ['pika' => 'one', 'chu' => 'two'],
            $parameterHolder->setParameter('chu', 'two')->getIterator()->getArrayCopy()
        );
        self::assertSame(
            ['pika' => 'three', 'chu' => 'two'],
            $parameterHolder->setParameter('pika', 'three')->getIterator()->getArrayCopy()
        );
        self::assertSame(
            ['pika' => 'three', 'chu' => 'two', 'plop' => null],
            $parameterHolder->setParameter('plop', null)->getIterator()->getArrayCopy()
        );
        self::assertSame(
            ['pika' => null, 'chu' => 'two', 'plop' => null],
            $parameterHolder->setParameter('pika', null)->getIterator()->getArrayCopy()
        );
    }

    public function testHasParameter(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertTrue($parameterHolder->hasParameter('pika'));
        self::assertFalse($parameterHolder->hasParameter('chu'));
        self::assertTrue($parameterHolder->setParameter('chu', 'whatever')->hasParameter('chu'));
        self::assertTrue($parameterHolder->setParameter('chu', null)->hasParameter('chu'));
    }

    public function testGetParameter(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertSame('one', $parameterHolder->getParameter('pika'));
        self::assertSame('one', $parameterHolder->getParameter('pika', 'two'));
        self::assertSame('two', $parameterHolder->getParameter('chu', 'two'));
        self::assertNull($parameterHolder->getParameter('chu'));
    }

    public function testMustHave(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertInstanceOf(
            ParameterHolder::class,
            $parameterHolder->mustHave('pika')
        );

        try {
            $parameterHolder->mustHave('chu');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('mandatory', $e->getMessage());
        }

        self::assertInstanceOf(
            ParameterHolder::class,
            $parameterHolder->setParameter('chu', 'whatever')->mustHave('chu')
        );
    }

    public function testSetDefaultValue(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertSame(
            'one',
            $parameterHolder->setDefaultValue('pika', 'two')->getParameter('pika')
        );
        self::assertSame(
            'two',
            $parameterHolder->setDefaultValue('chu', 'two')->getParameter('chu')
        );
    }

    public function testMustBeOneOf(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one', 'chu' => 'two']);

        self::assertInstanceOf(
            ParameterHolder::class,
            $parameterHolder->mustBeOneOf('pika', ['one', 'two', 'tree'])
        );

        try {
            $parameterHolder->mustBeOneOf('pika', ['four', 'five']);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('must be one of', $e->getMessage());
        }

        try {
            $parameterHolder->mustBeOneOf('chu', ['four', 'five']);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('must be one of', $e->getMessage());
        }
    }

    public function testUnsetParameter(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one', 'chu' => 'two']);

        self::assertSame(
            ['chu' => 'two'],
            $parameterHolder->unsetParameter('pika')->getIterator()->getArrayCopy()
        );
        self::assertEmpty($parameterHolder->unsetParameter('chu')->getIterator()->getArrayCopy());
        self::assertEmpty($parameterHolder->unsetParameter('chu')->getIterator()->getArrayCopy());
    }

    public function testArrayAccess(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertInstanceOf(\ArrayAccess::class, $parameterHolder);
        self::assertTrue(isset($parameterHolder['pika']));
        self::assertSame('one', $parameterHolder['pika']);
        self::assertFalse(isset($parameterHolder['chu']));

        $parameterHolder['chu'] = 'two';
        self::assertTrue(isset($parameterHolder['chu']));
        self::assertSame('two', $parameterHolder['chu']);

        unset($parameterHolder['chu']);
        self::assertFalse(isset($parameterHolder['chu']));
    }

    public function testCount(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertInstanceOf(\Countable::class, $parameterHolder);
        self::assertSame(1, $parameterHolder->count());
        self::assertSame(2, $parameterHolder->setParameter('chu', 'two')->count());
    }

    public function testGetIterator(): void
    {
        $parameterHolder = new ParameterHolder(['pika' => 'one']);

        self::assertInstanceOf(\IteratorAggregate::class, $parameterHolder);
        self::assertSame(
            ['pika' => 'one'],
            $parameterHolder->getIterator()->getArrayCopy()
        );
        self::assertSame(
            ['pika' => 'one', 'chu' => 'two'],
            $parameterHolder->setParameter('chu', 'two')->getIterator()->getArrayCopy()
        );
    }
}
