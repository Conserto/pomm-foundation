<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\PreparedQuery;

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Internal\UntypedParameter;
use PommProject\Foundation\Listener\SendNotificationTrait;
use PommProject\Foundation\QueryManager\QueryParameterParserTrait;
use PommProject\Foundation\Session\ResultHandler;

/**
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class PreparedQuery extends Client
{
    use QueryParameterParserTrait;
    use SendNotificationTrait;

    protected string $sql;
    private PreparationEnum $preparationStatus = PreparationEnum::NOT_PREPARED;
    private string $identifier;
    /** @var array<int, ?callable>|null  */
    private ?array $converters = null;

    /** @var array<string, true> notices already given for untyped parameters, by position and value kind */
    private array $noticed = [];

    /** Returns a hash for a given sql query. */
    public static function getSignatureFor(string $sql): string
    {
        return md5($sql);
    }

    /**
     * Build the prepared query.
     *
     * @throws FoundationException
     */
    public function __construct(?string $sql)
    {
        if (empty($sql)) {
            throw new FoundationException("Can not prepare an empty query.");
        }

        $this->sql        = $sql;
        $this->identifier = static::getSignatureFor($sql);
    }

    /** @see ClientPoolerInterface */
    public function getClientType(): string
    {
        return 'prepared_query';
    }

    /** Return the query identifier. */
    public function getClientIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Deallocate the statement in the database.
     *
     * @throws FoundationException
     * @see ClientInterface
     */
    public function shutdown(): void
    {
        if ($this->preparationStatus === PreparationEnum::PREPARED) {
            $this
                ->getSession()
                ->getConnection()
                ->executeAnonymousQuery(sprintf(
                    "deallocate %s",
                    $this->getSession()->getConnection()->escapeIdentifier($this->getClientIdentifier())
                ));

            $this->preparationStatus = PreparationEnum::NOT_PREPARED;
        }
    }

    /**
     * Launch the query with the given parameters.
     *
     * @param array<int, mixed> $values Query parameters
     * @throws ConnectionException|FoundationException|SqlException
     */
    public function execute(array $values = []): ResultHandler
    {
        if ($this->preparationStatus === PreparationEnum::NOT_PREPARED) {
            $this->prepare();
        }

        $values = $this->prepareValues($this->sql, $values);
        $this->sendNotification(
            'query:pre',
            [
                'sql'           => $this->sql,
                'parameters'    => $values,
                'session_stamp' => $this->getSession()->getStamp(),
            ]
        );

        $start    = microtime(true);
        $result = $this
            ->getSession()
            ->getConnection()
            ->sendExecuteQuery(
                $this->getClientIdentifier(),
                $values,
                $this->sql
            );
        $end      = microtime(true);
        $this->sendNotification(
            'query:post',
            [
                'result_count' => $result->countRows(),
                'time_ms'      => sprintf("%03.1f", ($end - $start) * 1000),
            ]
        );

        return $result;
    }

    /**
     * Send the query to be prepared by the server.
     * @throws FoundationException
     * @throws SqlException if the result is an error.
     */
    protected function prepare(): PreparedQuery
    {
        $this->preparationStatus = PreparationEnum::IN_PREPARATION;

        $this
            ->getSession()
            ->getConnection()
            ->sendPrepareQuery(
                $this->getClientIdentifier(),
                $this->orderParameters($this->sql)
            );

        $this->preparationStatus = PreparationEnum::PREPARED;

        return $this;
    }

    /** Get the original SQL query */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Prepare parameters to be sent.
     *
     * The per-index converter closures are built once on the first call via
     * {@see prepareConverters()} and reused for every subsequent execute.
     * SimpleQueryManager::prepareArguments() does the same transformation
     * without caching because it targets one-shot queries.
     *
     * @param array<int, mixed> $values
     * @return array<int, mixed> $prepared_values (untyped values are checked by the internal UntypedParameter)
     * @throws FoundationException
     */
    protected function prepareValues(mixed $sql, array $values): array
    {
        if ($this->converters === null) {
            $this->prepareConverters($sql);
        }

        // pgsql sends the values in order, whatever their keys: converters are matched by position.
        $position = 0;

        foreach ($values as $key => $value) {
            if (isset($this->converters[$position])) {
                $values[$key] = ($this->converters[$position])($value);
            } else {
                // Noticed once per position and value kind: a prepared query may be executed for many rows.
                $values[$key] = UntypedParameter::check($value, $position, $this->noticed);
            }

            ++$position;
        }

        return $values;
    }

    /**
     * Store converters needed for the query parameters.
     *
     * @throws FoundationException
     */
    protected function prepareConverters(mixed $sql): PreparedQuery
    {
        // Built aside and stored once complete: a type without converter leaves the cache unset, so the next execute
        // tries again instead of sending the remaining parameters unconverted. Stored even when empty (no $*
        // parameter), so the SQL is parsed once.
        $converters = [];

        foreach ($this->getParametersType($sql) as $index => $type) {
            if ($type === '') {
                $converters[$index] = null;
            } else {
                /** @var ConverterClient $converterClient */
                $converterClient = $this
                    ->getSession()
                    ->getClientUsingPooler('converter', $type);

                $converter = $converterClient->getConverter();

                $converters[$index] =
                    fn ($value) => $converter->toPgStandardFormat($value, $type, $this->getSession());
            }
        }

        $this->converters = $converters;

        return $this;
    }
}
