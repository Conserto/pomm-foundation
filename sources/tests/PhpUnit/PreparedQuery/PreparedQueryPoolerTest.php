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
use PommProject\Foundation\PreparedQuery\PreparedQuery;
use PommProject\Foundation\PreparedQuery\PreparedQueryPooler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(PreparedQueryPooler::class)]
class PreparedQueryPoolerTest extends VanillaSessionTestCase
{
    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $sql = 'select true where $*::boolean';
        $query = $session->getPoolerForType('prepared_query')->getClient($sql);

        self::assertInstanceOf(PreparedQuery::class, $query);
        self::assertSame($sql, $query->getSql());
        self::assertEquals($query, $session->getClientUsingPooler('prepared_query', $sql));
    }

    protected function initializeSession(Session $session): void
    {
        $session->registerClientPooler(new PreparedQueryPooler());
    }
}
