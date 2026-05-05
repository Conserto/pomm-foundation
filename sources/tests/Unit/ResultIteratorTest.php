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

namespace PommProject\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\ResultIterator;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(ResultIterator::class)]
class ResultIteratorTest extends VanillaSessionTestCase
{
    public function testConstructor(): void
    {
        $iterator = new ResultIterator($this->getResultResource('select true::boolean'));

        self::assertInstanceOf(ResultIterator::class, $iterator);
        self::assertInstanceOf(\Countable::class, $iterator);
        self::assertInstanceOf(\Iterator::class, $iterator);
    }

    public function testGet(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        self::assertSame(['id' => '1', 'pika' => 'a'], $iterator->get(0));
        self::assertSame(['id' => '3', 'pika' => 'c'], $iterator->get(2));
        self::assertSame(['id' => '2', 'pika' => 'b'], $iterator->get(1));

        try {
            $iterator->get(5);
            self::fail('Expected OutOfBoundsException was not thrown.');
        } catch (\OutOfBoundsException $e) {
            self::assertStringContainsString('Cannot jump to non existing row', $e->getMessage());
        }
    }

    public function testGetOnEmptyResult(): void
    {
        $iterator = new ResultIterator($this->getResultResource('select true where false'));

        self::assertSame(0, $iterator->count());
        self::assertTrue($iterator->isEmpty());
        self::assertNull($iterator->current());
        self::assertNull($iterator->isLast());
        self::assertNull($iterator->isFirst());
    }

    public function testHasAndCount(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        self::assertSame(4, $iterator->count());
        self::assertTrue($iterator->has(1));
        self::assertFalse($iterator->has(4));
    }

    public function testIterator(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        foreach ($iterator as $index => $element) {
            if ($index === 0) {
                self::assertTrue($iterator->isFirst());
            } elseif ($index === $iterator->count() - 1) {
                self::assertTrue($iterator->isLast());
            } else {
                self::assertFalse($iterator->isFirst());
                self::assertFalse($iterator->isLast());
            }

            // Postgres returns ids as strings by default (no converter registered on the vanilla session).
            self::assertSame((string) ($index + 1), $element['id']);
        }
    }

    public function testSlice(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        self::assertSame(['a', 'b', 'c', 'd'], $iterator->slice('pika'));
        self::assertSame(['1', '2', '3', '4'], $iterator->slice('id'));

        try {
            $iterator->slice('no_such_key');
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Could not find field', $e->getMessage());
        }
    }

    public function testExtract(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        self::assertSame(
            [
                ['id' => '1', 'pika' => 'a'],
                ['id' => '2', 'pika' => 'b'],
                ['id' => '3', 'pika' => 'c'],
                ['id' => '4', 'pika' => 'd'],
            ],
            $iterator->extract()
        );

        $emptyIterator = new ResultIterator($this->getResultResource('select true where false'));
        self::assertSame([], $emptyIterator->extract());
    }

    public function testJsonSerializable(): void
    {
        $iterator = new ResultIterator($this->getResultResource($this->getPikaSql()));

        self::assertSame(
            '[{"id":"1","pika":"a"},{"id":"2","pika":"b"},{"id":"3","pika":"c"},{"id":"4","pika":"d"}]',
            json_encode($iterator, JSON_THROW_ON_ERROR)
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
}
