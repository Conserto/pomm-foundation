<?php

declare(strict_types=1);

/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter\Type;

use PommProject\Foundation\Internal\FloatText;

/**
 * PHP type for PostgreSQL's point type.
 *
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Point implements \Stringable
{
    public float $x;
    public float $y;

    /** Create a point from a string description. */
    public function __construct(string $description)
    {
        $numbers = FloatText::match($description, '(N,N)', 'N,N');

        if ($numbers === null) {
            throw new \InvalidArgumentException(
                sprintf("Could not parse point representation '%s'.", trim($description, ' ()'))
            );
        }

        [$this->x, $this->y] = $numbers;
    }

    /** Return a string representation of Point, with all the digits PostgreSQL needs to read back the same floats. */
    public function __toString(): string
    {
        return sprintf("(%s,%s)", FloatText::write($this->x), FloatText::write($this->y));
    }
}
