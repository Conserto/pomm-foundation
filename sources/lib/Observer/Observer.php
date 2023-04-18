<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Observer;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\NotificationException;
use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Session\Session;

/**
 * Observer session client. Listen to notifications sent to the server.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       Client
 */
class Observer extends Client
{
    public function __construct(protected string $channel)
    {
    }

    /** @see Client */
    public function getClientType(): string
    {
        return 'observer';
    }

    /** @see Client */
    public function getClientIdentifier(): string
    {
        return $this->channel;
    }

    /**
     * @throws FoundationException
     * @see Client
     */
    public function initialize(Session $session): void
    {
        parent::initialize($session);
        $this->restartListening();
    }

    /** @see Client */
    public function shutdown(): void
    {
        $this->unlisten($this->channel);
    }

    /**
     * Check if a notification is pending. If so, the payload is returned. Otherwise, null is returned.
     *
     * @throws FoundationException
     * @return array{message: string, pid: int, payload: string}|null
     */
    public function getNotification(): ?array
    {
        return $this->getSession()->getConnection()->getNotification();
    }

    /**
     * Send a LISTEN command to the backend. This is called in the initialize() method but it can be unlisten if the
     * listen command took place in a transaction.
     *
     * @throws FoundationException
     */
    public function restartListening(): Observer
    {
        return $this->listen($this->channel);
    }

    /**
     * Start to listen on the given channel. The observer automatically starts listening when registered against the
     * session.
     * NOTE: When listen is issued in a transaction it is unlisten when the transaction is committed or rollback.
     *
     * @throws FoundationException
     */
    protected function listen(string $channel): Observer
    {
        $this->executeAnonymousQuery(
            sprintf("listen %s", $this->escapeIdentifier($channel))
        );

        return $this;
    }

    /**
     * Stop listening to events.
     *
     * @throws FoundationException
     */
    protected function unlisten(string $channel): Observer
    {
        $this->executeAnonymousQuery(
            sprintf("unlisten %s", $this->escapeIdentifier($channel))
        );

        return $this;
    }

    /**
     * Check if a notification is pending. If so, a NotificationException is thrown.
     *
     * @throws  NotificationException|FoundationException
     */
    public function throwNotification(): Observer
    {
        $notification = $this->getNotification();

        if ($notification !== null) {
            throw new NotificationException($notification);
        }

        return $this;
    }

    /**
     * Proxy for Connection::executeAnonymousQuery()
     *
     * @throws FoundationException
     * @see Connection
     */
    protected function executeAnonymousQuery(string $sql): Observer
    {
        $this->getSession()->getConnection()->executeAnonymousQuery($sql);

        return $this;
    }

    /**
     * Proxy for Connection::escapeIdentifier()
     *
     * @throws FoundationException
     * @see Connection
     */
    protected function escapeIdentifier(string $string): string
    {
        return $this->getSession()->getConnection()->escapeIdentifier($string);
    }
}
