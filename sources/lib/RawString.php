<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation;

/**
 * Expression escaper.
 *
 * This type allows to pass raw expressions as is to the database. Useful to call functions or database expressions.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class RawString implements \Stringable
{
    public function __construct(protected string $expression)
    {
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
