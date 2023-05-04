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

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionAtoum;

class ResultIterator extends VanillaSessionAtoum
{
    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function testConstructor(): void
    {
        $iterator = $this->newTestedInstance(
            $this->getResultResource("select true::boolean")
        );

        $this->object($iterator)
            ->isInstanceOf(\PommProject\Foundation\ResultIterator::class)
            ->isInstanceOf(\Countable::class)
            ->isInstanceOf(\Iterator::class);
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    protected function getResultResource($sql, array $params = []): ResultHandler
    {
        return $this->buildSession()->getConnection()->sendQueryWithParameters($sql, $params);
    }

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function testGet(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql)
        );

        $this->array($iterator->get(0))
            ->isIdenticalTo(['id' => '1', 'pika' => 'a'])
            ->array($iterator->get(2))
            ->isIdenticalTo(['id' => '3', 'pika' => 'c'])
            ->array($iterator->get(1))
            ->isIdenticalTo(['id' => '2', 'pika' => 'b'])
            ->exception(fn() => $iterator->get(5))
            ->isInstanceOf(\OutOfBoundsException::class)
            ->message->contains('Cannot jump to non existing row');
    }

    protected function getPikaSql(): string
    {
        return <<<SQL
            select
                p.id,
                p.pika
            from
              (values
                (1::int4, 'a'::text),
                (2, 'b'),
                (3, 'c'),
                (4, 'd')
              ) p (id, pika)
        SQL;
    }

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function testGetOnEmptyResult(): void
    {
        $iterator = $this->newTestedInstance(
            $this->getResultResource('select true where false')
        );
        $this->integer($iterator->count())
            ->isEqualTo(0)
            ->boolean($iterator->isEmpty())
            ->isTrue()
            ->variable($iterator->current())
            ->isNull()
            ->variable($iterator->isLast())
            ->isNull()
            ->variable($iterator->isFirst())
            ->isNull();
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    public function testHasAndCount(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql)
        );

        $this->integer($iterator->count())
            ->isEqualTo(4)
            ->boolean($iterator->has(1))
            ->isTrue()
            ->boolean($iterator->has(4))
            ->isFalse();
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    public function testIterator(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql)
        );

        foreach ($iterator as $index => $element) {
            if ($index === 0) {
                $this->boolean($iterator->isFirst())
                    ->isTrue();
            } elseif ($index === $iterator->count() - 1) {
                $this->boolean($iterator->isLast())
                    ->isTrue();
            } else {
                $this->boolean($iterator->isFirst())
                    ->isFalse()
                    ->boolean($iterator->isLast())
                    ->isFalse();
            }
            $this->string($element['id'])
                ->isEqualTo($index + 1);
        }
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    public function testSlice(): void
    {
        $sql = $this->getPikaSql();
        $iterator = $this->newTestedInstance(
            $this->getResultResource($sql)
        );

        $this->array($iterator->slice('pika'))
            ->isIdenticalTo(['a', 'b', 'c', 'd'])
            ->array($iterator->slice('id'))
            ->isIdenticalTo(['1', '2', '3', '4'])
            ->exception(fn() => $iterator->slice('no_such_key'))
            ->isInstanceOf(\InvalidArgumentException::class)
            ->message->contains('Could not find field');
    }

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function testExtract(): void
    {
        $iterator = $this->newTestedInstance(
            $this->getResultResource($this->getPikaSql())
        );

        $this->array($iterator->extract())
            ->isIdenticalTo(
                [
                    ['id' => '1', 'pika' => 'a'],
                    ['id' => '2', 'pika' => 'b'],
                    ['id' => '3', 'pika' => 'c'],
                    ['id' => '4', 'pika' => 'd'],
                ]
            );

        $iterator = $this->newTestedInstance(
            $this->getResultResource('select true where false')
        );

        $this->array($iterator->extract())->isEmpty();
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     * @throws \JsonException
     */
    public function testJsonSerializable(): void
    {
        $iterator = $this->newTestedInstance(
            $this->getResultResource($this->getPikaSql())
        );

        $json = json_encode($iterator, JSON_THROW_ON_ERROR);
        $this->string($json)
            ->isIdenticalTo('[{"id":"1","pika":"a"},{"id":"2","pika":"b"},{"id":"3","pika":"c"},{"id":"4","pika":"d"}]');
    }

    protected function initializeSession(Session $session): void
    {
    }
}
