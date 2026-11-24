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

use PommProject\Foundation\Exception\ConverterException;
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
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $data = $this->specialValue($this->unwrapEnum($data, $type));

        // @phpstan-ignore argument.type (sprintf() formats any user value, as it always did)
        return $data !== null ? sprintf("%s '%s'", $type, $data) : sprintf("NULL::%s", $type);
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        $data = $this->specialValue($this->unwrapEnum($data, $type));

        // @phpstan-ignore argument.type (sprintf() formats any user value, as it always did)
        return $data !== null ? sprintf('%s', $data) : null;
    }

    protected function acceptsEnum(\UnitEnum $enum): bool
    {
        return $enum instanceof \BackedEnum && is_numeric($enum->value);
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
