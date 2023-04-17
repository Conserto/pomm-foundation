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

/**
 * A Query Manager is a Client able to perform a query and return an iterator on results.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
interface QueryManagerInterface
{
    /**
     * Perform a query and return an iterator.
     *
     * @param string $sql
     * @param array<int, mixed> $parameters
     * @return \Iterator
     */
    public function query(string $sql, array $parameters = []): \Iterator;
}
