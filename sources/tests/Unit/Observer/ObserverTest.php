<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Observer;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Observer\Observer;
use PommProject\Foundation\Observer\ObserverPooler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(Observer::class)]
class ObserverTest extends VanillaSessionTestCase
{
    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() /
     *         registerClient() / getObserver() and the NOTIFY query issued by notify()
     */
    public function testGetNotification(): void
    {
        $session = $this->buildSession()->registerClient(new Observer('pika'));

        self::assertNull($session->getObserver('pika')->getNotification());

        $this->notify('pika');
        // getNotification() consumes the pending event, so capture it once before asserting.
        $first = $session->getObserver('pika')->getNotification();
        self::assertIsArray($first);
        self::assertContains('pika', $first);
        self::assertContains('', $first);

        $this->notify('pika', 'chu');
        $second = $session->getObserver('pika')->getNotification();
        self::assertIsArray($second);
        self::assertContains('pika', $second);
        self::assertContains('chu', $second);
        self::assertNull($session->getObserver('pika')->getNotification());
    }

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() /
     *         registerClient() / getObserver() / throwNotification() and the NOTIFY query
     */
    public function testThrowNotification(): void
    {
        $session = $this->buildSession()->registerClient(new Observer('an identifier'));

        self::assertInstanceOf(Observer::class, $session->getObserver('an identifier')->throwNotification());

        $this->notify('an identifier', 'some data');
        try {
            $session->getObserver('an identifier')->throwNotification();
            self::fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('some data', $e->getMessage());
        }

        self::assertInstanceOf(Observer::class, $session->getObserver('an identifier')->throwNotification());
    }

    /**
     * @throws FoundationException from registerClientPooler()
     */
    protected function initializeSession(Session $session): void
    {
        $session->registerClientPooler(new ObserverPooler());
    }

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() /
     *         executeAnonymousQuery() / escape* helpers
     */
    private function notify(string $channel, ?string $data = null): void
    {
        $session = $this->buildSession();
        $connection = $session->getConnection();

        $connection->executeAnonymousQuery(
            sprintf(
                'notify %s, %s',
                $connection->escapeIdentifier($channel),
                $connection->escapeLiteral($data)
            )
        );

        sleep(1);
    }
}
