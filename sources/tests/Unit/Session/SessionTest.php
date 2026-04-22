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

namespace PommProject\Foundation\Tests\Unit\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Connection as FoundationConnection;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\VanillaSessionTestCase;

#[CoversClass(Session::class)]
class SessionTest extends VanillaSessionTestCase
{
    public function testGetStamp(): void
    {
        self::assertNull($this->buildSession()->getStamp());
        self::assertSame('a stamp', $this->buildSession('a stamp')->getStamp());
    }

    public function testGetConnection(): void
    {
        self::assertInstanceOf(FoundationConnection::class, $this->buildSession()->getConnection());
    }

    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $client = $this->getClientInterfaceMock('one');
        $session->registerClient($client);

        self::assertNull($session->getClient('test', 'two'));
        self::assertSame($client, $session->getClient('test', 'one'));
        self::assertNull($session->getClient('whatever', 'two'));
        self::assertNull($session->getClient(null, 'two'));
    }

    public function testRegisterClient(): void
    {
        $session = $this->buildSession();
        $clientMock = $this->getClientInterfaceMock('one');

        $clientMock->expects(self::once())->method('getClientIdentifier')->willReturn('one');
        $clientMock->expects(self::once())->method('getClientType')->willReturn('test');
        $clientMock->expects(self::once())->method('initialize');

        self::assertNull($session->getClient('test', 'one'));
        self::assertInstanceOf(Session::class, $session->registerClient($clientMock));
        self::assertSame($clientMock, $session->getClient('test', 'one'));
    }

    public function testRegisterPooler(): void
    {
        $session = $this->buildSession();
        $clientPoolerMock = $this->getClientPoolerInterfaceMock('test');

        $clientPoolerMock->expects(self::atLeastOnce())->method('getPoolerType')->willReturn('test');
        $clientPoolerMock->expects(self::once())->method('register');

        self::assertFalse($session->hasPoolerForType('test'));
        self::assertInstanceOf(Session::class, $session->registerClientPooler($clientPoolerMock));
        self::assertTrue($session->hasPoolerForType('test'));
    }

    public function testGetPoolerForType(): void
    {
        $session = $this->buildSession();
        $clientPoolerMock = $this->getClientPoolerInterfaceMock('test');

        try {
            $session->getPoolerForType('test');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No pooler registered for type', $e->getMessage());
        }

        self::assertSame(
            $clientPoolerMock,
            $session->registerClientPooler($clientPoolerMock)->getPoolerForType('test')
        );
    }

    public function testGetClientUsingPooler(): void
    {
        $clientPoolerMock = $this->getClientPoolerInterfaceMock('test');
        $session = $this->buildSession()->registerClientPooler($clientPoolerMock);

        self::assertInstanceOf(ClientInterface::class, $session->getClientUsingPooler('test', 'ok'));

        try {
            $session->getClientUsingPooler('whatever', 'ok');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No pooler registered for type', $e->getMessage());
        }
    }

    public function testUnderscoreCall(): void
    {
        $clientPoolerMock = $this->getClientPoolerInterfaceMock('test');
        $clientPoolerMock->expects(self::once())->method('getClient')->with('ok');

        $session = $this->buildSession()->registerClientPooler($clientPoolerMock);

        try {
            $session->azerty('ok', 'what');
            self::fail('Expected BadFunctionCallException was not thrown.');
        } catch (\BadFunctionCallException $e) {
            self::assertStringContainsString('Unknown method', $e->getMessage());
        }

        try {
            $session->getPika('ok');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No pooler registered for type', $e->getMessage());
        }

        self::assertInstanceOf(ClientInterface::class, $session->getTest('ok'));
    }

    public function testShutdown(): void
    {
        $clientPoolerMock = $this->getClientPoolerInterfaceMock('test');
        $session = $this->buildSession()->registerClientPooler($clientPoolerMock);
        $session->shutdown();

        try {
            $session->getTest('ok');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('is shutdown', $e->getMessage());
        }

        self::assertSame(
            FoundationConnection::CONNECTION_STATUS_NONE,
            $session->getConnection()->getConnectionStatus()
        );

        $session = $this->buildSession();
        $session->getConnection()->executeAnonymousQuery('select true');
        $session->shutdown();

        self::assertSame(
            FoundationConnection::CONNECTION_STATUS_CLOSED,
            $session->getConnection()->getConnectionStatus()
        );
    }

    protected function initializeSession(Session $session): void
    {
    }

    /**
     * @return ClientInterface&MockObject
     */
    private function getClientInterfaceMock(string $identifier): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getClientType')->willReturn('test');
        $client->method('getClientIdentifier')->willReturn($identifier);

        return $client;
    }

    /**
     * @return ClientPoolerInterface&MockObject
     */
    private function getClientPoolerInterfaceMock(string $type): ClientPoolerInterface
    {
        $pooler = $this->createMock(ClientPoolerInterface::class);
        $pooler->method('getPoolerType')->willReturn($type);
        $pooler->method('getClient')->willReturn($this->getClientInterfaceMock('ok'));

        return $pooler;
    }
}
