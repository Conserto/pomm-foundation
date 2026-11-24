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

use PommProject\Foundation\Client\ClientHolder;
use PommProject\Foundation\Converter\ConverterHolder;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\ParameterHolder;

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
     */
    public function __construct(#[\SensitiveParameter] array $configuration, ?ConverterHolder $converterHolder = null)
    {
        $this->configuration = new ParameterHolder(
            // @phpstan-ignore argument.type (user configuration: ParameterHolder accessors convert the values, as they always did)
            array_merge(
                $this->getDefaultConfiguration(),
                $configuration
            )
        );
        $converterHolder ??= new ConverterHolder();

        $this->initializeConverterHolder($converterHolder);
        $this->converterHolder = $converterHolder;
    }

    /** Add a configuration parameter. */
    public function addParameter(string $name, #[\SensitiveParameter] mixed $value): SessionBuilder
    {
        // @phpstan-ignore argument.type (user configuration: ParameterHolder accessors convert the values, as they always did)
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
    final public function buildSession(?string $stamp = null): Session
    {
        $this->preConfigure();
        $this->configuration
            ->mustHave('dsn')
            ->mustHave('connection:configuration')
            ->mustHave('connection:persist');

        // Same order as createConnection()'s parameters, so the first invalid setting reported is unchanged.
        $dsn = $this->getStringSetting('dsn');
        $persist = $this->getBoolSetting('connection:persist');
        $connectionConfiguration = $this->getArraySetting('connection:configuration');

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
                    'timezone' => date_default_timezone_get(),
                ],
                'connection:persist' => false,
            ];
    }

    /**
     * If any computation to the configuration must be done before each session creation, it goes here.
     *
     * @phpstan-impure extension hook: overrides have side effects, calling parent::preConfigure() is meaningful
     */
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
     * @param array<string, mixed> $connectionConfiguration
     */
    protected function createConnection(#[\SensitiveParameter] string $dsn, bool $persist, array $connectionConfiguration): Connection
    {
        return new Connection($dsn, $persist, $connectionConfiguration);
    }

    /** Session instantiation.*/
    protected function createSession(Connection $connection, ClientHolder $clientHolder, ?string $stamp): Session
    {
        $sessionClass = $this->configuration->getParameter('class:session', Session::class);

        if (!is_string($sessionClass)) {
            // Instantiating a non string used to be an Error.
            throw new FoundationException(
                sprintf("The 'class:session' setting must be a class name, '%s' given.", get_debug_type($sessionClass))
            );
        }

        $session = new $sessionClass($connection, $clientHolder, $stamp);

        if (!$session instanceof Session) {
            // Used to be a TypeError on the return type.
            throw new FoundationException(
                sprintf("The 'class:session' setting must name a %s subclass, '%s' given.", Session::class, $sessionClass)
            );
        }

        return $session;
    }

    /** Instantiate ClientHolder. */
    protected function createClientHolder(): ClientHolder
    {
        return new ClientHolder();
    }

    /**
     * Session configuration once created. All pooler registration stuff goes here.
     *
     * @phpstan-impure extension hook: overrides have side effects, calling parent::postConfigure() is meaningful
     */
    protected function postConfigure(Session $session): SessionBuilder
    {
        return $this;
    }

    /**
     * Converter initialization at startup. If new converters are to be registered, it goes here.
     *
     * @phpstan-impure extension hook: overrides have side effects, calling parent::initializeConverterHolder() is meaningful
     */
    protected function initializeConverterHolder(ConverterHolder $converterHolder): SessionBuilder
    {
        return $this;
    }

    /**
     * Typed reads of the configuration. Scalars are converted exactly as PHP's coercive typing mode (this file does
     * not declare strict_types) converted them when they reached createConnection(); only values that already failed
     * there with a TypeError (null, array, object) now raise a FoundationException instead.
     *
     * @throws FoundationException
     */
    private function getStringSetting(string $name): string
    {
        $value = $this->configuration->getParameter($name);

        if (!is_scalar($value)) {
            throw new FoundationException(
                sprintf("Invalid '%s' setting: a string is expected, '%s' given.", $name, get_debug_type($value))
            );
        }

        return (string) $value;
    }

    /** @throws FoundationException */
    private function getBoolSetting(string $name): bool
    {
        $value = $this->configuration->getParameter($name);

        if (!is_scalar($value)) {
            throw new FoundationException(
                sprintf("Invalid '%s' setting: a boolean is expected, '%s' given.", $name, get_debug_type($value))
            );
        }

        return (bool) $value;
    }

    /**
     * @throws FoundationException
     * @return array<string, mixed>
     */
    private function getArraySetting(string $name): array
    {
        $value = $this->configuration->getParameter($name);

        if (!is_array($value)) {
            throw new FoundationException(
                sprintf("Invalid '%s' setting: an array is expected, '%s' given.", $name, get_debug_type($value))
            );
        }

        /** @var array<string, mixed> $value keys are setting names */
        return $value;
    }
}
