<?php
/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Session\Session;

/**
 * Interface for converters.
 *
 * NOTE: Here is the coding convention for value conversion TO postgres:
 * Values are always surrounded by simple quotes with the type prefixed.
 * Example: int4 '8', bool 'true', varchar 'a b c d'
 *
 * Null values must be type cast inline and be uppercase.
 * Example: NULL::int4, NULL::timestamp, NULL::circle
 *
 * Arrays are just declared with 'array[…]' and type cast inline.
 * Example: array[int4 '8',int4 '12']::int4[]
 *
 * Complex types with an existing constructor must use it.
 * Example: circle(point(1.23,2.34), 5.67), hstore('"a" => "b"')
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
interface ConverterInterface
{
    /**
     * Parse the output string from PostgreSQL and returns the converted value into an according PHP representation.
     *
     * @param string|null $data Input string from Pg row result.
     * @param string $type
     * @param Session $session
     * @return mixed PHP representation of the data.
     */
    public function fromPg(?string $data, string $type, Session $session): mixed;

    /**
     * Convert a PHP representation into the according Pg formatted string.
     *
     * @param mixed $data PHP representation.
     * @param string $type
     * @param Session $session
     * @return string  Pg converted string for input.
     */
    public function toPg(mixed $data, string $type, Session $session): string;

    /**
     * Convert a PHP representation into short PostgreSQL format like used in COPY values list.
     *
     * @param mixed $data PHP representation.
     * @param string $type
     * @param Session $session
     * @return string|null PostgreSQL standard representation.
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string;
}
