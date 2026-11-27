<?php

/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter\Geometry;

use PommProject\Foundation\Converter\ConverterInterface;
use PommProject\Foundation\Converter\Type\Box;
use PommProject\Foundation\Converter\TypeConverter;
use PommProject\Foundation\Session\Session;

/**
 * Converter for PostgreSQL box type.
 *
 * @copyright 2017 Grégoire HUBERT
 * @author Miha Vrhovnik
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see ConverterInterface
 */
class PgBox extends TypeConverter
{
    /** @see TypeConverter */
    public function getTypeClassName(): string
    {
        return Box::class;
    }

    /**
     * Kept for its untyped $type parameter: the file has no strict_types, so a Stringable type name is still accepted,
     * and a null type still writes box, as before.
     *
     * @param ?string $type
     * @see TypeConverter
     */
    #[\Override]
    public function toPg(mixed $data, $type, Session $session): string
    {
        return parent::toPg($data, $type ?? 'box', $session);
    }
}
