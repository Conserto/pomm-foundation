<?php

declare(strict_types=1);

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

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Session\SessionBuilder;

/**
 * Base PHPUnit test case that builds a vanilla {@see Session} (no poolers or
 * converters registered). Subclasses implement {@see initializeSession()} to
 * plug in any clients required by the test.
 */
abstract class VanillaSessionTestCase extends TestCase
{
    private ?SessionBuilder $sessionBuilder = null;

    /** @var list<Session> sessions built during the current test, shut down after it */
    private array $sessions = [];

    /** @throws FoundationException */
    protected function buildSession(?string $stamp = null): Session
    {
        $session = $this->getSessionBuilder()->buildSession($stamp);
        $this->sessions[] = $session;
        $this->initializeSession($session);

        return $session;
    }

    /**
     * Shut down the sessions built during the test, which closes their connections. A session is referenced by its own
     * clients, so PHP only frees it, and closes its connection, at a later garbage collection: a suite building many
     * sessions came close to PostgreSQL's connection limit (too many clients already).
     *
     * A session built with buildSession() therefore lives for one test: do not share it between tests (static
     * property, #[Depends]). Shutting a session down twice is harmless, so a test may still shut down its sessions
     * itself. Every session is shut down even if one fails; the first error is then rethrown. PHPUnit skips this hook
     * when tearDown() throws: the sessions of that test are then only closed at a garbage collection.
     *
     * Final: PHP does not inherit attributes, so an override without #[After] would silently disable it.
     *
     * @throws \Throwable the first error raised while shutting down a session
     */
    #[After]
    final protected function shutdownSessions(): void
    {
        $sessions = $this->sessions;
        $this->sessions = [];
        $error = null;

        foreach ($sessions as $session) {
            try {
                $session->shutdown();
            } catch (\Throwable $e) {
                $error ??= $e;
            }
        }

        if ($error instanceof \Throwable) {
            throw $error;
        }
    }

    private function getSessionBuilder(): SessionBuilder
    {
        if (!$this->sessionBuilder instanceof SessionBuilder) {
            // connection:persist => true forces pg_connect(..., FORCE_NEW) so every test
            // gets its own backend; without this the shared pg_pconnect pool leaks prepared
            // statements across tests since PHPUnit keeps the process alive.
            $database = $GLOBALS['pomm_db1'] ?? null;
            if (!is_array($database)) {
                // Used to be a TypeError (array union with a non array).
                throw new FoundationException("\$GLOBALS['pomm_db1'] must hold the test database configuration array.");
            }
            /** @var array<string, mixed> $database */
            $configuration = ['connection:persist' => true] + $database;
            $this->sessionBuilder = $this->createSessionBuilder($configuration);
        }

        return $this->sessionBuilder;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function createSessionBuilder(#[\SensitiveParameter] array $configuration): SessionBuilder
    {
        return new SessionBuilder($configuration);
    }

    abstract protected function initializeSession(Session $session): void;
}
