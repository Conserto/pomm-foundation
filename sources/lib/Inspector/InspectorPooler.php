<?php

/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Inspector;

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPooler;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;

/**
 * Pooler for Inspector client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ClientPooler
 */
class InspectorPooler extends ClientPooler
{
    /** @see ClientPoolerInterface */
    public function getPoolerType(): string
    {
        return 'inspector';
    }

    /**
     * @throws FoundationException
     * @see     ClientPooler
     */
    public function getClient(?string $identifier = null): ClientInterface
    {
        $identifier ??= Inspector::class;

        return parent::getClient($identifier);
    }

    /**
     * @throws FoundationException
     * @see    ClientPooler
     */
    protected function createClient(string $identifier): Client
    {
        try {
            if (!class_exists($identifier) && !interface_exists($identifier) && !trait_exists($identifier)) {
                // Same exception (and message) as the ReflectionClass constructor.
                throw new \ReflectionException(sprintf('Class "%s" does not exist', $identifier), -1);
            }
        } catch (\ReflectionException) {
            throw new FoundationException(
                sprintf(
                    "Unable to load inspector '%s'.",
                    $identifier
                )
            );
        }

        return new $identifier();
    }
}
