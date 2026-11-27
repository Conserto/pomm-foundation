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
use PommProject\Foundation\ParameterHolder;

/**
 * This class is responsible for configuring the connection.
 * It has to extract information from the DSN and ensure required arguments are present.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ConnectionConfigurator
{
    protected ParameterHolder $configuration;

    /** @throws ConnectionException|FoundationException */
    public function __construct(#[\SensitiveParameter] string $dsn, bool $persist = false)
    {
        $this->configuration = new ParameterHolder(
            [
                'dsn' => $dsn,
                'configuration' => $this->getDefaultConfiguration(),
                'persist' => $persist,
            ]
        );
        $this->parseDsn();
    }

    /**
     * Return whether or not to persist the connection to the DB.
     *
     * @throws FoundationException if the stored setting is not a boolean-like scalar
     */
    public function getPersist(): bool
    {
        return $this->configuration->getBool('persist');
    }

    /**
     * Add configuration settings. If settings exist, they are overridden.
     *
     * @param array<string, mixed> $configuration
     */
    public function addConfiguration(array $configuration): ConnectionConfigurator
    {
        $this ->configuration->setParameter(
            'configuration',
            array_merge(
                $this->configuration->getArray('configuration'),
                $configuration
            )
        );

        return $this;
    }

    /** Set a new configuration setting. */
    public function set(string $name, mixed $value): ConnectionConfigurator
    {
        $configuration = $this->configuration->getParameter('configuration');
        // Offset assignment used to turn null (and false) into an array: keep accepting them.
        $configuration = $configuration === null || $configuration === false ? [] : $this->configuration->getArray('configuration');
        $configuration[$name] = $value;
        $this->configuration
            ->setParameter('configuration', $configuration);

        return $this;
    }

    /**
     * Sets the different parameters from the DSN.
     *
     * @throws ConnectionException|FoundationException
     */
    private function parseDsn(): ConnectionConfigurator
    {
        $this->configuration->mustHave('dsn');
        $dsn = $this->configuration->getString('dsn');
        $parsedDsn = parse_url($dsn);

        if (!preg_match(
            '#^[a-z]+://((?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+)(?::((?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+))?@([a-zA-Z0-9\-_.]+)(?::([0-9]{1,5}))?/([a-zA-Z0-9_\-]+)(\?((?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+=(?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+)(&(?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+=(?:[a-zA-Z0-9\-._~]|%[0-9A-Fa-f]{2})+)*)?$#',
            $dsn
        ) || !$parsedDsn) {
            throw new ConnectionException(sprintf('Could not parse DSN "%s".', $this->redactDsn($dsn)));
        }

        $scheme = $parsedDsn['scheme'] ?? '';

        if ($scheme !== 'pgsql') {
            throw new ConnectionException(
                sprintf(
                    "bad protocol information '%s' in dsn '%s'. Pomm does only support 'pgsql' for now.",
                    $scheme,
                    $this->redactDsn($dsn)
                )
            );
        }

        if (empty($parsedDsn['user'])) {
            throw new ConnectionException(
                sprintf(
                    "No user information in dsn '%s'.",
                    $this->redactDsn($dsn)
                )
            );
        }

        $database = ltrim($parsedDsn['path'] ?? '', '/');

        if (empty($database)) {
            throw new ConnectionException(
                sprintf(
                    "No database name in dsn '%s'.",
                    $this->redactDsn($dsn)
                )
            );
        }

        $this->configuration
            ->setParameter('adapter', $scheme)
            ->setParameter('user', urldecode($parsedDsn['user']))
            ->setParameter('pass', urldecode($parsedDsn['pass'] ?? ''))
            ->setParameter('host', $parsedDsn['host'] ?? '')
            ->setParameter('port', (string) ($parsedDsn['port'] ?? ''))
            ->setParameter('database', $database)
            ->mustHave('user')
            ->mustHave('database');

        return $this;
    }

    /**
     * Mask the password of a DSN so it can safely appear in an exception message.
     *
     * Works on malformed DSNs too (parse_url() is not reliable there): after the scheme, everything between
     * the first ':' of the user part and the last '@' is masked, or up to the end of the string when there is no '@'.
     */
    private function redactDsn(string $dsn): string
    {
        $scheme = preg_match('#^[^:/@\s]+://#', $dsn, $matches) === 1 ? $matches[0] : '';
        $rest = substr($dsn, strlen($scheme));

        if (preg_match('#^([^:/@]*):.*@(.*)$#s', $rest, $matches) === 1) {
            return sprintf('%s%s:****@%s', $scheme, $matches[1], $matches[2]);
        }

        if (preg_match('#^([^:/@]*):#', $rest, $matches) === 1) {
            return sprintf('%s%s:****', $scheme, $matches[1]);
        }

        return $dsn;
    }

    /**
     * Return the connection string.
     *
     * @throws ConnectionException|FoundationException
     */
    public function getConnectionString(): string
    {
        $this->parseDsn();
        $connectParameters = [
            sprintf(
                "user=%s dbname=%s",
                $this->connectionValue($this->configuration->getString('user')),
                $this->connectionValue($this->configuration->getString('database'))
            ),
        ];

        $host = $this->configuration->getString('host');
        if ($host !== '') {
            $connectParameters[] = sprintf('host=%s', $this->connectionValue($host));
        }

        $port = $this->configuration->getString('port');
        if ($port !== '') {
            $connectParameters[] = sprintf('port=%s', $this->connectionValue($port));
        }

        $pass = $this->configuration->getString('pass');
        if ($pass !== '') {
            $connectParameters[] = sprintf('password=%s', $this->connectionValue($pass));
        }

        return implode(' ', $connectParameters);
    }

    /**
     * A value of the connection string, quoted as libpq reads it. A value holding a space, a quote or a backslash
     * used to be written bare: a password with a space broke the connection, and one such as 'x sslmode=disable'
     * added a connection parameter. Other values are written unchanged.
     */
    private function connectionValue(string $value): string
    {
        return preg_match('/^[^\s\'\\\\]+\z/', $value) === 1
            ? $value
            : sprintf("'%s'", addcslashes($value, "'\\"));
    }

    /**
     * Standalone, default configuration.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfiguration(): array
    {
        return [];
    }

    /**
     * Return current configuration settings.
     *
     * @throws ConnectionException
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        $configuration = $this->configuration->getParameter('configuration');

        if (!is_array($configuration)) {
            throw new ConnectionException(
                sprintf(
                    "Invalid configuration. It should be an array of settings, '%s' returned.",
                    gettype($configuration)
                )
            );
        }

        /** @var array<string, mixed> $configuration keys are setting names (built by addConfiguration() / set()) */
        return $configuration;
    }
}
