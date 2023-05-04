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

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Trait to add sendNotification method to clients.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
trait SendNotificationTrait
{
    /** sendNotification needs to access the session. */
    abstract protected function getSession(): Session;

    /**
     * Send notification to the listener pooler.
     *
     * @throws FoundationException
     *
     * @param string $name
     * @param array<mixed, mixed> $data
     * @return static
     */
    protected function sendNotification(string $name, array $data): static
    {
        /** @var Listener $listener */
        $listener = $this->getSession()->getPoolerForType('listener');
        $listener->notify($name, $data);

        return $this;
    }
}
