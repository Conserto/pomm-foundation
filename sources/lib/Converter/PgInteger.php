<?php

/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2017 Grégoire HUBERT <hubert.greg@gmail.com>
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
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $data = $this->unwrapEnum($data, $type);

        return $data !== null ? sprintf("%s '%d'", $type, $data) : sprintf("NULL::%s", $type);
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        $data = $this->unwrapEnum($data, $type);

        return $data !== null ? sprintf('%d', $data) : null;
    }

    protected function acceptsEnum(\UnitEnum $enum): bool
    {
        return $enum instanceof \BackedEnum && is_int($enum->value);
    }
}
