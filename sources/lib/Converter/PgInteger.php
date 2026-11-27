<?php

declare(strict_types=1);

/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2017 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgText;
use PommProject\Foundation\Session\Session;

/**
 * Converter for numbers.
 *
 * @copyright 2014 - 2017 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgInteger implements ConverterInterface
{
    use UnwrapsEnum;

    /** @see ConverterInterface */
    public function fromPg(?string $data, string $type, Session $session): ?int
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        if ($data === '') {
            return null;
        }

        return (int) $data;
    }

    /**
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $data = $this->unwrapEnum($data, $type);
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        return is_int($data)
            ? sprintf("%s '%d'", $type, $data)
            : sprintf("%s %s", $type, $session->getConnection()->escapeLiteral($this->toText($data, $type)));
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        $data = $this->unwrapEnum($data, $type);

        return $data === null ? null : $this->toText($data, $type);
    }

    protected function acceptsEnum(\UnitEnum $enum): bool
    {
        return $enum instanceof \BackedEnum && is_int($enum->value);
    }

    /**
     * Strings are passed unchanged, PostgreSQL accepts or rejects them by its own rules ('12' and ' 7 ' pass; '',
     * 'abc' and '12.7' raise an error), where PHP's integer conversion used to turn them silently into 0 or 12.
     * Floats are rounded half to even, as PostgreSQL stores a double precision into an integer column (12.5 gives 12,
     * 13.5 gives 14; 4.x truncated them); PostgreSQL then checks the range.
     *
     * @throws ConverterException for booleans (PostgreSQL does not store them into an integer column), NAN / INF,
     * arrays and non Stringable objects.
     */
    private function toText(mixed $data, string $type): string
    {
        return match (true) {
            is_int($data) => (string) $data,
            is_float($data) && is_finite($data) => sprintf('%.0f', round($data, 0, \RoundingMode::HalfEven)),
            default => PgText::of($data) ?? throw new ConverterException(sprintf(
                "Cannot convert a '%s' value to %s.",
                get_debug_type($data),
                $type
            )),
        };
    }
}
