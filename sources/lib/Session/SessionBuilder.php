<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */namespace PommProject\Foundation\Session;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\ParameterHolder;
use PommProject\Foundation\Client\ClientHolder;
use PommProject\Foundation\Converter\ConverterHolder;

/**
 * Session factory.
 * This class is responsible for creating and configuring a session. It is a
 * default configuration for session and is dedicated to be overloaded.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class SessionBuilder
{
    protected ParameterHolder $configuration;
    protected ConverterHolder $converterHolder;

    /**
     * Instantiate builder.
     *
     * Mandatory configuration options are:
     * dsn:  connection parameters
     * name: database logical name
     *
     * @param array<string, mixed> $configuration
     * @param ConverterHolder|null $converterHolder
     */
    public function __construct(array $configuration, ConverterHolder $converterHolder = null)
    {
        $this->configuration = new ParameterHolder(
            array_merge(
                $this->getDefaultConfiguration(),
                $configuration
            )
        );
        $converterHolder ??= new ConverterHolder;

        $this->initializeConverterHolder($converterHolder);
        $this->converterHolder = $converterHolder;
    }

    /** Add a configuration parameter. */
    public function addParameter(string $name, mixed $value): SessionBuilder
    {
        $this->configuration->setParameter($name, $value);

        return $this;
    }

    /** Return the converter holder. */
    public function getConverterHolder(): ConverterHolder
    {
        return $this->converterHolder;
    }

    /**
     * Build a new session.
     *
     * @throws FoundationException
     */
    final public function buildSession(string $stamp = null): Session
    {
        $this->preConfigure();
        $dsn = $this
            ->configuration->mustHave('dsn')->getParameter('dsn');

        $connectionConfiguration = $this->configuration
            ->mustHave('connection:configuration')
            ->getParameter('connection:configuration');

        $persist = $this->configuration
            ->mustHave('connection:persist')
            ->getParameter('connection:persist');

        $session = $this->createSession(
            $this->createConnection($dsn, $persist, $connectionConfiguration),
            $this->createClientHolder(),
            $stamp
        );
        $this->postConfigure($session);

        return $session;
    }

    /**
     * This must return the default configuration for new sessions. Default parameters are overrided by the
     * configuration passed as parameter to this builder.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfiguration(): array
    {
        return
            [
                "connection:configuration" =>
                [
                    'bytea_output'  => 'hex',
                    'intervalstyle' => 'ISO_8601',
                    'datestyle'     => 'ISO',
                    'standard_conforming_strings' => 'true',
                    'timezone' => date_default_timezone_get()
                ],
                'connection:persist' => false,
            ];
    }

    /** If any computation to the configuration must be done before each session creation, it goes here. */
    protected function preConfigure(): SessionBuilder
    {
        return $this;
    }

    /**
     * Connection instantiation.
     *
     * @throws ConnectionException
     * @throws FoundationException
     *
     * @param string $dsn
     * @param bool $persist
     * @param array<string, mixed> $connectionConfiguration
     * @return Connection
     */
    protected function createConnection(string $dsn, bool $persist, array $connectionConfiguration): Connection
    {
        return new Connection($dsn, $persist, $connectionConfiguration);
    }

    /** Session instantiation.*/
    protected function createSession(Connection $connection, ClientHolder $clientHolder, ?string $stamp): Session
    {
        $sessionClass = $this->configuration->getParameter('class:session', Session::class);

        return new $sessionClass($connection, $clientHolder, $stamp);
    }

    /** Instantiate ClientHolder. */
    protected function createClientHolder(): ClientHolder
    {
        return new ClientHolder();
    }

    /** Session configuration once created. All pooler registration stuff goes here. */
    protected function postConfigure(Session $session): SessionBuilder
    {
        return $this;
    }

    /** Converter initialization at startup. If new converters are to be registered, it goes here. */
    protected function initializeConverterHolder(ConverterHolder $converterHolder): SessionBuilder
    {
        return $this;
    }
}
