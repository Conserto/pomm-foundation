<?php

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\PhpUnit;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

#[CoversClass(ConvertedResultIterator::class)]
class ConvertedResultIteratorTest extends FoundationSessionTestCase
{
    public function testConstructor(): void
    {
        $iterator = new ConvertedResultIterator(
            $this->getResultResource('select true::boolean'),
            $this->buildSession()
        );

        self::assertInstanceOf(ConvertedResultIterator::class, $iterator);
        self::assertInstanceOf(\Countable::class, $iterator);
        self::assertInstanceOf(\Iterator::class, $iterator);
    }

    public function testGet(): void
    {
        $iterator = new ConvertedResultIterator(
            $this->getResultResource($this->getPikaSql()),
            $this->buildSession()
        );

        self::assertSame(['id' => 1, 'pika' => 'a', 'chu' => null], $iterator->get(0));
        self::assertSame(['id' => 3, 'pika' => 'c', 'chu' => [1]], $iterator->get(2));
    }

    public function testGetWithArray(): void
    {
        $sql = 'select array[1, 2, 3, null]::int4[] as array_one, array[null, null]::int4[] as array_two';
        $iterator = new ConvertedResultIterator(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        self::assertSame(1, $iterator->count());
        self::assertSame([1, 2, 3, null], $iterator->current()['array_one']);
        self::assertSame([null, null], $iterator->current()['array_two']);
    }

    public function testGetWithNoType(): void
    {
        $sql = 'select null as one, array[null, null] as two';
        $iterator = new ConvertedResultIterator(
            $this->getResultResource($sql),
            $this->buildSession()
        );

        self::assertSame(1, $iterator->count());
        self::assertNull($iterator->current()['one']);
        self::assertSame([null, null], $iterator->current()['two']);
    }

    public function testSlice(): void
    {
        $iterator = new ConvertedResultIterator(
            $this->getResultResource($this->getPikaSql()),
            $this->buildSession()
        );

        self::assertSame(['a', 'b', 'c', 'd'], $iterator->slice('pika'));
        self::assertSame([1, null, 3, 4], $iterator->slice('id'));
    }

    public function testExtract(): void
    {
        $iterator = new ConvertedResultIterator(
            $this->getResultResource($this->getPikaSql()),
            $this->buildSession()
        );

        self::assertSame(
            [
                ['id' => 1, 'pika' => 'a', 'chu' => null],
                ['id' => null, 'pika' => 'b', 'chu' => [null]],
                ['id' => 3, 'pika' => 'c', 'chu' => [1]],
                ['id' => 4, 'pika' => 'd', 'chu' => [2, 2]],
            ],
            $iterator->extract()
        );
    }

    protected function initializeSession(Session $session): void
    {
    }

    /**
     * @param array<int, mixed> $params
     */
    private function getResultResource(string $sql, array $params = []): ResultHandler
    {
        return $this->buildSession()->getConnection()->sendQueryWithParameters($sql, $params);
    }

    private function getPikaSql(): string
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
}
