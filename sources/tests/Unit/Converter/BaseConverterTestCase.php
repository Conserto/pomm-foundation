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

namespace PommProject\Foundation\Tests\Unit\Converter;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

abstract class BaseConverterTestCase extends FoundationSessionTestCase
{
    protected function initializeSession(Session $session): void
    {
    }

    /**
     * @throws FoundationException from getInspector() / getVersion()
     */
    public function isPgVersionAtLeast(string $version, Session $session): bool
    {
        $current = $session->getInspector()->getVersion();

        return version_compare($version, $current) <= 0;
    }

    /**
     * @throws FoundationException from getInspector() / getTypeInformation()
     */
    protected function doesTypeExist(string $type, Session $session): bool
    {
        return $session->getInspector()->getTypeInformation($type, 'public') !== null;
    }

    /**
     * @throws FoundationException|SqlException from getQueryManager() / QueryManager::query()
     */
    protected function sendToPostgres(mixed $value, mixed $type, Session $session): mixed
    {
        $result = $session->getQueryManager()
            ->query(sprintf('select $*::%s as my_test', $type), [$value])
            ->current();

        return $result['my_test'];
    }
}
