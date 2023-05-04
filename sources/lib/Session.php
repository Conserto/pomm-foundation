<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation;

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Inspector\Inspector;
use PommProject\Foundation\Listener\Listener;
use PommProject\Foundation\Observer\Observer;
use PommProject\Foundation\PreparedQuery\PreparedQuery;
use PommProject\Foundation\QueryManager\QueryManagerClient;
use PommProject\Foundation\Session\Session as VanillaSession;

/**
 * Session with Foundation poolers API.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         VanillaSession
 */
class Session extends VanillaSession
{
    /**
     * Return the prepared query client.
     *
     * @throws Exception\FoundationException
     */
    public function getPreparedQuery(string $query): PreparedQuery
    {
        /** @var PreparedQuery $preparedQuery */
        $preparedQuery = $this->getClientUsingPooler('prepared_query', $query);
        return $preparedQuery;
    }

    /**
     * Return a query manager (default to QueryManager\SimpleQueryManager)
     *
     * @throws Exception\FoundationException
     */
    public function getQueryManager(?string $queryManager = null): QueryManagerClient
    {
        /** @var QueryManagerClient $queryManagerClient */
        $queryManagerClient = $this->getClientUsingPooler('query_manager', $queryManager);
        return $queryManagerClient;
    }

    /**
     * Return a converter client.
     *
     * @throws Exception\FoundationException
     */
    public function getConverter(string $name): ConverterClient
    {
        /** @var ConverterClient $converterClient */
        $converterClient = $this->getClientUsingPooler('converter', $name);
        return $converterClient;
    }

    /**
     * Return an observer client.
     *
     * @throws Exception\FoundationException
     */
    public function getObserver(string $name): Observer
    {
        /** @var Observer $observer */
        $observer = $this->getClientUsingPooler('observer', $name);
        return $observer;
    }

    /**
     * Return the database inspector.
     *
     * @throws Exception\FoundationException
     */
    public function getInspector(?string $name = null): Inspector
    {
        /** @var Inspector $inspector */
        $inspector = $this->getClientUsingPooler('inspector', $name);
        return $inspector;
    }

    /** @throws Exception\FoundationException */
    public function getListener(string $name): Listener
    {
        /** @var Listener $listener */
        $listener = $this->getClientUsingPooler('listener', $name);
        return $listener;
    }
}
