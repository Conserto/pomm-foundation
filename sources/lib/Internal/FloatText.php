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
 * A float8 as PostgreSQL writes and reads it.
 *
 * @internal not part of the public API, may change without notice
 */
final class FloatText
{
    /**
     * A decimal float8 as PostgreSQL reads it, special values included (any case, inf or infinity, signed). Possessive
     * quantifiers: a long run of digits or spaces that does not match fails at once, without backtracking.
     */
    private const string PATTERN = '([+-]?+(?i:inf(?:inity)?+|nan)|[+-]?+(?:\d++\.?+\d*+|\.\d++)(?:[eE][+-]?+\d++)?+)';

    /** @var array<string, string> the regular expression of each form already used */
    private static array $regexes = [];

    /**
     * The fewest digits (at most 17) that read back the same float, as PostgreSQL writes a float8, and its spelling of
     * the special values. Converting a float to a string kept only 14 digits (PHP's precision setting), silently
     * losing the last ones: 1/3 was stored 0.33333333333333. PHP 8.6 also warns when it converts NAN to a string.
     *
     * %h is %g with a dot as decimal separator whatever the locale. PHP's precision setting does not apply.
     */
    public static function write(float $value): string
    {
        if (!is_finite($value)) {
            return match (true) {
                is_nan($value) => 'NaN',
                $value > 0 => 'Infinity',
                default => '-Infinity',
            };
        }

        for ($digits = 15; $digits < 17; ++$digits) {
            $text = sprintf('%.' . $digits . 'h', $value);

            if ((float) $text === $value) {
                return $text;
            }
        }

        return sprintf('%.17h', $value);
    }

    /**
     * The numbers of a geometric value written in one of the given forms, or null when it matches none or holds a
     * number out of float8's range (1e400, 1e-400), which PostgreSQL refuses. In a form, N stands for a number, and
     * spaces are allowed around the numbers and the punctuation: '(N,N)' reads ' ( 1 , 2 ) '.
     *
     * @return list<float>|null
     */
    public static function match(string $text, string ...$forms): ?array
    {
        foreach ($forms as $form) {
            $regex = self::$regexes[$form] ??= self::regex($form);

            if (preg_match($regex, $text, $matches)) {
                $numbers = array_map(self::readInput(...), array_slice($matches, 1));

                return in_array(null, $numbers, true) ? null : array_values(array_filter($numbers, is_float(...)));
            }
        }

        return null;
    }

    /** The regular expression of a form: one possessive run of spaces between two tokens. */
    private static function regex(string $form): string
    {
        $regex = '';

        foreach (str_split($form) as $char) {
            $regex .= '\\s*+' . ($char === 'N' ? self::PATTERN : preg_quote($char, '/'));
        }

        return '/^' . $regex . '\\s*+$/D';
    }

    /** The float a PostgreSQL text gives: a (float) cast turns its special values (NaN, Infinity) into 0.0. */
    public static function read(string $text): float
    {
        return match ($text) {
            'NaN' => \NAN,
            'Infinity' => \INF,
            '-Infinity' => -\INF,
            default => (float) $text,
        };
    }

    /**
     * A number matched by PATTERN, with the special values in any case and with a sign, as PostgreSQL reads them; null
     * when it is out of float8's range: a (float) cast turns it into INF or 0.
     */
    private static function readInput(string $text): ?float
    {
        $special = strtolower(ltrim($text, '+-'));

        if ($special === 'nan') {
            return \NAN;
        }

        if ($special === 'inf' || $special === 'infinity') {
            return str_starts_with($text, '-') ? -\INF : \INF;
        }

        $value = (float) $text;
        $overflow = !is_finite($value);
        $underflow = $value === 0.0 && preg_match('/[1-9]/', (string) strtok(strtolower($text), 'e')) === 1;

        return $overflow || $underflow ? null : $value;
    }
}
