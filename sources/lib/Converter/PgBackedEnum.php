<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Session\Session;

/**
 * The 4.x name of PgEnum, restricted to backed enums. Kept so that existing code keeps working.
 *
 * @deprecated since 5.0, to be removed in 6.0: use PgEnum, which also handles pure enums.
 */
class PgBackedEnum extends PgEnum
{
    /**
     * @param class-string $enumFqcn
     *
     * @throws ConverterException
     */
    public function __construct(string $enumFqcn)
    {
        // Silenced like Symfony's deprecations: reported by debug tools and deprecation handlers, not in production.
        @trigger_error(
            sprintf('Since 5.0, "%s" is deprecated and will be removed in 6.0: use "%s".', self::class, PgEnum::class),
            E_USER_DEPRECATED
        );

        if (!is_a($enumFqcn, \BackedEnum::class, true)) {
            throw new ConverterException(sprintf('"%s" is not a backed enum: use PgEnum.', $enumFqcn));
        }

        parent::__construct($enumFqcn);
    }

    /** @throws ConverterException */
    public function fromPg(?string $data, string $type, Session $session): ?\BackedEnum
    {
        $case = parent::fromPg($data, $type, $session);

        // Always a BackedEnum or null: the constructor only accepts backed enums.
        return $case instanceof \BackedEnum ? $case : null;
    }
}
