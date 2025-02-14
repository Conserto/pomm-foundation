<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionAtoum;

class ConvertedResultIterator extends FoundationSessionAtoum
{
    /** @throws FoundationException */
    public function testConstructor(): void
    {
        $iterator = $this->newTestedInstance(
            $this->getResultResource("select true::boolean"),
            $this->buildSession()
        );

        $this->object($iterator)
            ->isInstanceOf(\PommProject\Foundation\ConvertedResultIterator::class)
            ->isInstanceOf(\Countable::class)
            ->isInstanceOf(\Iterator::class);
    }

    /** @throws FoundationException */
    protected function getResultResource($sql, array $params = []): ResultHandler
    {
        return $this->buildSession()->getConnection()->sendQueryWithParameters($sql, $params);
    }

    /** @throws FoundationException */
    public function testGet(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        $this->array($iterator->get(0))
            ->isIdenticalTo(['id' => 1, 'pika' => 'a', 'chu' => null])
            ->array($iterator->get(2))
            ->isIdenticalTo(['id' => 3, 'pika' => 'c', 'chu' => [1]]);
    }

    protected function getPikaSql(): string
    {
        return <<<SQL
select
    p.id,
    p.pika,
    p.chu
from
  (values
    (1::int4, 'a'::text, null::int4[]),
    (null, 'b', array[null]::int4[]),
    (3, 'c', array[1]),
    (4, 'd', array[2, 2])
  ) p (id, pika, chu)
SQL;
    }

    /** @throws FoundationException */
    public function testGetWithArray(): void
    {
        $sql = "select array[1, 2, 3, null]::int4[] as array_one, array[null, null]::int4[] as array_two";

        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        $this->integer($iterator->count())
            ->isEqualTo(1)
            ->array($iterator->current()['array_one'])
            ->isIdenticalTo([1, 2, 3, null])
            ->array($iterator->current()['array_two'])
            ->isIdenticalTo([null, null]);
    }

    /** @throws FoundationException */
    public function testGetWithNoType(): void
    {
        $sql = 'select null as one, array[null, null] as two';

        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        $this->integer($iterator->count())
            ->isEqualTo(1)
            ->variable($iterator->current()['one'])
            ->isNull()
            ->array($iterator->current()['two'])
            ->isIdenticalTo([null, null]);
    }

    /** @throws FoundationException */
    public function testSlice(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        $this->array($iterator->slice('pika'))
            ->isIdenticalTo(['a', 'b', 'c', 'd'])
            ->array($iterator->slice('id'))
            ->isIdenticalTo([1, null, 3, 4]);
    }

    /** @throws FoundationException */
    public function testExtract(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        $this->array($iterator->extract())
            ->isIdenticalTo(
                [
                    ['id' => 1, 'pika' => 'a', 'chu' => null],
                    ['id' => null, 'pika' => 'b', 'chu' => [null]],
                    ['id' => 3, 'pika' => 'c', 'chu' => [1]],
                    ['id' => 4, 'pika' => 'd', 'chu' => [2, 2]],
                ]
            );
    }

    protected function initializeSession(Session $session): void
    {
    }
}
