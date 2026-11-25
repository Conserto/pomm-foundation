<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Tester;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(VanillaSessionTestCase::class)]
final class VanillaSessionTestCaseTest extends VanillaSessionTestCase
{
    /**
     * Sessions built during a test are shut down after it, which closes their connections.
     *
     * @throws FoundationException
     */
    public function testShutsDownSessions(): void
    {
        $session = $this->buildSession();
        $session->getConnection()->executeAnonymousQuery('select 1');

        $this->shutdownSessions();

        self::assertSame(Connection::CONNECTION_STATUS_CLOSED, $session->getConnection()->getConnectionStatus());
    }

    /**
     * A session failing to shut down does not keep the others open; its error is rethrown afterwards.
     *
     * @throws FoundationException
     */
    public function testShutsDownEverySessionAndRethrowsTheFirstError(): void
    {
        $failing = $this->buildSession();
        // Fails once only: the session destructor shuts it down again when PHP frees it.
        $failing->registerClient(new class () implements ClientInterface {
            private bool $failed = false;

            public function initialize(Session $session): void
            {
            }

            public function shutdown(): void
            {
                if (!$this->failed) {
                    $this->failed = true;

                    throw new \RuntimeException('Shutdown failed.');
                }
            }

            public function getClientType(): string
            {
                return 'test';
            }

            public function getClientIdentifier(): string
            {
                return 'failing';
            }
        });
        $other = $this->buildSession();
        $other->getConnection()->executeAnonymousQuery('select 1');

        try {
            $this->shutdownSessions();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('Shutdown failed.', $e->getMessage());
        }

        self::assertSame(Connection::CONNECTION_STATUS_CLOSED, $other->getConnection()->getConnectionStatus());
    }

    protected function initializeSession(Session $session): void
    {
    }
}
