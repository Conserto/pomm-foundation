<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\QueryManager;

use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionAtoum;

class SimpleQueryManager extends FoundationSessionAtoum
{
    /** @throws FoundationException */
    public function testSimpleQuery(): void
    {
        $session = $this->buildSession();
        $iterator = $this->getQueryManager($session)->query('select true as "one one", null::int4 as "TWO"');
        $this->object($iterator)
            ->isInstanceOf(ConvertedResultIterator::class)
            ->boolean($iterator->current()['one one'])
            ->isTrue()
            ->variable($iterator->current()['TWO'])
            ->isNull();
    }

    protected function getQueryManager(Session $session)
    {
        $query_manager = $this->newTestedInstance();
        $session->registerClient($query_manager);

        return $query_manager;
    }

    /** @throws FoundationException */
    public function testQueryWithExtraParameter(): void
    {
        $session = $this->buildSession();
        $this->exception(function () use ($session): void {
            $this->getQueryManager($session)->query('select true', ['extra']);
        })
            ->isInstanceOf(SqlException::class);
    }

    /** @throws FoundationException */
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
        $iterator = $this->getQueryManager($session)
            ->query(
                strtr(
                    $sql,
                    ['{condition}' => '(p.id >= $* or p.pika = ANY($*::text[])) and p.a_timestamp > $*::timestamp and $*::pg_catalog."circle" @> ANY (p.a_point)']
                ),
                [2, ['chu', 'three'], new \DateTime('2000-01-01'), new Circle('<(1.5,1.5), 0.3>')]
            );
        $this->array($iterator->slice('id'))
            ->isIdenticalTo([2, 3]);

        $iterator = $this->getQueryManager($session)
            ->query(
                strtr($sql, ['{condition}' => 'a_bool = $*::bool']),
                [false]
            );
        $this->array($iterator->slice('id'))
            ->isIdenticalTo([2, 3]);
    }

    /** @throws FoundationException */
    public function testSendNotification(): void
    {
        $session = $this->buildSession('test session');
        $listener_tester = new ListenerTester();
        $session->getClientUsingPooler('listener', 'query')
            ->attachAction($listener_tester->call(...));

        $this->getQueryManager($session)->query('select $*::bool as one', [true]);

        $this->boolean($listener_tester->is_called)
            ->isTrue()
            ->string($listener_tester->sql)
            ->isEqualTo('select $*::bool as one')
            ->array($listener_tester->parameters)
            ->isIdenticalTo(['t'])
            ->string($listener_tester->session_stamp)
            ->isEqualTo('test session')
            ->integer($listener_tester->result_count)
            ->isEqualTo(1);
    }

    protected function initializeSession(Session $session): void
    {
    }
}
