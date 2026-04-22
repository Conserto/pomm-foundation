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

namespace PommProject\Foundation\Tests\Unit\QueryManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\QueryManager\SimpleQueryManager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;
use PommProject\Foundation\Tests\Fixture\QueryManager\ListenerTester;

#[CoversClass(SimpleQueryManager::class)]
class SimpleQueryManagerTest extends FoundationSessionTestCase
{
    public function testSimpleQuery(): void
    {
        $session = $this->buildSession();
        $iterator = $this->getQueryManager($session)->query('select true as "one one", null::int4 as "TWO"');

        self::assertInstanceOf(ConvertedResultIterator::class, $iterator);
        self::assertTrue($iterator->current()['one one']);
        self::assertNull($iterator->current()['TWO']);
    }

    public function testQueryWithExtraParameter(): void
    {
        $session = $this->buildSession();

        $this->expectException(SqlException::class);
        $this->getQueryManager($session)->query('select true', ['extra']);
    }

    public function testParametrizedQuery(): void
    {
        $session = $this->buildSession();
        $sql = <<<SQL
            select
              p.id, p.pika, p.a_timestamp, p.a_point
            from (values
                (1, 'one', '1999-08-08'::timestamp, ARRAY[point(1.3, 1.6)], 't'::bool),
                (2, 'two', '2000-09-07'::timestamp, ARRAY[point(1.5, 1.5)], 'f'::bool),
                (3, 'three', '2001-10-25 15:43'::timestamp, ARRAY[point(1.6, 1.4)], 'f'::bool),
                (4, 'four', '2002-01-01 01:10'::timestamp, ARRAY[point(1.8, 2.3)], 't'::bool)
            ) p (id, pika, a_timestamp, a_point, a_bool)
            where {condition}
            SQL;

        $iteratorFromComplex = $this->getQueryManager($session)->query(
            strtr($sql, ['{condition}' => '(p.id >= $* or p.pika = ANY($*::text[])) and p.a_timestamp > $*::timestamp and $*::pg_catalog."circle" @> ANY (p.a_point)']),
            [2, ['chu', 'three'], new \DateTime('2000-01-01'), new Circle('<(1.5,1.5), 0.3>')]
        );
        self::assertSame([2, 3], $iteratorFromComplex->slice('id'));

        $iteratorFromBool = $this->getQueryManager($session)->query(
            strtr($sql, ['{condition}' => 'a_bool = $*::bool']),
            [false]
        );
        self::assertSame([2, 3], $iteratorFromBool->slice('id'));
    }

    public function testSendNotification(): void
    {
        $session = $this->buildSession('test session');
        $listenerTester = new ListenerTester();
        $session->getClientUsingPooler('listener', 'query')
            ->attachAction($listenerTester->call(...));

        $this->getQueryManager($session)->query('select $*::bool as one', [true]);

        self::assertTrue($listenerTester->is_called);
        self::assertSame('select $*::bool as one', $listenerTester->sql);
        self::assertSame(['t'], $listenerTester->parameters);
        self::assertSame('test session', $listenerTester->session_stamp);
        self::assertSame(1, $listenerTester->result_count);
    }

    protected function initializeSession(Session $session): void
    {
    }

    private function getQueryManager(Session $session): SimpleQueryManager
    {
        $queryManager = new SimpleQueryManager();
        $session->registerClient($queryManager);

        return $queryManager;
    }
}
