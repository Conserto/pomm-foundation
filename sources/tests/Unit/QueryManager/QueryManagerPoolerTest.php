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
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\QueryManager\QueryManagerPooler;
use PommProject\Foundation\QueryManager\SimpleQueryManager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(QueryManagerPooler::class)]
final class QueryManagerPoolerTest extends VanillaSessionTestCase
{
    /**
     * @throws FoundationException from buildSession() / getPoolerForType() / getClient() on the happy path
     */
    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $pooler = $session->getPoolerForType('query_manager');
        self::assertInstanceOf(QueryManagerPooler::class, $pooler);

        self::assertInstanceOf(SimpleQueryManager::class, $pooler->getClient());

        try {
            $pooler->getClient('\No\Such\Client');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('Could not load', $e->getMessage());
        }
    }

    /**
     * @throws FoundationException from registerClientPooler()
     */
    protected function initializeSession(Session $session): void
    {
        $session->registerClientPooler(new QueryManagerPooler());
    }

    /**
     * @throws FoundationException from buildSession() / getPoolerForType()
     */
    public function testGetClientRejectsNonClientClass(): void
    {
        $pooler = $this->buildSession()->getPoolerForType('query_manager');

        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage("Query manager 'stdClass' must implement");
        $pooler->getClient(\stdClass::class);
    }
}
