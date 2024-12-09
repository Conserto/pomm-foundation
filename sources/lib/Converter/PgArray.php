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
     * @param string $type
     * @param Session $session
     * @param string|null $data
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
                } else {
                    return null;
                }
            }, str_getcsv(trim($data, "{}"), escape: "\\"));
        } else {
            return [];
        }
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $type = static::getSubType($type);

        if ($data === null) {
            return sprintf("NULL::%s[]", $type);
        }

        $converter = $this->getSubtypeConverter($type, $session);
        $data = $this->checkArray($data);

        return sprintf(
            'ARRAY[%s]::%s[]',
            join(',', array_map(fn(mixed $val): string => $converter->toPg($val, $type, $session), $data)),
            $type
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
            sprintf('{%s}', join(',',
                array_map(function (mixed $val) use ($converter, $type, $session): ?string {
                    if ($val === null) {
                        return 'NULL';
                    }

                    $val = $converter->toPgStandardFormat($val, $type, $session);

                    if ($val !== '') {
                        if (preg_match('/[,\\"\s]/', $val)) {
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
