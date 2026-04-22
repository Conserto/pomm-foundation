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

namespace PommProject\Foundation\Test\PhpUnit\PreparedQuery;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\PreparedQuery\PreparedQuery;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

#[CoversClass(PreparedQuery::class)]
class PreparedQueryTest extends FoundationSessionTestCase
{
    public function testConstruct(): void
    {
        try {
            new PreparedQuery(null);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('empty query', $e->getMessage());
        }

        $query = new PreparedQuery('abcd');
        self::assertInstanceOf(PreparedQuery::class, $query);
        self::assertSame(PreparedQuery::getSignatureFor('abcd'), $query->getClientIdentifier());
    }

    public function testExecute(): void
    {
        $session = $this->buildSession();
        $sql = <<<SQL
            select
              p.id, p.pika, p.a_timestamp, p.a_point
            from (values
                (1, 'one', '1999-08-08'::timestamp, ARRAY[point(1.3, 1.6)]),
                (2, 'two', '2000-09-07'::timestamp, ARRAY[point(1.5, 1.5)]),
                (3, 'three', '2001-10-25 15:43'::timestamp, ARRAY[point(1.6, 1.4)]),
                (4, 'four', '2002-01-01 01:10'::timestamp, ARRAY[point(1.8, 2.3)])
            ) p (id, pika, a_timestamp, a_point)
            where (p.id >= $* or p.pika = ANY($*::text[])) and p.a_timestamp > $*::timestamp and $*::pg_catalog."circle" @> ANY (p.a_point)
            SQL;
        $query = new PreparedQuery($sql);
        $session->registerClient($query);

        $result = $query->execute(
            [2, ['pika, chu', 'three'], new \DateTime('2000-01-01'), new Circle('<(1.5,1.5), 0.3>')]
        );

        self::assertSame(2, $result->countRows());
    }

    protected function initializeSession(Session $session): void
    {
    }
}
