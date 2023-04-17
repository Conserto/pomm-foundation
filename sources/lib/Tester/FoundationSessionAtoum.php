<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Tester;

use PommProject\Foundation\SessionBuilder;

/**
 * Provide an Atoum with a session configured with Foundation default poolers.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       VanillaSessionAtoum
 */
abstract class FoundationSessionAtoum extends VanillaSessionAtoum
{
    /** Override VanillaSessionAtoum to return a Foundation Session builder. */
    protected function createSessionBuilder(array $configuration): SessionBuilder
    {
        return new SessionBuilder($configuration);
    }
}
