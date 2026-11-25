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
 * Type names written into SQL by the converters.
 *
 * PostgreSQL folds unquoted names to lower case: a type created as "Status" must be written with its quotes, or it is
 * read as status. PostgreSQL reports such a name without quotes (Status), so a name part mixing ASCII upper and lower
 * case letters is quoted. Everything else is written unchanged; for another name needing quotes (a type created as
 * "STATUS" or "order"), give it quoted.
 *
 * @internal not part of the public API, may change without notice
 */
final class PgTypeName
{
    /** A plain identifier, or one already quoted. */
    private const string PART = '(?:[A-Za-z_\x80-\xFF][A-Za-z0-9_$\x80-\xFF]*|"(?:[^"]|"")+")';

    /** The type name as it must be written in SQL. */
    public static function quote(string $type): string
    {
        // Almost every type name is lower case: nothing to quote, and no regex to run for each value written.
        if (strtolower($type) === $type) {
            return $type;
        }

        if (!preg_match('/^' . self::PART . '(?:\.' . self::PART . ')?\z/', $type)) {
            return $type;
        }

        preg_match_all('/' . self::PART . '/', $type, $matches);

        return implode('.', array_map(
            static fn (string $part): string => !str_starts_with($part, '"')
                && preg_match('/[A-Z]/', $part) === 1
                && preg_match('/[a-z]/', $part) === 1
                    ? sprintf('"%s"', $part)
                    : $part,
            $matches[0]
        ));
    }
}
