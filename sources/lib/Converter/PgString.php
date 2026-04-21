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
use PommProject\Foundation\Session\Session;

/**
 * Converter for strings types.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ConverterInterface
 */
class PgString implements ConverterInterface
{
    /**
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $data = $this->normalizeEnum($data, $type);

        return $data !== null
            ? sprintf("%s %s",  $type, $session->getConnection()->escapeLiteral($data))
            : sprintf("NULL::%s", $type);
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $this->normalizeEnum($data, $type);
    }

    /** @throws ConverterException */
    private function normalizeEnum(mixed $data, string $type): ?string
    {
        if ($data instanceof \BackedEnum) {
            if (!is_string($data->value)) {
                throw new ConverterException(sprintf(
                    "BackedEnum '%s' is int-backed and cannot be converted to string type '%s'.",
                    $data::class,
                    $type
                ));
            }

            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        return $data;
    }

    /** @see ConverterInterface */
    public function fromPg(?string $data, string $type, Session $session): ?string
    {
        return $data;
    }
}
