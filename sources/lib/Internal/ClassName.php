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

namespace PommProject\Foundation\Internal;

/**
 * Class name checks shared by the classes that instantiate a configured class name.
 *
 * @internal not part of the public API, may change without notice
 */
final class ClassName
{
    /**
     * Throw the very ReflectionException (message and code) the ReflectionClass constructor throws for an unknown
     * class, interface or trait — without reflecting it, so the name is known to be a class-string afterwards.
     *
     * @phpstan-assert class-string $class
     * @throws \ReflectionException
     */
    public static function mustExist(string $class): void
    {
        if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
            throw new \ReflectionException(sprintf('Class "%s" does not exist', $class), -1);
        }
    }
}
