<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Session;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PgSql\Connection as PgSqlConnection;

/**
 * Manage connection through a resource handler.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Connection
{
    final const CONNECTION_STATUS_NONE    = 0;
    final const CONNECTION_STATUS_GOOD    = 1;
    final const CONNECTION_STATUS_BAD     = 2;
    final const CONNECTION_STATUS_CLOSED  = 3;
    final const ISOLATION_READ_COMMITTED  = "READ COMMITTED";  // default
    final const ISOLATION_REPEATABLE_READ = "REPEATABLE READ"; // from Pg 9.1
    final const ISOLATION_SERIALIZABLE    = "SERIALIZABLE";    // changes in 9.1
    final const CONSTRAINTS_DEFERRED      = "DEFERRED";
    final const CONSTRAINTS_IMMEDIATE     = "IMMEDIATE";       // default
    final const ACCESS_MODE_READ_ONLY     = "READ ONLY";
    final const ACCESS_MODE_READ_WRITE    = "READ WRITE";      // default

    protected ?PgSqlConnection $handler = null;
    protected ConnectionConfigurator $configurator;
    private bool $isClosed = false;

    /**
     * @throws ConnectionException if pgsql extension is missing
     * @throws FoundationException
     *
     * @param string $dsn
     * @param bool $persist
     * @param array<string,mixed> $configuration
     */
    public function __construct(string $dsn, bool $persist = false, array $configuration = [])
    {
        if (!function_exists('pg_connection_status')) {
            throw new ConnectionException(
                "`pgsql` PHP extension's functions are unavailable in your environment, please make sure ".
                "PostgreSQL support is enabled in PHP."
            );
        }

        $this->configurator = new ConnectionConfigurator($dsn, $persist);
        $this->configurator->addConfiguration($configuration);
    }

    /** Close the connection if any. */
    public function close(): Connection
    {
        if ($this->hasHandler()) {
            pg_close($this->handler);
            $this->handler = null;
            $this->isClosed = true;
        }

        return $this;
    }

    /**
     * Add configuration settings. If settings exist, they are overridden.
     *
     * @throws  ConnectionException if connection is already up.
     * @param array<string,mixed> $configuration
     */
    public function addConfiguration(array $configuration): Connection
    {
        $this
            ->checkConnectionUp("Cannot update configuration once the connection is open.")
            ->configurator->addConfiguration($configuration);

        return $this;
    }

    /**
     * Add or override a configuration definition.
     *
     * @throws ConnectionException
     */
    public function addConfigurationSetting(string $name, string $value): Connection
    {
        $this->checkConnectionUp("Cannot set configuration once a connection is made with the server.")
            ->configurator->set($name, $value);

        return $this;
    }

    /**
     * Return the connection handler. If no connection are open, it opens one.
     *
     * @throws  ConnectionException|FoundationException if connection is open in a bad state.
     */
    protected function getHandler(): PgSqlConnection
    {
        switch ($this->getConnectionStatus()) {
            case static::CONNECTION_STATUS_NONE:
                $this->launch();
                // no break
            case static::CONNECTION_STATUS_GOOD:
                return $this->handler;
            case static::CONNECTION_STATUS_BAD:
                throw new ConnectionException(
                    "Connection problem. Read your server's log about this, I have no more informations."
                );
            case static::CONNECTION_STATUS_CLOSED:
                throw new ConnectionException(
                    "Connection has been closed, no further queries can be sent."
                );
            default:
                throw new ConnectionException(
                    "Connection has unexpected status."
                );
        }
    }

    /** Tell if a handler is set or not. */
    protected function hasHandler(): bool
    {
        return $this->handler !== null;
    }

    /** Return a connection status. */
    public function getConnectionStatus(): int
    {
        if (!$this->hasHandler()) {
            return $this->isClosed ? static::CONNECTION_STATUS_CLOSED : static::CONNECTION_STATUS_NONE;
        }

        if (@pg_connection_status($this->handler) === \PGSQL_CONNECTION_OK) {
            return static::CONNECTION_STATUS_GOOD;
        }

        return static::CONNECTION_STATUS_BAD;
    }

    /**
     * Return the current transaction status.
     * Return a PHP constant.
     * @see http://fr2.php.net/manual/en/function.pg-transaction-status.php
     */
    public function getTransactionStatus(): int
    {
        return pg_transaction_status($this->handler);
    }

    /**
     * Open a connection on the database.
     *
     * @throws  ConnectionException|FoundationException if connection fails.
     */
    private function launch(): Connection
    {
        $string = $this->configurator->getConnectionString();
        $persist = $this->configurator->getPersist();

        if ($persist) {
            $handler = pg_connect($string, \PGSQL_CONNECT_FORCE_NEW);
        } else {
            $handler = pg_pconnect($string);
        }

        if ($handler === false) {
            throw new ConnectionException(
                sprintf(
                    "Error connecting to the database with parameters '%s'.",
                    preg_replace('/password=[^ ]+/', 'password=xxxx', $string)
                )
            );
        } else {
            $this->handler = $handler;
        }

        if ($this->getConnectionStatus() !== static::CONNECTION_STATUS_GOOD) {
            throw new ConnectionException(
                "Connection open but in a bad state. Read your database server log to learn more about this."
            );
        }

        $this->sendConfiguration();

        return $this;
    }

    /**
     * Send the configuration settings to the server.
     *
     * @throws ConnectionException|FoundationException
     */
    protected function sendConfiguration(): Connection
    {
        $sql=[];

        if( $this->handler === null)
        {
            throw new ConnectionException("Handler must be set before sendConfiguration call.");
        }

        foreach ($this->configurator->getConfiguration() as $setting => $value) {
            $sql[] = sprintf(
                "set %s = %s",
                pg_escape_identifier($this->handler, $setting),
                pg_escape_literal($this->handler, $value)
            );
        }

        if (!empty($sql)) {
            $this->testQuery(
                pg_query($this->getHandler(), join('; ', $sql)),
                sprintf("Error while applying settings '%s'.", join('; ', $sql))
            );
        }

        return $this;
    }

    /**
     * Check if the handler is set and throw an Exception if yes.
     *
     * @throws ConnectionException
     */
    private function checkConnectionUp(string $errorMessage = ''): Connection
    {
        if ($this->hasHandler()) {
            if ($errorMessage === '') {
                $errorMessage = "Connection is already made with the server";
            }

            throw new ConnectionException($errorMessage);
        }

        return $this;
    }

    /**
     * Performs a raw SQL query
     *
     * @throws ConnectionException|SqlException|FoundationException
     *
     * @return ResultHandler|array<int,ResultHandler>
     */
    public function executeAnonymousQuery(string $sql): ResultHandler|array
    {
        $ret = pg_send_query($this->getHandler(), $sql);

        return $this
            ->testQuery($ret, sprintf("Anonymous query failed '%s'.", $sql))
            ->getQueryResult($sql);
    }

    /**
     * Get an asynchronous query result.
     * The only reason for the SQL query to be passed as parameter is to throw a meaningful exception when an error is
     * raised.
     * Since it is possible to send several queries at a time, This method can return an array of ResultHandler.
     *
     * @throws ConnectionException if no response are available.
     * @throws FoundationException if the result is an error.
     * @throws SqlException if the result is an error.
     *
     * @param string|null $sql
     * @return ResultHandler|array<int,ResultHandler>
     */
    protected function getQueryResult(string $sql = null): ResultHandler|array
    {
        $results = [];

        while ($result = pg_get_result($this->getHandler())) {
            $status = pg_result_status($result);

            if ($status !== \PGSQL_COMMAND_OK && $status !== \PGSQL_TUPLES_OK) {
                throw new SqlException($result, $sql);
            }

            $results[] = new ResultHandler($result);
        }

        if (empty($results)) {
            throw new ConnectionException(
                sprintf("There are no waiting results in connection.\nQuery = '%s'.", $sql)
            );
        }

        return count($results) === 1 ? $results[0] : $results;
    }

    /**
     * Escape database object's names. This is different from value escaping as objects names are surrounded by double
     * quotes. API function does provide a nice escaping with -- hopefully -- UTF8 support.
     *
     * @see http://www.postgresql.org/docs/current/static/sql-syntax-lexical.html
     * @throws ConnectionException|FoundationException
     */
    public function escapeIdentifier(?string $string): string
    {
        return \pg_escape_identifier($this->getHandler(), $string ?? '');
    }

    /**
     * Escape a text value.
     *
     * @throws ConnectionException|FoundationException
     */
    public function escapeLiteral(?string $string): string
    {
        return \pg_escape_literal($this->getHandler(), $string ?? '');
    }

    /**
     * Wrap pg_escape_bytea
     *
     * @throws ConnectionException|FoundationException
     */
    public function escapeBytea(?string $word): string
    {
        return pg_escape_bytea($this->getHandler(), $word ?? '');
    }

    /** Unescape PostgreSQL bytea. */
    public function unescapeBytea(string $bytea): string
    {
        return pg_unescape_bytea($bytea);
    }

    /**
     * Execute an asynchronous query with parameters and send the results.
     *
     * @param string $query
     * @param  array<int, string> $parameters
     * @return ResultHandler query result wrapper
     * @throws SqlException|ConnectionException|FoundationException
     */
    public function sendQueryWithParameters(string $query, array $parameters = []): ResultHandler
    {
        $res = pg_send_query_params($this->getHandler(), $query, $parameters);

        try {
            return $this->testQuery($res, $query)
                ->getQueryResult($query);
        } catch (SqlException $e) {
            throw $e->setQueryParameters($parameters);
        }
    }

    /**
     * Send a prepare query statement to the server.
     *
     * @throws ConnectionException|SqlException|FoundationException
     */
    public function sendPrepareQuery(string $identifier, string $sql): Connection
    {
        $this
            ->testQuery(
                pg_send_prepare($this->getHandler(), $identifier, $sql),
                sprintf("Could not send prepare statement «%s».", $sql)
            )
            ->getQueryResult(sprintf("PREPARE ===\n%s\n ===", $sql));

        return $this;
    }

    /**
     * Factor method to test query return and summon getQueryResult().
     *
     * @throws ConnectionException
     */
    protected function testQuery(mixed $queryReturn, string $sql): Connection
    {
        if ($queryReturn === false) {
            throw new ConnectionException(sprintf("Query Error : '%s'.", $sql));
        }

        return $this;
    }

    /**
     * Execute a prepared statement. The optional SQL parameter is for debugging purposes only.
     *
     * @throws ConnectionException
     * @throws FoundationException
     * @throws SqlException
     *
     * @param string $identifier
     * @param array<int, mixed> $parameters
     * @param string $sql
     *
     * @return ResultHandler
     */
    public function sendExecuteQuery(string $identifier, array $parameters = [], string $sql = ''): ResultHandler
    {
        $ret = pg_send_execute($this->getHandler(), $identifier, $parameters);

        /** @var ResultHandler $result */
        $result = $this
            ->testQuery($ret, sprintf("Prepared query '%s'.", $identifier))
            ->getQueryResult(
                sprintf("EXECUTE ===\n%s\n ===\nparameters = {%s}", $sql, join(', ', $parameters))
            );

        return $result;
    }

    /**
     * Return the actual client encoding.
     *
     * @throws ConnectionException|FoundationException
     */
    public function getClientEncoding(): string
    {
        $encoding = pg_client_encoding($this->getHandler());
        $this->testQuery($encoding, 'get client encoding');

        return $encoding;
    }

    /**
     * Set client encoding.
     *
     * @throws ConnectionException|FoundationException
     */
    public function setClientEncoding(string $encoding): Connection
    {
        $result = pg_set_client_encoding($this->getHandler(), $encoding);

        return $this->testQuery($result != -1, sprintf("Set client encoding to '%s'.", $encoding));
    }

    /**
     * Get pending notifications. If no notifications are waiting, NULL is returned.
     * Otherwise, an associative array containing the optional data and de backend's PID is returned.
     *
     * @return array{message: string, pid: int, payload: string}|null
     */
    public function getNotification(): ?array
    {
        $data = pg_get_notify($this->handler, \PGSQL_ASSOC);

        return $data === false ? null : $data;
    }
}
