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

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionAtoum;

class QueryManagerPooler extends VanillaSessionAtoum
{
    /** @throws FoundationException */
    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $this->object(
            $session->getPoolerForType('query_manager')
                ->getClient()
        )
            ->isInstanceOf(\PommProject\Foundation\QueryManager\SimpleQueryManager::class)
            ->exception(fn() => $session
                ->getPoolerForType('query_manager')
                ->getClient('\No\Such\Client'))
            ->isInstanceOf(FoundationException::class)
            ->message->contains('Could not load');
    }

    protected function initializeSession(Session $session): void
    {
        $session->registerClientPooler($this->newTestedInstance());
    }
}
