<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Client;

use Atoum;
use Mock\PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Exception\FoundationException;

class ClientHolder extends Atoum
{
    public function testGet(): void
    {
        $clientHolder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $this->variable($clientHolder->get('no_type', 'no_name'))
            ->isNull()
            ->object($clientHolder->add($client_1)->get('test', 'one'))
            ->isIdenticalTo($client_1);
    }

    protected function getClientHolder()
    {
        return $this->newTestedInstance();
    }

    protected function getClientMock($identifier, $type = 'test'): ClientInterface
    {
        $client = new ClientInterface;
        $client->getMockController()->getClientIdentifier = $identifier;
        $client->getMockController()->getClientType = $type;

        return $client;
    }

    public function testHas(): void
    {
        $clientHolder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $this->boolean($clientHolder->has('test', 'one'))
            ->isFalse()
            ->boolean($clientHolder->add($client_1)->has('test', 'one'))
            ->isTrue();
    }

    public function testClear(): void
    {
        $clientHolder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $client_2 = $this->getClientMock('two');

        $this->object(
            $clientHolder->add($client_1)
                ->add($client_2)
                ->clear('test', 'one')
        )
            ->isInstanceOf(\PommProject\Foundation\Client\ClientHolder::class)
            ->boolean($clientHolder->has('test', 'one'))
            ->isFalse()
            ->mock($client_1)
            ->call('shutdown')
            ->once()
            ->boolean($clientHolder->has('test', 'two'))
            ->isTrue();
    }

    public function testShutdown(): void
    {
        $client_1 = $this->getClientMock('one');
        $client_2 = $this->getClientMock('two');
        $client_3 = $this->getClientMock('three');
        $this->calling($client_3)->shutdown = function (): never {
            throw new FoundationException("plop");
        };
        $clientHolder = $this->getClientHolder()
            ->add($client_3)
            ->add($client_1)
            ->add($client_2);
        $this->object($exception = ($clientHolder->shutdown()[0]))
            ->isInstanceOf(\PommProject\Foundation\Exception\FoundationException::class)
            ->string($exception->getMessage())->contains('plop')
            ->mock($client_1)
            ->call('shutdown')
            ->once()
            ->mock($client_2)
            ->call('shutdown')
            ->once();
    }

    public function testGetAllFor(): void
    {
        $clientHolder = $this->getClientHolder()
            ->add($this->getClientMock('one'))
            ->add($this->getClientMock('two'));

        $this->array($clientHolder->getAllFor('whatever'))
            ->isEmpty()
            ->array($clientHolder->getAllFor('test'))
            ->hasSize(2);
    }
}
