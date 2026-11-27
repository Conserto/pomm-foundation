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
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\ResultHandler;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
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

        $results = $connection->executeAnonymousQuery('select true; select false; select null');
        self::assertIsArray($results);
        self::assertCount(3, $results);

        $this->expectException(SqlException::class);
        $connection->executeAnonymousQuery('select true; bad query');
    }

    /**
     * The two ways of sending a query with parameters: directly, or as a prepared statement.
     *
     * @return iterable<string, array{\Closure(Connection, string, array<int, mixed>): ResultHandler}>
     */
    public static function parameterizedQuerySenders(): iterable
    {
        yield 'query with parameters' => [self::sendDirect(...)];
        yield 'prepared statement' => [self::sendPrepared(...)];
    }

    /**
     * @param array<int, mixed> $parameters
     * @throws FoundationException|SqlException
     */
    private static function sendDirect(Connection $connection, string $sql, array $parameters): ResultHandler
    {
        return $connection->sendQueryWithParameters($sql, $parameters);
    }

    /**
     * @param array<int, mixed> $parameters
     * @throws FoundationException|SqlException
     */
    private static function sendPrepared(Connection $connection, string $sql, array $parameters): ResultHandler
    {
        $identifier = 'test_' . md5($sql);
        $connection->sendPrepareQuery($identifier, $sql);

        return $connection->sendExecuteQuery($identifier, $parameters, $sql);
    }

    /**
     * A failing query keeps its parameters available, but out of the exception message, which ends up in logs.
     *
     * @param \Closure(Connection, string, array<int, mixed>): ResultHandler $send
     * @throws ConnectionException from getConnection()
     * @throws FoundationException|SqlException from $send on the happy path.
     */
    #[DataProvider('parameterizedQuerySenders')]
    public function testParameterizedQuery(\Closure $send): void
    {
        // true stays a PHP bool: the exception must keep the parameters as supplied.
        $parameters = ['s3cr3t-token', true];
        $connection = $this->getConnection();

        self::assertInstanceOf(
            ResultHandler::class,
            $send($connection, 'select $1::text where $2::bool', $parameters)
        );

        // The division fails at execution, with a message that does not quote the parameters.
        $badQuery = 'select length($1::text) / 0 where $2::bool';
        try {
            $send($connection, $badQuery, $parameters);
            self::fail('Expected SqlException was not thrown.');
        } catch (SqlException $e) {
            self::assertSame(SqlException::DIVISION_BY_ZERO, $e->getSQLErrorState());
            self::assertSame($parameters, $e->getQueryParameters());
            self::assertStringContainsString($badQuery, $e->getQuery());
            self::assertStringNotContainsString('s3cr3t-token', $e->getMessage());
        }
    }

    /**
     * Every SqlException accessor, on a real server error (the file declares strict_types).
     *
     * @throws ConnectionException from getConnection()
     * @throws FoundationException propagated from Connection's constructor
     */
    public function testSqlExceptionAccessors(): void
    {
        try {
            $this->getConnection()->executeAnonymousQuery('select 1 from no_such_table_for_sql_exception');
            self::fail('Expected SqlException was not thrown.');
        } catch (SqlException $e) {
            self::assertSame(SqlException::UNDEFINED_TABLE, $e->getSQLErrorState());
            self::assertSame('ERROR', $e->getSQLErrorSeverity());
            self::assertStringContainsString('no_such_table_for_sql_exception', $e->getSqlErrorMessage(true));
            self::assertStringContainsString('no_such_table_for_sql_exception', $e->getSqlErrorMessage());
            self::assertStringContainsString('no_such_table_for_sql_exception', $e->getSQLDetailedErrorMessage());
            self::assertSame('select 1 from no_such_table_for_sql_exception', $e->getQuery());
            self::assertSame([], $e->getQueryParameters());
            self::assertSame(['x'], $e->setQueryParameters(['x'])->getQueryParameters());
        }
    }

    /**
     * A URL-decoded password may contain spaces: the whole value must be masked in the error message.
     *
     * @throws FoundationException propagated from Connection's constructor
     */
    public function testConnectionErrorMessageMasksPassword(): void
    {
        // Port 1 refuses the connection; the password decodes to 'S3cr et'.
        $connection = new Connection('pgsql://user:S3cr%20et@127.0.0.1:1/dbname', persist: true);

        // pg_connect() raises a PHP warning on failure, on top of the ConnectionException under test.
        set_error_handler(static fn (): bool => true, \E_WARNING);
        try {
            $connection->executeAnonymousQuery('select true');
            self::fail('A ConnectionException was expected.');
        } catch (ConnectionException $e) {
            self::assertSame(
                "Error connecting to the database with parameters 'user=user dbname=dbname host=127.0.0.1 port=1 password=xxxx'.",
                $e->getMessage()
            );
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Invalid UTF-8 cannot be escaped: a ConnectionException instead of a TypeError on the string return type.
     *
     * @throws FoundationException propagated from Connection's constructor
     */
    public function testEscapeInvalidEncodingRaisesConnectionException(): void
    {
        $connection = $this->getConnection();
        $connection->executeAnonymousQuery("set client_encoding to 'UTF8'");

        foreach (['escapeLiteral' => 'Could not escape literal', 'escapeIdentifier' => 'Could not escape identifier'] as $method => $message) {
            // pg_escape_*() raise a PHP warning on failure, on top of the ConnectionException under test.
            set_error_handler(static fn (): bool => true, \E_WARNING);
            try {
                $connection->{$method}("invalid \xff utf-8");
                self::fail(sprintf('A ConnectionException was expected from %s().', $method));
            } catch (ConnectionException $e) {
                self::assertStringStartsWith($message, $e->getMessage());
                self::assertStringNotContainsString("\xff", $e->getMessage());
            } finally {
                restore_error_handler();
            }
        }
    }

    /**
     * @throws ConnectionException if the pgsql extension is missing
     * @throws FoundationException propagated from Connection's constructor
     */
    private function getConnection(): Connection
    {
        return new Connection(
            $this->getDsn(),
            persist: true,
        );
    }

    private function getDsn(): string
    {
        $database = $GLOBALS['pomm_db1'] ?? null;
        self::assertIsArray($database);
        self::assertIsString($database['dsn'] ?? null);

        return $database['dsn'];
    }

    /**
     * Before any query, no connection is open: no transaction and no notification (they used to end in a TypeError).
     *
     * @throws FoundationException propagated from Connection's constructor
     */
    public function testStatusWithoutOpenConnection(): void
    {
        $connection = new Connection($this->getDsn(), persist: true);

        self::assertSame(Connection::CONNECTION_STATUS_NONE, $connection->getConnectionStatus());
        self::assertSame(\PGSQL_TRANSACTION_UNKNOWN, $connection->getTransactionStatus());
        self::assertNull($connection->getNotification());
    }

    /**
     * @throws FoundationException
     */
    public function testFreedResultHandler(): void
    {
        $result = $this->getConnection()->executeAnonymousQuery('select 1');
        self::assertInstanceOf(ResultHandler::class, $result);
        self::assertSame(1, $result->countRows());

        $result->free();
        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage('The query result has already been freed.');
        $result->countRows();
    }
}
