<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Internal;

use PommProject\Foundation\Exception\ConverterException;

/**
 * Values given to a query parameter Pomm does not convert: $* without a type, or a numbered $1 (only $*::type is
 * converted, $1::type is cast by PostgreSQL after pgsql turned the value into text).
 *
 * pgsql turns the value into text itself. Every PHP type gets an explicit rule:
 *
 * - null, string, int and Stringable objects are sent faithfully; an enum is sent as its value, or its name; any
 *   other object PHP can turn into text (GMP) is sent as that text, as pgsql did;
 * - bool and float are sent unchanged, with a silenced E_USER_DEPRECATED giving the text actually sent: false
 *   becomes an empty string, and a float follows PHP's precision setting (14 significant digits by default). true
 *   is noticed too: it works ('1'), but turning it into false breaks the query. The notice is silenced, so PHP's
 *   default handler ignores it; debug tools, deprecation loggers and PHPUnit's ignoreSuppressionOfDeprecations
 *   report it;
 * - any other value (array, object PHP cannot turn into text, resource) raises a ConverterException: pgsql wrote
 *   'Array', raised a PHP Error or wrote an arbitrary text.
 *
 * The fix is to write the parameter $*::type (with $* for every parameter of the query: Pomm numbers them), or, when
 * the SQL is built by the library (Where::createWhereIn()), to pass the value already as a string.
 *
 * @internal not part of the public API, may change without notice
 */
final class UntypedParameter
{
    private const string ADVICE = 'write every parameter of the query as $* and this one $*::%s, or pass the value as a'
        . ' string';

    /**
     * The value to send for the parameter at the given position (0 for the first parameter).
     *
     * $noticed holds the notices already given, by position and value kind (true, false, float): a caller sending the
     * same query again (a prepared query executed for many rows) passes the same array to notice each case once. true
     * and false are separate kinds, so a false following a true is still noticed.
     *
     * @param array<string, true> $noticed
     * @param-out array<string, true> $noticed
     * @throws ConverterException for a value PostgreSQL cannot read
     */
    public static function check(mixed $value, int $position, array &$noticed = []): mixed
    {
        return match (true) {
            $value === null, is_string($value), is_int($value), $value instanceof \Stringable => $value,
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            is_bool($value), is_float($value) => self::notice($value, $position, $noticed),
            is_object($value) && ($text = self::text($value)) !== null => $text,
            default => throw new ConverterException(sprintf(
                'Parameter %d is not converted by Pomm (only $*::type parameters are) and was given a value of type'
                . ' %s, which cannot be sent to PostgreSQL: %s.',
                $position + 1,
                get_debug_type($value),
                sprintf(self::ADVICE, 'type')
            )),
        };
    }

    /** The text PHP turns the object into, as pgsql does (GMP), or null when PHP cannot (DateTime, stdClass). */
    private static function text(mixed $value): ?string
    {
        try {
            // @phpstan-ignore cast.string (PHP decides: GMP has a cast, DateTime raises an Error)
            return (string) $value;
        } catch (\Error) {
            return null;
        }
    }

    /**
     * @param array<string, true> $noticed
     * @param-out array<string, true> $noticed
     */
    private static function notice(bool|float $value, int $position, array &$noticed): bool|float
    {
        [$kind, $sent, $type, $note] = is_bool($value)
            ? [$value ? 'true' : 'false', $value ? '1' : '', 'bool', " (false is sent as '')"]
            : ['float', (string) $value, 'float8 (or ::numeric)', ''];
        $key = $position . ':' . $kind;

        if (isset($noticed[$key])) {
            return $value;
        }

        $noticed[$key] = true;
        @trigger_error(
            sprintf(
                "Parameter %d is not converted by Pomm (only \$*::type parameters are) and was given a value of type"
                . " %s, sent as '%s'%s: %s.",
                $position + 1,
                get_debug_type($value),
                $sent,
                $note,
                sprintf(self::ADVICE, $type)
            ),
            E_USER_DEPRECATED
        );

        return $value;
    }
}
