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

use Atoum;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;

class Connection extends Atoum
{
    public function testExecuteAnonymousQuery(): void
    {
        $connection = $this->getConnection($this->getDsn());
        $this->object($connection->executeAnonymousQuery('select true'))
            ->isInstanceOf(\PommProject\Foundation\Session\ResultHandler::class)
            ->exception(function () use ($connection) {
                $connection->executeAnonymousQuery('bad query');
            })
            ->isInstanceOf(SqlException::class)
            ->string($this->exception->getSQLErrorState())
            ->isIdenticalTo(SqlException::SYNTAX_ERROR)
            ->array($connection->executeAnonymousQuery('select true; select false; select null'))
            ->hasSize(3)
            ->exception(function () use ($connection) {
                $connection->executeAnonymousQuery('select true; bad query');
            })
            ->isInstanceOf(SqlException::class);
    }

    protected function getConnection($dsn)
    {
        return $this->newTestedInstance($dsn);
    }

    protected function getDsn()
    {
        $var = $GLOBALS['pomm_db1'];

        return $var['dsn'];
    }

    /** @throws FoundationException */
    public function testSendQueryWithParameters(): void
    {
        $bad_query = 'select n where true = $1';
        $parameters = [true];

        $connection = $this->getConnection($this->getDsn());
        $this->object($connection->sendQueryWithParameters('select true where true = $1', $parameters))
            ->isInstanceOf(\PommProject\Foundation\Session\ResultHandler::class)
            ->exception(function () use ($connection, $bad_query, $parameters) {
                $connection->sendQueryWithParameters($bad_query, $parameters);
            })
            ->isInstanceOf(SqlException::class)
            ->string($this->exception->getSQLErrorState())
            ->isIdenticalTo(SqlException::UNDEFINED_COLUMN)
            ->and
            ->array($this->exception->getQueryParameters())
            ->isIdenticalTo($parameters)
            ->and
            ->string($this->exception->getQuery())
            ->isIdenticalTo($bad_query);
    }
}
