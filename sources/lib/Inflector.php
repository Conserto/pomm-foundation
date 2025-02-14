<?php
/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation;

/**
 * Turn identifiers from/to StudlyCaps/underscore.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Inflector
{
    /** Camelize a string. */
    public static function studlyCaps(?string $string = null): ?string
    {
        if ($string === null) {
            return null;
        }

        return preg_replace_callback(
            '/_([a-z])/',
            fn(array $v): string => strtoupper((string) $v[1]),
            ucfirst(strtolower($string))
        );
    }

    /** Underscore a string. */
    public static function underscore(?string $string = null): ?string
    {
        if ($string === null) {
            return null;
        }

        return strtolower((string) preg_replace('/([A-Z])/', '_\1', lcfirst($string)));
    }
}
