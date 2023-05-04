<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionAtoum;

abstract class BaseConverter extends FoundationSessionAtoum
{
    /** Return true if the server version meets client expectations. */
    public function isPgVersionAtLeast(string $version, Session $session): bool
    {
        $current_version = $session->getInspector()->getVersion();

        return version_compare($version, $current_version) <= 0;
    }

    protected function initializeSession(Session $session): void
    {
    }

    /** Return true if the given type exists. */
    protected function doesTypeExist(string $type, Session $session): bool
    {
        return ($session->getInspector()->getTypeInformation($type, 'public') !== null);
    }

    /**
     * To test toPgStandardFormat, values can be sent to Postgres and retreived.
     *
     * @return mixed query result
     */
    protected function sendToPostgres(mixed $value, mixed $type, Session $session): mixed
    {
        $result = $session->getQueryManager()
            ->query(
                sprintf("select $*::%s as my_test", $type),
                [$value]
            )
            ->current();

        return $result['my_test'];
    }
}
