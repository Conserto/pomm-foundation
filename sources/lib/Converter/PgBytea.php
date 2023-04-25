<?php
/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Bytea converter
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class PgBytea implements ConverterInterface
{
    /**
     * @throws ConnectionException
     * @see Pomm\Converter\ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        return $data !== null
            ? sprintf("%s '%s'", $type, $this->escapeByteString($session, $data))
            : sprintf("NULL::%s", $type);
    }

    /** @see Pomm\Converter\ConverterInterface */
    public function fromPg(?string $data, string $type, Session $session): ?string
    {
        return $data !== null ? $session->getConnection()->unescapeBytea($data) : null;
    }

    /**
     * @throws ConnectionException|FoundationException
     * @see Pomm\Converter\ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $data !== null ? sprintf('%s', $this->escapeByteString($session, $data)) : null;
    }

    /**
     * Escape a binary string to postgres.
     *
     * @throws ConnectionException|FoundationException
     */
    protected function escapeByteString(Session $session, mixed $string): string
    {
        return preg_replace(["/\\\\/", '/"/'], ["\\", '""'], $session->getConnection()->escapeBytea($string));
    }
}
