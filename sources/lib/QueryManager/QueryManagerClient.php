<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\QueryManager;

use PommProject\Foundation\Client\Client;

/**
 * Query system as a client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
abstract class QueryManagerClient extends Client implements QueryManagerInterface
{
    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'query_manager';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return static::class;
    }
}
