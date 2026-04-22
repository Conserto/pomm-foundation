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

namespace PommProject\Foundation\Tester;

use PommProject\Foundation\SessionBuilder;

/**
 * PHPUnit equivalent of {@see FoundationSessionAtoum}: provides a session
 * pre-wired with Foundation's default poolers and converters.
 *
 * @see VanillaSessionTestCase
 */
abstract class FoundationSessionTestCase extends VanillaSessionTestCase
{
    /**
     * @param array<string, mixed> $configuration
     */
    protected function createSessionBuilder(array $configuration): SessionBuilder
    {
        return new SessionBuilder($configuration);
    }
}
