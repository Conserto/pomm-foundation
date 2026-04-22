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

namespace PommProject\Foundation\Test\PhpUnit\QueryManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\QueryManager\QueryManagerPooler;
use PommProject\Foundation\QueryManager\SimpleQueryManager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(QueryManagerPooler::class)]
class QueryManagerPoolerTest extends VanillaSessionTestCase
{
    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $pooler = $session->getPoolerForType('query_manager');

        self::assertInstanceOf(SimpleQueryManager::class, $pooler->getClient());

        try {
            $pooler->getClient('\No\Such\Client');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('Could not load', $e->getMessage());
        }
    }

    protected function initializeSession(Session $session): void
    {
        $session->registerClientPooler(new QueryManagerPooler());
    }
}
