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
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\ResultHandler;

#[CoversClass(Connection::class)]
class ConnectionTest extends TestCase
{
    /**
     * @throws ConnectionException from getConnection()
     * @throws FoundationException|SqlException propagated from executeAnonymousQuery (the
     *         final statement re-uses expectException so its exception is the caught one).
     */
    public function testExecuteAnonymousQuery(): void
    {
        $connection = $this->getConnection();

        self::assertInstanceOf(
            ResultHandler::class,
            $connection->executeAnonymousQuery('select true')
        );

        try {
            $connection->executeAnonymousQuery('bad query');
            self::fail('Expected SqlException was not thrown.');
        } catch (SqlException $e) {
            self::assertSame(SqlException::SYNTAX_ERROR, $e->getSQLErrorState());
        }

        self::assertCount(3, $connection->executeAnonymousQuery('select true; select false; select null'));

        $this->expectException(SqlException::class);
        $connection->executeAnonymousQuery('select true; bad query');
    }

    /**
     * @throws ConnectionException from getConnection()
     * @throws FoundationException|SqlException from sendQueryWithParameters on the happy path.
     */
    public function testSendQueryWithParameters(): void
    {
        $badQuery = 'select n where true = $1';
        // true is kept unquoted here: Connection::sendQueryWithParameters is expected to
        // coerce scalars to their Postgres text form transparently, and the SqlException
        // path must preserve the raw parameters as supplied (asserted below).
        $parameters = [true];

        $connection = $this->getConnection();

        self::assertInstanceOf(
            ResultHandler::class,
            $connection->sendQueryWithParameters('select true where true = $1', $parameters)
        );

        try {
            $connection->sendQueryWithParameters($badQuery, $parameters);
            self::fail('Expected SqlException was not thrown.');
        } catch (SqlException $e) {
            self::assertSame(SqlException::UNDEFINED_COLUMN, $e->getSQLErrorState());
            self::assertSame($parameters, $e->getQueryParameters());
            self::assertSame($badQuery, $e->getQuery());
        }
    }

    /**
     * @throws ConnectionException if the pgsql extension is missing
     * @throws FoundationException propagated from Connection's constructor
     */
    private function getConnection(): Connection
    {
        return new Connection(
            $GLOBALS['pomm_db1']['dsn'],
            persist: true,
        );
    }
}
