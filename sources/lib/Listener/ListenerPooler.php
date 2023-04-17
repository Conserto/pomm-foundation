<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Listener;

use PommProject\Foundation\Client\ClientPooler;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;

/**
 * Pooler for listener clients.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ClientPooler
 */
class ListenerPooler extends ClientPooler
{
    /**
     * Send a notification to clients.
     * Client identifiers may be a single client name, an array of client or '*' to notify all clients.
     * Event name may use ':' to split indicate additional information (ie type of payload).
     * Events sent to 'pika', 'pika:chu' will both notify client 'pika'.
     *
     * @throws FoundationException
     * @param array $data
     * @return ListenerPooler   $this
     * @param string|array<int, string> $identifiers
     */
    public function notify(string|array $identifiers, array $data): ListenerPooler
    {
        if ($this->getSession()->hasLogger()) {
            $this->getSession()->getLogger()->debug(
                "Pomm: ListenerPooler: notification received.",
                ['receivers' => $identifiers]
            );
        }

        if (is_scalar($identifiers)) {
            if ($identifiers === '*') {
                return $this->notifyAll($data);
            }

            $identifiers = [$identifiers];
        }

        return $this->notifyClients($identifiers, $data);
    }

    /**
     * Notify all existing clients.
     *
     * @throws FoundationException
     */
    protected function notifyAll(array $data): ListenerPooler
    {
        /** @var Listener $client */
        foreach ($this->getSession()->getAllClientForType($this->getPoolerType()) as $client) {
            $client->notify('*', $data);
        }

        return $this;
    }

    /** @see ClientPoolerInterface */
    public function getPoolerType(): string
    {
        return 'listener';
    }

    /**
     * Send a notification to the specified clients.
     *
     * @throws FoundationException
     * @param array<int, string> $identifiers
     * @param array $data
     * @return ListenerPooler   $this
     */
    protected function notifyClients(array $identifiers, array $data): ListenerPooler
    {
        foreach ($identifiers as $identifier) {
            $clientName = str_contains((string)$identifier, ':')
                ? substr((string)$identifier, 0, strpos((string)$identifier, ':'))
                : $identifier;

            /** @var ?Listener $client */
            $client = $this
                ->getSession()
                ->getClient($this->getPoolerType(), $clientName);

            $client?->notify($identifier, $data);
        }

        return $this;
    }

    /** See @ClientPooler */
    protected function createClient(string $identifier): Listener
    {
        return new Listener($identifier);
    }
}
