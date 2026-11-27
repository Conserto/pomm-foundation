<?php

declare(strict_types=1);

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\QueryManager;

use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\UntypedParameter;
use PommProject\Foundation\Listener\SendNotificationTrait;
use PommProject\Foundation\Session\ResultHandler;

/**
 * Query system as a client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class SimpleQueryManager extends QueryManagerClient
{
    use SendNotificationTrait;
    use QueryParameterParserTrait;

    /** Number of queries whose notices are remembered (the least recently sent is forgotten first). */
    private const int NOTICED_QUERIES = 100;

    /** @var array<string, array<string, true>> notices already given for untyped parameters, by query signature */
    private array $noticed = [];

    /**
     * Perform a simple escaped query and return converted result iterator.
     *
     * @throws FoundationException
     *
     * @param array<int, mixed> $parameters
     * @return ConvertedResultIterator<array<string, mixed>>
     */
    public function query(string $sql, array $parameters = []): ConvertedResultIterator
    {
        $parameters = $this->prepareArguments($sql, $parameters);
        $this->sendNotification(
            'query:pre',
            [
                'sql' => $sql,
                'parameters' => $parameters,
                'session_stamp' => $this->getSession()->getStamp(),
            ]
        );
        $start = microtime(true);
        $resource = $this->doQuery($sql, $parameters);
        $end = microtime(true);

        $iterator = new ConvertedResultIterator($resource, $this->getSession());

        $this->sendNotification(
            'query:post',
            [
                'result_count' => $iterator->count(),
                'time_ms' => sprintf("%03.1f", ($end - $start) * 1000),
            ]
        );

        return $iterator;
    }

    /**
     * Prepare and convert $parameters if needed.
     *
     * Mirrors the conversion step done in PreparedQuery::prepareValues() but
     * deliberately re-resolves the ConverterClient on every call: simple
     * queries are one-shot by design, so caching would just add bookkeeping
     * without payoff. Repeated queries should go through PreparedQuery.
     *
     * @throws FoundationException
     * @param array<int, mixed> $parameters
     * @return array<int, mixed> (values without a type hint are checked by the internal UntypedParameter)
     */
    protected function prepareArguments(string $sql, array $parameters): array
    {
        $types = $this->getParametersType($sql);

        // pgsql sends the values in order, whatever their keys: types are matched by position.
        $position = 0;
        $signature = md5($sql);
        $noticed = $this->noticed[$signature] ?? [];

        try {
            foreach ($parameters as $key => $value) {
                if (isset($types[$position]) && $types[$position] !== '') {
                    /** @var ConverterClient $converterClient */
                    $converterClient = $this
                        ->getSession()
                        ->getClientUsingPooler('converter', $types[$position]);

                    $parameters[$key] = $converterClient->toPgStandardFormat($value, $types[$position]);
                } else {
                    // Noticed once per query, position and value kind: a query may be sent in a loop.
                    $parameters[$key] = UntypedParameter::check($value, $position, $noticed);
                }

                ++$position;
            }
        } finally {
            // Remembered even when a parameter is refused, so a retried query is not noticed again.
            $this->rememberNotices($signature, $noticed);
        }

        return $parameters;
    }

    /** @param array<string, true> $noticed */
    private function rememberNotices(string $signature, array $noticed): void
    {
        if ($noticed === []) {
            return;
        }

        // Moved to the end: the first entry is the least recently sent query.
        unset($this->noticed[$signature]);

        if (count($this->noticed) >= self::NOTICED_QUERIES) {
            array_shift($this->noticed);
        }

        $this->noticed[$signature] = $noticed;
    }

    /**
     * Perform the query
     *
     * @throws FoundationException
     * @param array<int, mixed> $parameters converted values, or checked values when untyped
     */
    protected function doQuery(string $sql, array $parameters): ResultHandler
    {
        return $this
            ->getSession()
            ->getConnection()
            ->sendQueryWithParameters($this->orderParameters($sql), $parameters);
    }
}
