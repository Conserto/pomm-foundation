<?php

/*
 * This file is part of the PommProject's Foundation package.
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
use PommProject\Foundation\Where;

#[CoversClass(Where::class)]
class WhereTest extends TestCase
{
    public function testCreate(): void
    {
        self::assertInstanceOf(Where::class, Where::create());
        self::assertInstanceOf(Where::class, Where::create('a = pika($*, $*)', [1, 2]));
    }

    public function testCreateWhereIn(): void
    {
        $where1 = Where::createWhereIn('b', [1, 2, 3, 4]);
        $where2 = Where::createWhereIn('(a, b)', [[1, 2], [3, 4]]);

        self::assertInstanceOf(Where::class, $where1);
        self::assertSame('b IN ($*, $*, $*, $*)', $where1->__toString());
        self::assertSame('(a, b) IN (($*, $*), ($*, $*))', $where2->__toString());
    }

    public function testCreateWhereNotIn(): void
    {
        $where = Where::createWhereNotIn('(a, b)', [[1, 2], [3, 4]]);

        self::assertInstanceOf(Where::class, $where);
        self::assertSame('(a, b) NOT IN (($*, $*), ($*, $*))', $where->__toString());
    }

    public function testIsEmpty(): void
    {
        $where = new Where();

        self::assertTrue($where->isEmpty());
        self::assertFalse($where->andWhere('a')->isEmpty());
    }

    public function testAndWhere(): void
    {
        $where = new Where('a', [1]);

        self::assertSame('a', $where->andWhere(new Where())->__toString());
        self::assertSame('(a AND b)', $where->andWhere(new Where('b'))->__toString());
        self::assertSame('(a AND b AND c)', $where->andWhere(new Where('c', [2, 3]))->__toString());
        self::assertSame([1, 2, 3], $where->getValues());
    }

    public function testOrWhere(): void
    {
        $where = new Where('a', [1]);

        self::assertSame('a', $where->orWhere(new Where())->__toString());
        self::assertSame('(a OR b)', $where->orWhere(new Where('b'))->__toString());
        self::assertSame('(a OR b OR c)', $where->orWhere(new Where('c', [2, 3]))->__toString());
        self::assertSame([1, 2, 3], $where->getValues());
    }

    public function testAndOrWhere(): void
    {
        $where = new Where('a', [1]);
        $where->andWhere('b')
            ->orWhere('c', [2, 3])
            ->orWhere('d', [4])
            ->andWhere('e');

        self::assertSame('(((a AND b) OR c OR d) AND e)', $where->__toString());
        self::assertSame([1, 2, 3, 4], $where->getValues());
    }
}
