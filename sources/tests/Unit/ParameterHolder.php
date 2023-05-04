<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Test\Unit\Foundation;

use Atoum;
use PommProject\Foundation\Exception\FoundationException;

class ParameterHolder extends Atoum
{
    public function testConstructorEmpty(): void
    {
        $parameterHolder = $this->newTestedInstance();
        $this->array($parameterHolder->getIterator()->getArrayCopy())
            ->isEmpty();
    }

    public function testConstructorWithParameter(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->array($parameterHolder->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'one']);
    }

    public function testSetParameter(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->array($parameterHolder->setParameter('chu', 'two')->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'one', 'chu' => 'two'])
            ->array($parameterHolder->setParameter('pika', 'three')->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'three', 'chu' => 'two'])
            ->array($parameterHolder->setParameter('plop', null)->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'three', 'chu' => 'two', 'plop' => null])
            ->array($parameterHolder->setParameter('pika', null)->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => null, 'chu' => 'two', 'plop' => null]);
    }

    public function testHasParameter(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->boolean($parameterHolder->hasParameter('pika'))
            ->isTrue()
            ->boolean($parameterHolder->hasParameter('chu'))
            ->isFalse()
            ->boolean($parameterHolder->setParameter('chu', 'whatever')->hasParameter('chu'))
            ->isTrue()
            ->boolean($parameterHolder->setParameter('chu', null)->hasParameter('chu'))
            ->isTrue();
    }

    public function testGetParameter(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->string($parameterHolder->getParameter('pika'))
            ->isEqualTo('one')
            ->string($parameterHolder->getParameter('pika', 'two'))
            ->isEqualTo('one')
            ->string($parameterHolder->getParameter('chu', 'two'))
            ->isEqualTo('two')
            ->variable($parameterHolder->getParameter('chu'))
            ->isNull();
    }

    public function testMustHave(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->object($parameterHolder->mustHave('pika'))
            ->isInstanceOf(\PommProject\Foundation\ParameterHolder::class)
            ->exception(function () use ($parameterHolder) { $parameterHolder->mustHave('chu'); })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('mandatory')
            ->object($parameterHolder->setParameter('chu', 'whatever')->mustHave('chu'))
            ->isInstanceOf(\PommProject\Foundation\ParameterHolder::class);
    }

    public function testSetDefaultValue(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->string($parameterHolder->setDefaultValue('pika', 'two')->getParameter('pika'))
            ->isEqualTo('one')
            ->string($parameterHolder->setDefaultValue('chu', 'two')->getParameter('chu'))
            ->isEqualTo('two');
    }

    public function testMustBeOneOf(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one', 'chu' => 'two']);
        $this->object($parameterHolder->mustBeOneOf('pika', [ 'one', 'two', 'tree']))
            ->isInstanceOf(\PommProject\Foundation\ParameterHolder::class)
            ->exception(
                function () use ($parameterHolder) { $parameterHolder->mustBeOneOf('pika', ['four', 'five']); }
            )
            ->isInstanceOf(FoundationException::class)
            ->message->contains('must be one of')
            ->exception(
                function () use ($parameterHolder) { $parameterHolder->mustBeOneOf('chu', ['four', 'five']); }
            )
            ->isInstanceOf(FoundationException::class)
            ->message->contains('must be one of');
    }

    public function testUnsetParameter(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one', 'chu' => 'two']);
        $this->array($parameterHolder->unsetParameter('pika')->getIterator()->getArrayCopy())
            ->isIdenticalTo(['chu' => 'two'])
            ->array($parameterHolder->unsetParameter('chu')->getIterator()->getArrayCopy())
            ->isEmpty()
            ->array($parameterHolder->unsetParameter('chu')->getIterator()->getArrayCopy())
            ->isEmpty();
    }

    public function testArrayAccess(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->object($parameterHolder)
            ->isInstanceOf(\ArrayAccess::class)
            ->boolean(isset($parameterHolder['pika']))
            ->isTrue()
            ->string($parameterHolder['pika'])
            ->isEqualTo('one')
            ->boolean(isset($parameterHolder['chu']))
            ->isFalse();

        $parameterHolder['chu'] = 'two';
        $this->boolean(isset($parameterHolder['chu']))
            ->isTrue()
            ->string($parameterHolder['chu'])
            ->isEqualTo('two');

        unset($parameterHolder['chu']);
        $this->boolean(isset($parameterHolder['chu']))
            ->isFalse();
    }

    public function testCount(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->object($parameterHolder)
            ->isInstanceOf(\Countable::class)
            ->integer($parameterHolder->count())
            ->isEqualTo(1)
            ->integer($parameterHolder->setParameter('chu', 'two')->count())
            ->isEqualTo(2);
    }

    public function testGetIterator(): void
    {
        $parameterHolder = $this->newTestedInstance(['pika' => 'one']);
        $this->object($parameterHolder)
            ->isInstanceOf(\IteratorAggregate::class)
            ->array($parameterHolder->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'one'])
            ->array($parameterHolder->setParameter('chu', 'two')->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'one', 'chu' => 'two']);
    }
}
