<?php

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\PhpUnit\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Listener\Listener;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

#[CoversClass(Listener::class)]
class ListenerTest extends FoundationSessionTestCase
{
    public function testAttachAction(): void
    {
        $listener = new Listener('pika');
        $this->buildSession()->registerClient($listener);

        self::assertInstanceOf(
            Listener::class,
            $listener->attachAction(fn ($name, $data, $session): true => true)
        );
        self::assertInstanceOf(
            Listener::class,
            $listener->attachAction($this->testAttachAction(...))
        );
    }

    public function testNotify(): void
    {
        $listener = new Listener('pika');
        $this->buildSession()->registerClient($listener);
        $flag = null;

        $listener
            ->attachAction(fn ($name, $data, $session): true => true)
            ->attachAction(function ($name, $data, $session) use (&$flag): void {
                $flag = $name;
            });

        self::assertInstanceOf(Listener::class, $listener->notify('pika', ['chu' => true], null));
        self::assertSame('pika', $flag);
    }

    protected function initializeSession(Session $session): void
    {
    }
}
