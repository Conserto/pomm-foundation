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

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\ParameterHolder;
use PommProject\Foundation\Exception\ConnectionException;

/**
 * ConnectionConfigurator
 *
 * This class is responsible of configuring the connection.
 * It has to extract information from the DSN and ensure required arguments
 * are present.
 *
 * @package     Foundation
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ConnectionConfigurator
{
    protected ParameterHolder $configuration;

    /**
     * Initialize configuration.
     *
     * @throws ConnectionException|FoundationException
     */
    public function __construct(string $dsn, bool $persist = false)
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
     */
    public function getPersist(): bool
    {
        return $this->configuration['persist'];
    }

    /**
     * Add configuration settings. If settings exist, they are overridden.
     */
    public function addConfiguration(array $configuration): ConnectionConfigurator
    {
        $this ->configuration->setParameter(
            'configuration',
            array_merge(
                $this->configuration->getParameter('configuration'),
                $configuration
            )
        );

        return $this;
    }

    /**
     * Set a new configuration setting.
     */
    public function set(string $name, mixed $value): ConnectionConfigurator
    {
        $configuration = $this->configuration->getParameter('configuration');
        $configuration[$name] = $value;
        $this
            ->configuration
            ->setParameter(
                'configuration',
                $configuration
            );

        return $this;
    }


    /**
     * Sets the different parameters from the DSN.
     *
     * @throws ConnectionException|FoundationException
     */
    private function parseDsn(): ConnectionConfigurator
    {
        $dsn = $this->configuration->mustHave('dsn')->getParameter('dsn');
        if (!preg_match(
            '#([a-z]+)://([^:@]+)(?::([^@]*))?(?:@([\w\.-]+|!/.+[^/]!)(?::(\w+))?)?/(.+)#',
            (string) $dsn,
            $matches
        )) {
            throw new ConnectionException(sprintf('Could not parse DSN "%s".', $dsn));
        }

        if ($matches[1] !== 'pgsql') {
            throw new ConnectionException(
                sprintf(
                    "bad protocol information '%s' in dsn '%s'. Pomm does only support 'pgsql' for now.",
                    $matches[1],
                    $dsn
                )
            );
        }

        $adapter = $matches[1];

        if ($matches[2] === null) {
            throw new ConnectionException(
                sprintf(
                    "No user information in dsn '%s'.",
                    $dsn
                )
            );
        }

        $user = $matches[2];
        $pass = $matches[3];

        if (preg_match('/!(.*)!/', (string) $matches[4], $host_matches)) {
            $host = $host_matches[1];
        } else {
            $host = $matches[4];
        }

        $port = $matches[5];

        if ($matches[6] === null) {
            throw new ConnectionException(
                sprintf(
                    "No database name in dsn '%s'.",
                    $dsn
                )
            );
        }

        $database = $matches[6];
        $this->configuration
            ->setParameter('adapter', $adapter)
            ->setParameter('user', $user)
            ->setParameter('pass', $pass)
            ->setParameter('host', $host)
            ->setParameter('port', $port)
            ->setParameter('database', $database)
            ->mustHave('user')
            ->mustHave('database')
            ;

        return $this;
    }

    /**
     * Return the connection string.
     *
     * @throws ConnectionException|FoundationException
     */
    public function getConnectionString(): string
    {
        $this->parseDsn();
        $connect_parameters = [
            sprintf(
                "user=%s dbname=%s",
                $this->configuration['user'],
                $this->configuration['database']
            )
        ];

        if ($this->configuration['host'] !== '') {
            $connect_parameters[] = sprintf('host=%s', $this->configuration['host']);
        }

        if ($this->configuration['port'] !== '') {
            $connect_parameters[] = sprintf('port=%s', $this->configuration['port']);
        }

        if ($this->configuration['pass'] !== '') {
            $connect_parameters[] = sprintf('password=%s', addslashes($this->configuration['pass']));
        }

        return join(' ', $connect_parameters);
    }

    /**
     * Standalone, default configuration.
     */
    protected function getDefaultConfiguration(): array
    {
        return [];
    }

    /**
     * Return current configuration settings.
     *
     * @throws ConnectionException
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

        return $configuration;
    }
}
