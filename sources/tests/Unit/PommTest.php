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

namespace PommProject\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Pomm;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Session\SessionBuilder;
use PommProject\Foundation\Tests\Fixture\PommTestSession;
use PommProject\Foundation\Tests\Fixture\PommTestSessionBuilder;
use PommProject\Foundation\Tests\Unit\Session\ConnectionConfiguratorTest;

#[CoversClass(Pomm::class)]
class PommTest extends TestCase
{
    public function testConstructor(): void
    {
        $empty = new Pomm([]);
        self::assertInstanceOf(Pomm::class, $empty);
        self::assertSame([], $empty->getSessionBuilders());

        $twoConfigs = new Pomm([
            'first_db_config' => ['dsn' => 'pgsql://user:pass@host:5432/db_name'],
            'second_db_config' => ['dsn' => 'pgsql://user:pass@host:5432/db_name'],
        ]);
        self::assertCount(2, $twoConfigs->getSessionBuilders());

        try {
            new Pomm([
                'db_three' => [
                    'dsn' => 'pgsql://user:pass@host:5432/db_name',
                    'class:session_builder' => '\\Whatever\\Unexistent\\Class',
                ],
            ]);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('Could not instantiate', $e->getMessage());
        }
    }

    public function testAddBuilder(): void
    {
        $pomm = new Pomm([]);

        self::assertInstanceOf(
            Pomm::class,
            $pomm->addBuilder('pika', $this->getSessionBuilder())
        );
        self::assertSame(
            ['pika'],
            array_keys($pomm->addBuilder('pika', $this->getSessionBuilder())->getSessionBuilders())
        );
    }

    public function testGetBuilder(): void
    {
        $pomm = $this->getPomm();

        self::assertInstanceOf(SessionBuilder::class, $pomm->getBuilder('db_one'));
        self::assertInstanceOf(PommTestSessionBuilder::class, $pomm->getBuilder('db_two'));

        try {
            $pomm->getBuilder('whatever');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No such builder', $e->getMessage());
        }
    }

    public function testGetSession(): void
    {
        $pomm = $this->getPomm();

        self::assertInstanceOf(Session::class, $pomm->getSession('db_one'));
        self::assertInstanceOf(PommTestSession::class, $pomm->getSession('db_two'));

        try {
            $pomm->getSession('whatever');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString("{'db_one', 'db_two'}", $e->getMessage());
        }

        self::assertSame(
            ['prepared_query', 'query_manager', 'converter', 'observer', 'inspector', 'listener'],
            $pomm->getSession('db_one')->getRegisterPoolersNames()
        );
    }

    public function testCreateSession(): void
    {
        $pomm = $this->getPomm();

        self::assertInstanceOf(Session::class, $pomm->getSession('db_one'));
        self::assertInstanceOf(PommTestSession::class, $pomm->getSession('db_two'));
    }

    public function testRemoveSession(): void
    {
        $pomm = $this->getPomm();
        $pomm->getSession('db_one');

        self::assertInstanceOf(Pomm::class, $pomm->removeSession('db_one'));
        self::assertFalse($pomm->hasSession('db_one'));
        self::assertInstanceOf(Pomm::class, $pomm->removeSession('db_two'));
        self::assertFalse($pomm->hasSession('db_one'));

        try {
            $pomm->removeSession('unknown');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString("{'db_one', 'db_two'}", $e->getMessage());
        }
    }

    public function testPostConfiguration(): void
    {
        $pomm = $this->getPomm()
            ->addPostConfiguration('db_two', function (Session $session): void {
                $session->getListener('pika');
            });

        self::assertTrue($pomm['db_two']->hasClient('listener', 'pika'));
    }

    public function testDefault(): void
    {
        try {
            new Pomm()->getDefaultSession();
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No default session builder set.', $e->getMessage());
        }

        self::assertInstanceOf(Session::class, $this->getPomm()->getDefaultSession());
        self::assertStringContainsString('db_two', $this->getPomm()->getDefaultSession()->getStamp());

        self::assertStringContainsString(
            'one',
            new Pomm(['one' => ['dsn' => ConnectionConfiguratorTest::DSN_TEST]])->getDefaultSession()->getStamp()
        );

        try {
            $this->getPomm()->setDefaultBuilder('none');
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No such builder', $e->getMessage());
        }

        self::assertStringContainsString(
            'db_one',
            $this->getPomm()->setDefaultBuilder('db_one')->getDefaultSession()->getStamp()
        );
    }

    public function testIsDefault(): void
    {
        $pomm = new Pomm([
            'one' => ['dsn' => ConnectionConfiguratorTest::DSN_TEST],
            'two' => ['dsn' => ConnectionConfiguratorTest::DSN_TEST],
        ]);

        self::assertTrue($pomm->isDefaultSession('one'));
        self::assertFalse($pomm->isDefaultSession('two'));
        self::assertFalse($pomm->isDefaultSession('three'));
    }

    public function testShutdown(): void
    {
        // class:session lets Pomm instantiate an arbitrary Session subclass — we use a
        // spy subclass to observe that shutdown() is actually invoked.
        $pomm = new Pomm([
            'one' => [
                'dsn' => ConnectionConfiguratorTest::DSN_TEST,
                'class:session' => ShutdownSpySession::class,
            ],
            'two' => ['dsn' => ConnectionConfiguratorTest::DSN_TEST],
        ]);
        $spy = $pomm['one'];

        self::assertInstanceOf(ShutdownSpySession::class, $spy);
        self::assertSame(0, $spy::$shutdownCalls);

        self::assertInstanceOf(Pomm::class, $pomm->shutdown());
        self::assertSame(1, $spy::$shutdownCalls);

        self::assertInstanceOf(Pomm::class, $pomm->shutdown(['one', 'two']));

        try {
            $pomm->shutdown(['one', 'four']);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('No such builder', $e->getMessage());
        }

        ShutdownSpySession::$shutdownCalls = 0;
    }

    /**
     * @param array<string, array<string, mixed>>|null $configuration
     */
    private function getPomm(?array $configuration = null): Pomm
    {
        $configuration ??= [
            'db_one' => ['dsn' => 'pgsql://user:pass@host:5432/db_name'],
            'db_two' => [
                'dsn' => 'pgsql://user:pass@host:5432/db_name',
                'class:session_builder' => PommTestSessionBuilder::class,
                'pomm:default' => true,
            ],
        ];

        return new Pomm($configuration);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getSessionBuilder(array $config = []): SessionBuilder
    {
        return new SessionBuilder($config);
    }
}

/**
 * @internal Test helper — do not rely on it outside this file.
 */
final class ShutdownSpySession extends Session
{
    public static int $shutdownCalls = 0;

    public function shutdown(): void
    {
        self::$shutdownCalls++;
        // Skip parent::shutdown() so we don't try to talk to the unreachable DSN.
    }
}
