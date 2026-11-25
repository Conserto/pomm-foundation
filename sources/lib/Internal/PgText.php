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
 * Strings PostgreSQL parses itself.
 *
 * The integer, boolean and date converters do not interpret strings: they pass them unchanged, and PostgreSQL accepts
 * or rejects them by its own input rules (' 7 ' is an int4, '12.7' is not; 'yes' is a boolean; '19940623' is a date).
 * Other PHP values follow PostgreSQL's assignment rules, i.e. what it does when storing a value of that type into a
 * column of the target type; each converter applies them.
 *
 * @internal not part of the public API, may change without notice
 */
final class PgText
{
    /** The string, or null for a value that is neither a string nor a Stringable object. */
    public static function of(mixed $value): ?string
    {
        return is_string($value) || $value instanceof \Stringable ? (string) $value : null;
    }
}
