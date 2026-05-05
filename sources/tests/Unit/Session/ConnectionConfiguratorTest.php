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

namespace PommProject\Foundation\Tests\Unit\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Session\ConnectionConfigurator;

#[CoversClass(ConnectionConfigurator::class)]
class ConnectionConfiguratorTest extends TestCase
{
    public const string DSN_TEST = 'pgsql://user:pass@host:5432/db_name';

    #[TestWith(['azertyuiop'])]
    #[TestWith(['abcde://user:pass/host:1234/dbname'])]
    #[TestWith(['pgsql://toto'])]
    #[TestWith(['pgsql://toto:p4ssW0rD'])]
    #[TestWith(['pgsql://user:p4ssW0rD@a_host:postgres/dbname'])]
    #[TestWith(['pgsql://user/dbname'])]
    #[TestWith(['pgsql://user:@aHost/dbname'])]
    #[TestWith(['pgsql://user:p4ssW0rD@!/var/run/pgsql!:5432/dbname'])]
    #[TestWith(['pgsql://user:p4ssW0rD@!/var/run/pgsql!/dbname'])]
    public function testBadDsn(string $dsn): void
    {
        $this->expectException(ConnectionException::class);
        new ConnectionConfigurator($dsn);
    }

    /**
     * @throws ConnectionException if a DSN in goodDsnProvider regresses into the parser's reject set.
     */
    #[DataProvider('goodDsnProvider')]
    public function testGoodDsn(string $dsn, string $connectionString): void
    {
        self::assertSame(
            $connectionString,
            new ConnectionConfigurator($dsn)->getConnectionString()
        );
    }

    /**
     * @return iterable<int|string, array{0: string, 1: string}>
     */
    public static function goodDsnProvider(): iterable
    {
        yield ['pgsql://user:p4ssW0rD@aHost:5432/dbname', 'user=user dbname=dbname host=aHost port=5432 password=p4ssW0rD'];
        yield ['pgsql://user:p4ssW0rD@aHost/dbname', 'user=user dbname=dbname host=aHost password=p4ssW0rD'];
        yield ['pgsql://user@aHost/dbname', 'user=user dbname=dbname host=aHost'];
        yield 'duplicate of the previous case, preserved for fidelity' => [
            'pgsql://user@aHost/dbname',
            'user=user dbname=dbname host=aHost',
        ];
        yield ['pgsql://user:p4ssW0rD@172.18.210.109:5432/dbname', 'user=user dbname=dbname host=172.18.210.109 port=5432 password=p4ssW0rD'];
        yield ['pgsql://user:p4ssW0rD@172.18.210.109/dbname', 'user=user dbname=dbname host=172.18.210.109 password=p4ssW0rD'];
        yield ['pgsql://user:p%40ssW0rD@aHost/dbname', 'user=user dbname=dbname host=aHost password=p@ssW0rD'];
        yield ['pgsql://user:p%40ssW0rD@aHost:5432/dbname', 'user=user dbname=dbname host=aHost port=5432 password=p@ssW0rD'];
        yield ['pgsql://user:p.ssW0rD@aHost/dbname', 'user=user dbname=dbname host=aHost password=p.ssW0rD'];
        yield ['pgsql://user:p~ssW0rD@aHost/dbname', 'user=user dbname=dbname host=aHost password=p~ssW0rD'];
        yield ['pgsql://user.name:p4ssW0rD@aHost/dbname', 'user=user.name dbname=dbname host=aHost password=p4ssW0rD'];
    }

    /**
     * @throws ConnectionException
     */
    public function testGetConfiguration(): void
    {
        $configurator = new ConnectionConfigurator(self::DSN_TEST);

        self::assertEmpty($configurator->getConfiguration());
    }

    /**
     * @throws ConnectionException
     */
    public function testAddConfiguration(): void
    {
        $configurator = new ConnectionConfigurator(self::DSN_TEST);
        $configuration = ['encoding' => 'utf-8'];

        self::assertInstanceOf(
            ConnectionConfigurator::class,
            $configurator->addConfiguration($configuration)
        );
        self::assertSame($configuration, $configurator->getConfiguration());
    }

    /**
     * @throws ConnectionException
     */
    public function testSet(): void
    {
        $configurator = new ConnectionConfigurator(self::DSN_TEST);

        self::assertInstanceOf(
            ConnectionConfigurator::class,
            $configurator->set('encoding', 'utf-8')
        );
        self::assertSame(['encoding' => 'utf-8'], $configurator->getConfiguration());
    }
}
