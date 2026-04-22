<?php

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Fixture\QueryManager;

use PommProject\Foundation\Session\Session;

class ListenerTester
{
    public bool $is_called = false;
    public ?string $sql = null;

    /** @var array<int, mixed> */
    public array $parameters = [];
    public ?string $session_stamp = null;
    public ?int $result_count = null;

    /**
     * @param array<string, mixed> $data
     */
    public function call(string $event, array $data, Session $session): void
    {
        $this->is_called = true;

        if (isset($data['sql'])) {
            $this->sql = $data['sql'];
        }
        if (isset($data['parameters'])) {
            $this->parameters = $data['parameters'];
        }
        if (isset($data['session_stamp'])) {
            $this->session_stamp = $data['session_stamp'];
        }
        if (isset($data['result_count'])) {
            $this->result_count = $data['result_count'];
        }
    }

    public function clear(): void
    {
        $this->is_called = false;
        $this->sql = null;
        $this->parameters = [];
        $this->session_stamp = null;
    }
}
