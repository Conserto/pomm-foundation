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

namespace PommProject\Foundation\Test\PhpUnit\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Client\ClientHolder;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(ClientHolder::class)]
class ClientHolderTest extends TestCase
{
    public function testGet(): void
    {
        $clientHolder = $this->getClientHolder();
        $clientOne = $this->getClientMock('one');

        self::assertNull($clientHolder->get('no_type', 'no_name'));
        self::assertSame(
            $clientOne,
            $clientHolder->add($clientOne)->get('test', 'one')
        );
    }

    public function testHas(): void
    {
        $clientHolder = $this->getClientHolder();
        $clientOne = $this->getClientMock('one');

        self::assertFalse($clientHolder->has('test', 'one'));
        self::assertTrue($clientHolder->add($clientOne)->has('test', 'one'));
    }

    public function testClear(): void
    {
        $clientHolder = $this->getClientHolder();
        $clientOne = $this->getClientMock('one');
        $clientTwo = $this->getClientMock('two');

        // atoum: ->mock($client_1)->call('shutdown')->once() — PHPUnit expects this up-front
        $clientOne->expects(self::once())->method('shutdown');

        $returned = $clientHolder->add($clientOne)
            ->add($clientTwo)
            ->clear('test', 'one');

        self::assertInstanceOf(ClientHolder::class, $returned);
        self::assertFalse($clientHolder->has('test', 'one'));
        self::assertTrue($clientHolder->has('test', 'two'));
    }

    public function testShutdown(): void
    {
        $clientOne = $this->getClientMock('one');
        $clientTwo = $this->getClientMock('two');
        $clientThree = $this->getClientMock('three');

        $clientOne->expects(self::once())->method('shutdown');
        $clientTwo->expects(self::once())->method('shutdown');
        $clientThree->method('shutdown')->willThrowException(new FoundationException('plop'));

        $clientHolder = $this->getClientHolder()
            ->add($clientThree)
            ->add($clientOne)
            ->add($clientTwo);

        $exception = $clientHolder->shutdown()[0];

        self::assertInstanceOf(FoundationException::class, $exception);
        self::assertStringContainsString('plop', $exception->getMessage());
    }

    public function testGetAllFor(): void
    {
        $clientHolder = $this->getClientHolder()
            ->add($this->getClientMock('one'))
            ->add($this->getClientMock('two'));

        self::assertEmpty($clientHolder->getAllFor('whatever'));
        self::assertCount(2, $clientHolder->getAllFor('test'));
    }

    private function getClientHolder(): ClientHolder
    {
        return new ClientHolder();
    }

    private function getClientMock(string $identifier, string $type = 'test'): ClientInterface&MockObject
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getClientIdentifier')->willReturn($identifier);
        $client->method('getClientType')->willReturn($type);

        return $client;
    }
}
