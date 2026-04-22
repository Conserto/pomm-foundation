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

namespace PommProject\Foundation\Tester;

use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Session\SessionBuilder;

/**
 * PHPUnit equivalent of {@see VanillaSessionAtoum}. Exposes the same
 * `buildSession()` contract so downstream packages can migrate their
 * tests in place without losing the helper semantics.
 */
abstract class VanillaSessionTestCase extends TestCase
{
    private ?SessionBuilder $sessionBuilder = null;

    /** @throws FoundationException */
    protected function buildSession(?string $stamp = null): Session
    {
        $session = $this->getSessionBuilder()->buildSession($stamp);
        $this->initializeSession($session);

        return $session;
    }

    private function getSessionBuilder(): SessionBuilder
    {
        if ($this->sessionBuilder === null) {
            // connection:persist => true forces pg_connect(..., FORCE_NEW) so every test
            // gets its own backend; without this the shared pg_pconnect pool leaks prepared
            // statements across tests since PHPUnit keeps the process alive.
            $configuration = ['connection:persist' => true] + $GLOBALS['pomm_db1'];
            $this->sessionBuilder = $this->createSessionBuilder($configuration);
        }

        return $this->sessionBuilder;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function createSessionBuilder(array $configuration): SessionBuilder
    {
        return new SessionBuilder($configuration);
    }

    abstract protected function initializeSession(Session $session): void;
}
