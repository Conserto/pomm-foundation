<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\Unit\Session;

use Mock\PommProject\Foundation\Client\ClientInterface as ClientInterfaceMock;
use Mock\PommProject\Foundation\Client\ClientPoolerInterface as ClientPoolerInterfaceMock;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Connection as FoundationConnection;
use PommProject\Foundation\Session\Session as VanillaSession;
use PommProject\Foundation\Tester\VanillaSessionAtoum;

class Session extends VanillaSessionAtoum
{
    public function testGetStamp(): void
    {
        $this->variable($this->buildSession()->getStamp())
            ->isNull
            ->string($this->buildSession('a stamp')->getStamp())
            ->isEqualTo('a stamp');
    }

    /** @throws FoundationException */
    public function testGetConnection(): void
    {
        $session = $this->buildSession();

        $this->object($session->getConnection())
            ->isInstanceOf(FoundationConnection::class);
    }

    /** @throws FoundationException */
    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $client = $this->getClientInterfaceMock('one');
        $session->registerClient($client);
        $this->variable($session->getClient('test', 'two'))
            ->isNull()
            ->object($session->getClient('test', 'one'))
            ->isIdenticalTo($client)
            ->variable($session->getClient('whatever', 'two'))
            ->isNull()
            ->variable($session->getClient(null, 'two'))
            ->isNull();
    }

    /** @return ClientInterfaceMock&ClientInterface */
    protected function getClientInterfaceMock($identifier): ClientInterfaceMock
    {
        $client = new ClientInterfaceMock();
        $client->getMockController()->getClientType = 'test';
        $client->getMockController()->getClientIdentifier = $identifier;

        return $client;
    }

    /** @throws FoundationException */
    public function testRegisterClient(): void
    {
        $session = $this->buildSession();
        $client_mock = $this->getClientInterfaceMock('one');

        $this->variable($session->getClient('test', 'one'))
            ->isNull()
            ->object($session->registerClient($client_mock))
            ->isInstanceOf(VanillaSession::class)
            ->mock($client_mock)
            ->call('getClientIdentifier')
            ->once()
            ->call('getClientType')
            ->once()
            ->call('initialize')
            ->once()
            ->object($session->getClient('test', 'one'))
            ->isIdenticalTo($client_mock);
    }

    /** @throws FoundationException */
    public function testRegisterPooler(): void
    {
        $session = $this->buildSession();
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');

        $this->boolean($session->hasPoolerForType('test'))
            ->isFalse()
            ->assert('Testing client pooler registration.')
            ->object($session->registerClientPooler($client_pooler_mock))
            ->isInstanceOf(VanillaSession::class)
            ->boolean($session->hasPoolerForType('test'))
            ->isTrue()
            ->mock($client_pooler_mock)
            ->call('getPoolerType')
            ->atLeastOnce()
            ->call('register')
            ->once();
    }

    /** @return ClientPoolerInterfaceMock&ClientPoolerInterface */
    protected function getClientPoolerInterfaceMock($type): ClientPoolerInterfaceMock
    {
        $client_pooler = new ClientPoolerInterfaceMock();
        $client_pooler->getMockController()->getPoolerType = $type;
        $client_pooler->getMockController()->getClient = $this->getClientInterfaceMock('ok');

        return $client_pooler;
    }

    /** @throws FoundationException */
    public function testGetPoolerForType(): void
    {
        $session = $this->buildSession();
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');

        $this->exception(function () use ($session) {
            $session->getPoolerForType('test');
        })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('No pooler registered for type')
            ->object($session
                ->registerClientPooler($client_pooler_mock)
                ->getPoolerForType('test')
            )
            ->isIdenticalTo($client_pooler_mock);
    }

    /** @throws FoundationException */
    public function testGetClientUsingPooler(): void
    {
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');
        $session = $this->buildSession()->registerClientPooler($client_pooler_mock);

        $this->object($session->getClientUsingPooler('test', 'ok'))
            ->isInstanceOf(ClientInterface::class)
            ->exception(function () use ($session) {
                $session->getClientUsingPooler('whatever', 'ok');
            })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('No pooler registered for type');
    }

    /** @throws FoundationException */
    public function testUnderscoreCall(): void
    {
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');
        $session = $this->buildSession()->registerClientPooler($client_pooler_mock);

        $this->exception(
                function () use ($session) {
                    $session->azerty('ok', 'what');
                }
            )
            ->isInstanceOf(\BadFunctionCallException::class)
            ->message->contains('Unknown method')
            ->exception(function () use ($session) {
                $session->getPika('ok');
            })
            ->isInstanceOf(FoundationException::class)
            ->message->contains('No pooler registered for type')
            ->object($session->getTest('ok'))
            ->isInstanceOf(ClientInterface::class)
            ->mock($client_pooler_mock)
            ->call('getClient')
            ->withArguments('ok')
            ->once();
    }

    /** @throws FoundationException */
    public function testShutdown(): void
    {
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');
        $session = $this->buildSession()->registerClientPooler($client_pooler_mock);
        $session->shutdown();

        $this->exception(fn() => $session->getTest('ok'))
            ->isInstanceOf(FoundationException::class)
            ->message->contains('is shutdown')
            ->integer($session->getConnection()->getConnectionStatus())
            ->isEqualTo(FoundationConnection::CONNECTION_STATUS_NONE);

        $session = $this->buildSession();
        $session->getConnection()->executeAnonymousQuery('select true');
        $session->shutdown();
        $this->integer($session->getConnection()->getConnectionStatus())
            ->isEqualTo(FoundationConnection::CONNECTION_STATUS_CLOSED);
    }

    protected function initializeSession(VanillaSession $session): void
    {
    }
}
