<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Session;

use PommProject\Foundation\Exception\ConnectionException;

class ConnectionConfigurator extends \Atoum
{
    public const string DSN_TEST = 'pgsql://user:pass@host:5432/db_name';

    public function testBadDsn($dsn): void
    {
        $this->exception(function () use ($dsn): void {
                $this->newTestedInstance($dsn);
            })
            ->isInstanceOf(ConnectionException::class);
    }

    protected function testBadDsnDataProvider(): array
    {
        return [
            'azertyuiop',
            'abcde://user:pass/host:1234/dbname',
            'pgsql://toto',
            'pgsql://toto:p4ssW0rD',
            'pgsql://user:p4ssW0rD@a_host:postgres/dbname',
            'pgsql://user/dbname',
            'pgsql://user:@aHost/dbname',
            'pgsql://user:p4ssW0rD@!/var/run/pgsql!:5432/dbname',
            'pgsql://user:p4ssW0rD@!/var/run/pgsql!/dbname',
        ];
    }

    public function testGoodDsn($dsn, $connectionString): void
    {
        $configurator = $this->newTestedInstance($dsn);
        $this->object($configurator)
            ->string($configurator->getConnectionString())
            ->isEqualTo($connectionString);
    }

    protected function testGoodDsnDataProvider(): array
    {
        return [
            [
                'pgsql://user:p4ssW0rD@aHost:5432/dbname',
                'user=user dbname=dbname host=aHost port=5432 password=p4ssW0rD',
            ],
            [
                'pgsql://user:p4ssW0rD@aHost/dbname',
                'user=user dbname=dbname host=aHost password=p4ssW0rD',
            ],
            [
                'pgsql://user@aHost/dbname',
                'user=user dbname=dbname host=aHost',
            ],
            [
                'pgsql://user@aHost/dbname',
                'user=user dbname=dbname host=aHost',
            ],
            [
                'pgsql://user:p4ssW0rD@172.18.210.109:5432/dbname',
                'user=user dbname=dbname host=172.18.210.109 port=5432 password=p4ssW0rD',
            ],
            [
                'pgsql://user:p4ssW0rD@172.18.210.109/dbname',
                'user=user dbname=dbname host=172.18.210.109 password=p4ssW0rD',
            ]
        ];
    }

    public function testGetConfiguration(): void
    {
        $configurator = $this->newTestedInstance(self::DSN_TEST);

        $this->array($configurator->getConfiguration())
            ->isEmpty();
    }

    public function testAddConfiguration(): void
    {
        $configurator = $this->newTestedInstance(self::DSN_TEST);
        $configuration = ['encoding' => 'utf-8'];

        $this->object($configurator->addConfiguration($configuration))
            ->isInstanceOf($this->getTestedClassName());

        $this->array($configurator->getConfiguration())
            ->isIdenticalTo($configuration);
    }

    public function testSet(): void
    {
        $configurator = $this->newTestedInstance(self::DSN_TEST);
        $configuration = ['encoding' => 'utf-8'];

        $this->object($configurator->set('encoding', 'utf-8'))
            ->isInstanceOf($this->getTestedClassName());

        $this->array($configurator->getConfiguration())
            ->isIdenticalTo($configuration);
    }
}
