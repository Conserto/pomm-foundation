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
            $this->sessionBuilder = $this->createSessionBuilder($GLOBALS['pomm_db1']);
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
