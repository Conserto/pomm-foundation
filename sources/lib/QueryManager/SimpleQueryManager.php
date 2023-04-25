<?php
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

    /**
     * Perform a simple escaped query and return converted result iterator.
     *
     * @throws FoundationException
     *
     * @param string $sql
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
     * @throws FoundationException
     * @param string $sql
     * @param array<int, mixed> $parameters
     * @return array<int, string>
     */
    protected function prepareArguments(string $sql, array $parameters): array
    {
        $types = $this->getParametersType($sql);

        foreach ($parameters as $index => $value) {
            if (isset($types[$index]) && $types[$index] !== '') {
                /** @var ConverterClient $converterClient */
                $converterClient = $this
                    ->getSession()
                    ->getClientUsingPooler('converter', $types[$index]);

                $parameters[$index] = $converterClient->toPgStandardFormat($value, $types[$index]);
            }
        }

        return $parameters;
    }

    /**
     * Perform the query
     *
     * @throws FoundationException
     * @param string $sql
     * @param array<int, string> $parameters
     * @return ResultHandler
     */
    protected function doQuery(string $sql, array $parameters): ResultHandler
    {
        return $this
            ->getSession()
            ->getConnection()
            ->sendQueryWithParameters($this->orderParameters($sql), $parameters);
    }
}
