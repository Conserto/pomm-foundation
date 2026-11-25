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
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgTypeName;
use PommProject\Foundation\Session\Session;

/**
 * Converter for numbers.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgFloat implements ConverterInterface
{
    use UnwrapsEnum;

    /** Binary floating point types, written with all their digits. */
    private const array FLOAT_TYPES = [
        'float4', 'pg_catalog.float4', 'real', 'float', 'float8', 'pg_catalog.float8', 'double precision',
    ];

    /** @see ConverterInterface */
    public function fromPg(?string $data, string $type, Session $session): ?float
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        if ($data === '') {
            return null;
        }

        // A (float) cast turns PostgreSQL's special values into 0.0.
        return match ($data) {
            'NaN' => \NAN,
            'Infinity' => \INF,
            '-Infinity' => -\INF,
            default => (float) $data,
        };
    }

    /**
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $value = $this->specialValue($this->unwrapEnum($data, $type));

        return match (true) {
            $value === null => sprintf('NULL::%s', PgTypeName::quote($type)),
            // A number only holds digits, a sign, a dot and an exponent: written as is, without the connection.
            is_int($value), is_float($value) => sprintf("%s '%s'", PgTypeName::quote($type), $this->toText($value, $type)),
            default => sprintf('%s %s', PgTypeName::quote($type), $session->getConnection()->escapeLiteral($this->toText($value, $type))),
        };
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        $value = $this->specialValue($this->unwrapEnum($data, $type));

        return $value !== null ? $this->toText($value, $type) : null;
    }

    protected function acceptsEnum(\UnitEnum $enum): bool
    {
        return $enum instanceof \BackedEnum && is_numeric($enum->value);
    }

    /** The value, already unwrapped from an enum and with its special values spelled, as text. */
    private function toText(mixed $value, string $type): string
    {
        // @phpstan-ignore argument.type (sprintf() formats any other user value, as it always did)
        return is_float($value) ? $this->floatToText($value, $type) : sprintf('%s', $value);
    }

    /**
     * A finite float as PostgreSQL writes it. Converting it to a string kept only 14 digits (PHP's precision setting),
     * silently losing the last ones: 1/3 was stored 0.33333333333333.
     *
     * - float4 / float8: the fewest digits (at most 17) that read back the same float, as PostgreSQL writes a float8.
     * - numeric and any other type: 15 significant digits, as PostgreSQL's float8 to numeric cast (0.1 + 0.2 gives
     *   0.3, not 0.30000000000000004).
     *
     * %h is %g with a dot as decimal separator whatever the locale. PHP's precision setting no longer applies.
     */
    private function floatToText(float $data, string $type): string
    {
        if (!in_array($type, self::FLOAT_TYPES, true)) {
            return sprintf('%.15h', $data);
        }

        for ($digits = 15; $digits < 17; ++$digits) {
            $text = sprintf('%.' . $digits . 'h', $data);

            if ((float) $text === $data) {
                return $text;
            }
        }

        return sprintf('%.17h', $data);
    }

    /**
     * NAN / INF / -INF written with PostgreSQL's spelling: sprintf() gave NAN / INF / -INF, which PostgreSQL accepts
     * too, but PHP 8.6 warns when it converts NAN to a string. Any other value is returned unchanged.
     */
    private function specialValue(mixed $data): mixed
    {
        return match (true) {
            !is_float($data) || is_finite($data) => $data,
            is_nan($data) => 'NaN',
            $data > 0 => 'Infinity',
            default => '-Infinity',
        };
    }
}
