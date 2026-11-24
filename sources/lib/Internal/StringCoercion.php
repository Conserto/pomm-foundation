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

/**
 * Explicit form of PHP's coercive typing mode for string parameters.
 *
 * Several Foundation files do not declare strict_types: user values reaching a `string` / `?string` parameter
 * were silently converted by PHP. This class performs exactly the same conversion, so the conversion can be
 * written (and statically analysed) without changing behaviour: scalars and Stringable objects are converted
 * like a `(string)` cast — which is what coercion does — and anything that used to raise a TypeError
 * (array, other objects) is reported as null, leaving the caller to raise a meaningful exception.
 *
 * @internal not part of the public API, may change without notice
 */
final class StringCoercion
{
    /**
     * Value a `string` parameter received in coercive typing mode, or null when PHP raised a TypeError.
     * A null $value gives null too: callers with a `?string` target pass it through themselves.
     */
    public static function toString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value), $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }
}
