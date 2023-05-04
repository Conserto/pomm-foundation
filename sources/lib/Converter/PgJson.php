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
 * Json converter.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgJson implements ConverterInterface
{
    /** Configure the JSON converter to decode JSON as StdObject instances or arrays (default). */
    public function __construct(protected bool $isArray = true)
    {
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): mixed
    {
        if (null === $data || trim($data) === '') {
            return null;
        }

        $return = json_decode($data, $this->isArray);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new ConverterException(
                sprintf(
                    "Could not convert Json to PHP %s, json_last_error() returned '%d'.\n%s",
                    $this->isArray ? 'array' : 'object',
                    json_last_error(),
                    $data
                )
            );
        }

        return $return;
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        return
            $data !== null
            ? sprintf("json '%s'", $this->jsonEncode($data))
            : sprintf("NULL::%s", $type);
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $data !== null ? $this->jsonEncode($data) : null;
    }

    /**
     * Encode data to Json. Throw an exception if an error occurs.
     *
     * @throws ConverterException
     */
    protected function jsonEncode(mixed $data): string
    {
        $return = json_encode($data);

        if ($return === false) {
            throw new ConverterException(
                sprintf(
                    "Could not convert %s data to JSON. Driver returned '%s'.",
                    gettype($data),
                    json_last_error()
                )
            );
        }

        return $return;
    }
}
