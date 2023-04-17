<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\PreparedQuery;

use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\QueryManager\QueryManagerClient;

/**
 * Query manager using the prepared_statement client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       SimpleQuery
 */
class PreparedQueryManager extends QueryManagerClient
{
    /**
     * @throws FoundationException
     * @see QueryManagerInterface
     *
     * @param string $sql
     * @param array<int, mixed> $parameters
     * @return ConvertedResultIterator
     */
    public function query(string $sql, array $parameters = []): ConvertedResultIterator
    {
        /** @var PreparedQuery $prepareQuery */
        $prepareQuery = $this->getSession()
            ->getClientUsingPooler('prepared_query', $sql);

        $resource = $prepareQuery->execute($parameters);

        return new ConvertedResultIterator($resource, $this->getSession());
    }
}
