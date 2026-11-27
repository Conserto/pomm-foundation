<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum as TestUnitEnum;
use PommProject\Foundation\Where;

#[CoversClass(Where::class)]
final class WhereTest extends TestCase
{
    public function testCreate(): void
    {
        // Smoke test for the two public factory overloads — exceptions would surface here.
        self::assertSame('true', Where::create()->__toString());
        self::assertSame(
            'a = pika($*, $*)',
            Where::create('a = pika($*, $*)', [1, 2])->__toString()
        );
    }

    public function testCreateWhereIn(): void
    {
        $where1 = Where::createWhereIn('b', [1, 2, 3, 4]);
        $where2 = Where::createWhereIn('(a, b)', [[1, 2], [3, 4]]);

        self::assertSame('b IN ($*, $*, $*, $*)', $where1->__toString());
        self::assertSame('(a, b) IN (($*, $*), ($*, $*))', $where2->__toString());
    }

    /**
     * Enum values must be carried as-is through extractValues — RecursiveArrayIterator
     * rejects enum instances on PHP 8.1+, so the flattening step uses array_walk_recursive
     * instead and must not descend into the objects themselves.
     */
    public function testCreateWhereInWithEnumValues(): void
    {
        $where = Where::createWhereIn('col', [BackedEnum::A, BackedEnum::NUMERIC]);

        self::assertSame('col IN ($*, $*)', $where->__toString());
        self::assertSame([BackedEnum::A, BackedEnum::NUMERIC], $where->getValues());

        $where = Where::createWhereIn('col', [IntBackedEnum::TWO, TestUnitEnum::Active]);
        self::assertSame([IntBackedEnum::TWO, TestUnitEnum::Active], $where->getValues());
    }

    public function testCreateWhereNotIn(): void
    {
        $where = Where::createWhereNotIn('(a, b)', [[1, 2], [3, 4]]);

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

    /** Each condition keeps its values when they share a key: there is one value per parameter. */
    public function testValuesWithSameKeys(): void
    {
        // Named keys are not documented, but they are accepted and used to lose values.
        $where = Where::create('a = $*', ['id' => 1]); // @phpstan-ignore argument.type
        $where->andWhere('b = $*', ['id' => 2]); // @phpstan-ignore argument.type

        self::assertSame([1, 2], $where->getValues());
        self::assertSame([3], Where::create('c = $*', ['id' => 3])->getValues()); // @phpstan-ignore argument.type
    }

    public function testGetElementWithoutElement(): void
    {
        self::assertSame('a', Where::create('a')->getElement());

        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage('This Where clause has no element');
        Where::create('a')->andWhere('b')->getElement();
    }

    public function testStackWithoutOperatorCannotBeCombined(): void
    {
        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage('Cannot combine a Where clause stack that has no operator.');
        Where::create()->setStack([Where::create('a'), Where::create('b')])->andWhere('c');
    }

    public function testNonStringElement(): void
    {
        self::assertSame('(a AND 42)', (string) Where::create('a')->andWhere(42));

        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage("A Where clause must be a string, 'array' given.");
        Where::create('a')->andWhere(['b']);
    }
}
