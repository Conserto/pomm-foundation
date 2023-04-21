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
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\Client;

/**
 * Listener client.
 * This class may attach actions that are triggered by events.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       Client
 */
class Listener extends Client
{
    /** @var array<int, callable> */
    protected array $actions = [];

    /** Take the client identifier as argument.*/
    public function __construct(protected string $name)
    {
    }

    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'listener';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return $this->name;
    }

    /** Attach a new callback to the callback list. */
    public function attachAction(callable $action): Listener
    {
        $this->actions[] = $action;

        return $this;
    }

    /**
     * Trigger actions. All actions are executed passing the following parameters:
     * string   $name    name of the event
     * array    $data    event's payload if any
     * Session  $session the current session
     *
     * @throws FoundationException
     *
     * @param string $name
     * @param array<mixed, mixed> $data
     * @return Listener
     */
    public function notify(string $name, array $data): Listener
    {
        foreach ($this->actions as $action) {
            call_user_func($action, $name, $data, $this->getSession());
        }

        return $this;
    }
}
