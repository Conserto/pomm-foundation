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

namespace PommProject\Foundation\Tests\Unit\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PommProject\Foundation\Listener\Listener;
use PommProject\Foundation\Listener\ListenerPooler;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

#[CoversClass(ListenerPooler::class)]
class ListenerPoolerTest extends FoundationSessionTestCase
{
    /** @var array<string, Listener&MockObject> */
    private array $listenerMocks = [];

    /** @var array<string, array<int, string>> captured notify() arguments per listener identifier */
    private array $notifyCalls = [];

    public function testNotifySingleListener(): void
    {
        $session = $this->buildSession();
        $session->registerClientPooler(new ListenerPooler());

        self::assertInstanceOf(
            ListenerPooler::class,
            $session->getPoolerForType('listener')->notify('pika', ['data' => 1])
        );

        self::assertSame(['pika'], $this->notifyCalls['pika']);
        self::assertArrayNotHasKey('chu', $this->notifyCalls);
    }

    public function testNotifyMultipleListeners(): void
    {
        $session = $this->buildSession();
        $session->registerClientPooler(new ListenerPooler());

        $session->getPoolerForType('listener')->notify(['pika', 'chu', 'whatever'], ['data' => 1]);

        self::assertContains('pika', $this->notifyCalls['pika']);
        self::assertContains('chu', $this->notifyCalls['chu']);
    }

    public function testNotifyAllListeners(): void
    {
        $session = $this->buildSession();
        $session->registerClientPooler(new ListenerPooler());

        self::assertInstanceOf(
            ListenerPooler::class,
            $session->getPoolerForType('listener')->notify('*', ['data' => 1])
        );

        self::assertContains('*', $this->notifyCalls['pika']);
        self::assertContains('*', $this->notifyCalls['chu']);
    }

    public function testNotifyWithSubspace(): void
    {
        $session = $this->buildSession();
        $session->registerClientPooler(new ListenerPooler());

        self::assertInstanceOf(
            ListenerPooler::class,
            $session->getPoolerForType('listener')->notify('pika:plop', ['data' => 1])
        );

        self::assertSame(['pika:plop'], $this->notifyCalls['pika']);
        self::assertArrayNotHasKey('chu', $this->notifyCalls);
    }

    protected function initializeSession(Session $session): void
    {
        foreach (['pika', 'chu'] as $identifier) {
            $mock = $this->getMockBuilder(Listener::class)
                ->setConstructorArgs([$identifier])
                ->onlyMethods(['notify'])
                ->getMock();

            $capturedIdentifier = $identifier;
            $mock->method('notify')
                ->willReturnCallback(
                    function (string $name) use ($capturedIdentifier, $mock): Listener {
                        $this->notifyCalls[$capturedIdentifier][] = $name;

                        return $mock;
                    }
                );

            $this->listenerMocks[$identifier] = $mock;
            $session->registerClient($mock);
        }
    }
}
