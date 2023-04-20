<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */namespace PommProject\Foundation\Client;

use PommProject\Foundation\Exception\PommException;

/**
 * Session clients are stored in this holder.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ClientHolder
{
    /** @var array<string, array<string, ClientInterface>> */
    protected array $clients = [];

    /** Add a new client or replace existing one. */
    public function add(ClientInterface $client): ClientHolder
    {
        $this->clients[$client->getClientType()][$client->getClientIdentifier()] = $client;

        return $this;
    }

    /** Tell if a client is in the pool or not. */
    public function has(string $type, string $name): bool
    {
        return isset($this->clients[$type][$name]);
    }

    /** Return a client by its name or null if no client exist for that name. */
    public function get(?string $type, string $name): ?ClientInterface
    {
        return $this->clients[$type][$name] ?? null;
    }

    /**
     * Return all clients for a given type.
     *
     * @return array<string, ClientInterface>
     */
    public function getAllFor(string $type): array
    {
        if (!isset($this->clients[$type])) {
            return [];
        }

        return $this->clients[$type];
    }

    /** Call shutdown and remove a client from the pool. If the client does not exist, nothing is done. */
    public function clear(string $type, string $name): ClientHolder
    {
        if (isset($this->clients[$type][$name])) {
            $this->clients[$type][$name]->shutdown();
            unset($this->clients[$type][$name]);
        }

        return $this;
    }

    /**
     * Call shutdown for all registered clients and unset the clients so they can be cleaned by GC.
     * It would have been better by far to use a RecursiveArrayIterator to do this but it is not possible in PHP using
     * built'in iterators hence the double foreach recursion.
     * see http://fr2.php.net/manual/en/class.recursivearrayiterator.php#106519
     *
     * @return array<int, PommException> exceptions caught during the shutdown
     */
    public function shutdown(): array
    {
        $exceptions = [];

        foreach ($this->clients as $names) {
            foreach ($names as $client) {
                try {
                    $client->shutdown();
                } catch (PommException $e) {
                    $exceptions[] = $e;
                }
            }
        }

        $this->clients = [];

        return $exceptions;
    }
}
