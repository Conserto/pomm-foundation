<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Client;

use PommProject\Foundation\Session\Session;

/**
 * An interface for classes to be registered as Session clients.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
interface ClientInterface
{
    /**
     * This makes clients able to get Session environment hence perform queries. Also useful to perform sanity checks.
     */
    public function initialize(Session $session): void;

    /**
     * Perform some computations when the instance is removed from the pool.
     * Most of the time, instances are removed from the pool before the
     * Session is closed, you may have things to clean before it happens.
     */
    public function shutdown(): void;

    /** Must return the type of the session client. You may have several classes for each type of client. */
    public function getClientType(): string;

    /** Each client must have a unique identifier so it will be pooled in by the Session */
    public function getClientIdentifier(): string;
}
