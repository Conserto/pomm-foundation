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

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgTypeName;
use PommProject\Foundation\Session\Session;

/**
 * Converter for arrays.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ArrayTypeConverter
 */
class PgArray extends ArrayTypeConverter
{
    /** Extract subtype from a formatted string (ie int4[] or _text). */
    public static function getSubType(string $type): string
    {
        if (preg_match('/^(.+)\[\]$/', $type, $matches) || preg_match('/^_(.+)$/', $type, $matches)) {
            return $matches[1];
        }

        return $type;
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     *
     *
     * @return null|array<int, null|mixed>
     */
    public function fromPg(?string $data, string $type, Session $session): ?array
    {
        if ($data === null || $data === 'NULL') {
            return null;
        }

        $type = static::getSubType($type);

        if ($data !== "{}") {
            $converter = $this->getSubtypeConverter($type, $session);

            return array_map(function ($val) use ($converter, $type, $session) {
                if ($val !== "NULL") {
                    return null !== $val && preg_match('/\\\\/', $val)
                        ? $converter->fromPg(stripslashes($val), $type, $session)
                        : $converter->fromPg($val, $type, $session);
                }
                return null;
            }, str_getcsv(trim($data, "{}"), escape: "\\"));
        }
        return [];
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $type = static::getSubType($type);

        if ($data === null) {
            return sprintf("NULL::%s[]", PgTypeName::quote($type));
        }

        $converter = $this->getSubtypeConverter($type, $session);
        $data = $this->checkArray($data);

        return sprintf(
            'ARRAY[%s]::%s[]',
            implode(',', array_map(fn (mixed $val): string => $converter->toPg($val, $type, $session), $data)),
            PgTypeName::quote($type)
        );
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        $type = static::getSubType($type);
        $converter = $this->getSubtypeConverter($type, $session);
        $data = $this->checkArray($data);

        return
            sprintf('{%s}', implode(
                ',',
                array_map(function (mixed $val) use ($converter, $type, $session): string {
                    // A converter may return null for a value it writes as SQL NULL: an empty element ({1,,3}) is
                    // rejected by PostgreSQL.
                    $val = $val !== null ? $converter->toPgStandardFormat($val, $type, $session) : null;

                    if ($val === null) {
                        return 'NULL';
                    }

                    if ($val !== '') {
                        // A sub-array (int4[][]) stays bare. Any other value is quoted when it contains a separator,
                        // a quote or a backslash (an escape character otherwise: a\b would be read ab), braces (read as a
                        // nested array otherwise) or is NULL (read as SQL NULL otherwise).
                        if (
                            !$converter instanceof self
                            && (preg_match('/[,"\\\\\s{}]/', $val) === 1 || strcasecmp($val, 'NULL') === 0)
                        ) {
                            $val = sprintf('"%s"', addcslashes($val, '"\\'));
                        }
                    } else {
                        $val = '""';
                    }

                    return $val;
                }, $data)
            ));
    }
}
