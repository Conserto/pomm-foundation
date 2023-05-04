<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Listener;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionAtoum;

class Listener extends FoundationSessionAtoum
{
    /** @throws FoundationException */
    public function testAttachAction(): void
    {
        $listener = $this->newTestedInstance('pika');
        $this->buildSession()->registerClient($listener);
        $this->object($listener->attachAction(fn($name, $data, $session) => true))
            ->isInstanceOf(\PommProject\Foundation\Listener\Listener::class)
            ->object($listener->attachAction([$this, 'testAttachAction']));
    }

    /** @throws FoundationException */
    public function testNotify(): void
    {
        $listener = $this->newTestedInstance('pika');
        $this->buildSession()->registerClient($listener);
        $flag = null;
        $listener
            ->attachAction(fn($name, $data, $session) => true)
            ->attachAction(function ($name, $data, $session) use (&$flag) {
                $flag = $name;
            });

        $this->object($listener->notify('pika', ['chu' => true], null))
            ->isInstanceOf(\PommProject\Foundation\Listener\Listener::class)
            ->string($flag)
            ->isEqualTo('pika');
    }

    protected function initializeSession(Session $session): void
    {
    }
}
