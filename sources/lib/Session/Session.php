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
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Inflector;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Public API to share the database connection. IO are shared amongst clients which are stored in a ClientHolder.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         LoggerAwareInterface
 */
class Session implements LoggerAwareInterface
{
    /** @var array<string, ClientPoolerInterface> */
    protected array $clientPoolers = [];
    protected bool $isShutdown = false;

    use LoggerAwareTrait;

    /**
     * In order to create a physical connection to the database, it requires a 'dsn' parameter from the ParameterHolder.
     */
    public function __construct(
        protected Connection $connection,
        protected ?ClientHolder $clientHolder = null,
        protected ?string $stamp = null
    ) {
        $this->clientHolder = $clientHolder ?? new ClientHolder;
    }

    /** A short description here */
    public function __destruct()
    {
        if (!$this->isShutdown) {
            $this->shutdown();
        }
    }

    /** Gently shutdown all clients when the Session is getting down prior to connection termination. */
    public function shutdown(): void
    {
        $exceptions = $this->clientHolder->shutdown();

        if ($this->hasLogger()) {
            foreach ($exceptions as $exception) {
                printf("Exception caught during shutdown: %s\n", $exception);
            }

            $this->logger = null;
        }

        $this->clientPoolers = [];
        $this->connection->close();
        $this->isShutdown = true;
    }

    /** Return true if a logger is set. */
    public function hasLogger(): bool
    {
        return $this->logger !== null;
    }

    /** Return the session's stamp if any */
    public function getStamp(): ?string
    {
        return $this->stamp === null ? null : (string) $this->stamp;
    }

    /** Return the database connection. */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /** Initialize a connection client with context and register it in the client holder. */
    public function registerClient(ClientInterface $client): Session
    {
        $client->initialize($this);
        $this->clientHolder->add($client);

        if ($this->hasLogger()) {
            $this->getLogger()->debug(
                "Pomm: Registering new client",
                [
                    'type' => $client->getClientType(),
                    'identifier' => $client->getClientIdentifier(),
                ]
            );
        }


        return $this;
    }

    /** Return the logger if any. */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /** Tell if a client exist or not. */
    public function hasClient(string $type, string $name): bool
    {
        return $this->clientHolder->has($type, $name);
    }

    /** Add or replace a Client pooler for the specified type. */
    public function registerClientPooler(ClientPoolerInterface $clientPooler): Session
    {
        if ($clientPooler->getPoolerType() == null) {
            throw new \InvalidArgumentException("Can not register a pooler for the empty type.");
        }

        $clientPooler->register($this);
        $this->clientPoolers[$clientPooler->getPoolerType()] = $clientPooler;

        if ($this->hasLogger()) {
            $this->getLogger()
                ->debug(
                    "Pomm: Registering new client pooler.",
                    ['type' => $clientPooler->getPoolerType()]
                );
        }

        return $this;
    }

    /**
     * Return all instances of clients for a given type.
     *
     * @param string $type
     * @return array<string, ClientInterface>
     */
    public function getAllClientForType(string $type): array
    {
        return $this->clientHolder->getAllFor($type);
    }

    /**
     * Create handy methods to access clients through a pooler.
     *
     * @throws FoundationException      if no poolers found
     * @throws \BadFunctionCallException if unknown method
     *
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return ClientInterface
     */
    public function __call(string $method, array $arguments): ClientInterface
    {
        if (!preg_match('/get([A-Z][A-Za-z]+)/', $method, $matches)) {
            throw new \BadFunctionCallException(sprintf("Unknown method 'Session::%s()'.", $method));
        }

        return $this->getClientUsingPooler(
            Inflector::underscore($matches[1]),
            !empty($arguments) ? $arguments[0] : null
        );
    }

    /**
     * Summon a pooler to retrieve a client. If the pooler does not exist, a FoundationException is thrown.
     *
     * @throws FoundationException
     */
    public function getClientUsingPooler(string $type, ?string $identifier): ClientInterface
    {
        return $this->getPoolerForType($type)->getClient($identifier);
    }

    /** Return a Client from its type and identifier. */
    public function getClient(?string $type, string $identifier): ?ClientInterface
    {
        return $this->clientHolder->get($type, $identifier);
    }

    /**
     * Get the registered for the given type.
     * @throws FoundationException if pooler does not exist
     */
    public function getPoolerForType(string $type): ClientPoolerInterface
    {
        if (!$this->hasPoolerForType($type)) {
            $errorMessage = <<<ERROR
No pooler registered for type '%s'. Poolers available: {%s}.
If the pooler you are asking for is not listed there, maybe you have not used
the correct session builder. Use the "class:session_builder" parameter in the
configuration to associate each session with a session builder. A good practice
is to define your own project's session builders.
ERROR;
            if ($this->isShutdown) {
                $errorMessage = 'There are no poolers in the session because it is shutdown.';
            }

            throw new FoundationException(
                sprintf(
                    $errorMessage,
                    $type,
                    join(', ', $this->getRegisterPoolersNames())
                )
            );
        }

        return $this->clientPoolers[$type];
    }

    /** Tell if a pooler exist or not. */
    public function hasPoolerForType(string $type): bool
    {
        return isset($this->clientPoolers[$type]);
    }

    /**
     * Useful to test & debug.
     *
     * @return  array<int, string>
     */
    public function getRegisterPoolersNames(): array
    {
        return array_keys($this->clientPoolers);
    }
}
