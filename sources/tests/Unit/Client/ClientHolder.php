<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Client;

use Atoum;
use Mock\PommProject\Foundation\Client\ClientInterface;

class ClientHolder extends Atoum
{
    protected function getClientHolder()
    {
        return $this->newTestedInstance();
    }

    protected function getClientMock($identifier, $type = 'test')
    {
        $client = new ClientInterface;
        $client->getMockController()->getClientIdentifier = $identifier;
        $client->getMockController()->getClientType       = $type;

        return $client;
    }

    public function testGet()
    {
        $client_holder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $this
            ->variable($client_holder->get('no_type', 'no_name'))
            ->isNull()
            ->object($client_holder->add($client_1)->get('test', 'one'))
            ->isIdenticalTo($client_1)
            ;
    }

    public function testHas()
    {
        $client_holder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $this
            ->boolean($client_holder->has('test', 'one'))
            ->isFalse()
            ->boolean($client_holder->add($client_1)->has('test', 'one'))
            ->isTrue()
            ;
    }

    public function testClear()
    {
        $client_holder = $this->getClientHolder();
        $client_1 = $this->getClientMock('one');
        $client_2 = $this->getClientMock('two');

        $this
            ->object($client_holder
                ->add($client_1)
                ->add($client_2)
                ->clear('test', 'one')
            )
            ->isInstanceOf('\PommProject\Foundation\Client\ClientHolder')
            ->boolean($client_holder->has('test', 'one'))
            ->isFalse()
            ->mock($client_1)
            ->call('shutdown')
            ->once()
            ->boolean($client_holder->has('test', 'two'))
            ->isTrue()
            ;
    }

    public function testShutdown()
    {
        $client_1 = $this->getClientMock('one');
        $client_2 = $this->getClientMock('two');
        $client_holder = $this->getClientHolder()
            ->add($client_1)
            ->add($client_2)
            ;
        $this
            ->object($client_holder->shutdown())
            ->isInstanceOf('\PommProject\Foundation\Client\ClientHolder')
            ->mock($client_1)
            ->call('shutdown')
            ->once()
            ->mock($client_2)
            ->call('shutdown')
            ->once()
            ;
    }
}
