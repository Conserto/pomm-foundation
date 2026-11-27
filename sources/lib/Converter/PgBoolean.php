<?php

declare(strict_types=1);

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
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgText;
use PommProject\Foundation\Internal\PgTypeName;
use PommProject\Foundation\Session\Session;

/**
 * Converter for boolean type.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgBoolean implements ConverterInterface
{
    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): ?bool
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        if (!preg_match('/^(t|f)$/', $data)) {
            if ($data === '') {
                return null;
            }

            throw new ConverterException(sprintf("Unknown %s data '%s'.", $type, $data));
        }

        return $data === 't';
    }

    /**
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        return match (true) {
            $data === null => sprintf("NULL::%s", PgTypeName::quote($type)),
            is_bool($data) => sprintf("%s '%s'", PgTypeName::quote($type), $data ? "true" : "false"),
            default => sprintf("%s %s", PgTypeName::quote($type), $session->getConnection()->escapeLiteral($this->toText($data, $type))),
        };
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return match (true) {
            $data === null => null,
            is_bool($data) => $data ? 't' : 'f',
            default => $this->toText($data, $type),
        };
    }

    /**
     * Strings are passed unchanged, PostgreSQL accepts or rejects them by its own rules: 't', 'yes', 'on', '1' are
     * true, 'f', 'no', 'off', '0' false, '' or 'abc' raise an error. Only the PHP true used to give true: everything
     * else was silently written as false.
     *
     * @throws ConverterException for numbers (PostgreSQL does not store them into a boolean column), arrays and non
     * Stringable objects.
     */
    private function toText(mixed $data, string $type): string
    {
        return PgText::of($data) ?? throw new ConverterException(sprintf(
            "Cannot convert a '%s' value to %s.",
            get_debug_type($data),
            $type
        ));
    }
}
