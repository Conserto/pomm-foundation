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
 * PHP type for PostgreSQL's box type.
 *
 * @copyright 2017 Grégoire HUBERT
 * @author Miha Vrhovnik
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Box implements \Stringable
{
    public float $topX;
    public float $topY;
    public float $bottomX;
    public float $bottomY;

    /** Create a box from a string description. */
    public function __construct(string $description)
    {
        $numbers = FloatText::match($description, '((N,N),(N,N))', '(N,N),(N,N)', '(N,N,N,N)', 'N,N,N,N');

        if ($numbers === null) {
            throw new \InvalidArgumentException(
                sprintf("Could not parse box representation '%s'.", trim($description, ' ()'))
            );
        }

        [$this->topX, $this->topY, $this->bottomX, $this->bottomY] = $numbers;
    }

    /** Return a string representation of Box, with all the digits PostgreSQL needs to read back the same floats. */
    public function __toString(): string
    {
        return sprintf(
            "((%s,%s),(%s,%s))",
            FloatText::write($this->topX),
            FloatText::write($this->topY),
            FloatText::write($this->bottomX),
            FloatText::write($this->bottomY)
        );
    }
}
