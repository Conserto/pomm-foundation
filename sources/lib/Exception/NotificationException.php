<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Exception;

/**
 * Notification exception.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       FoundationException
 */
class NotificationException extends FoundationException
{
    protected string $channel;
    protected int $pid;

    /** @param array{message: string, pid: int, payload: string} $notification */
    public function __construct(array $notification)
    {
        parent::__construct();
        $this->channel = $notification['message'];
        $this->pid     = $notification['pid'];
        $this->message = $notification['payload'];
    }

    /** Return the channel's name. */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /** Return the server's PID. */
    public function getPid(): int
    {
        return $this->pid;
    }
}
