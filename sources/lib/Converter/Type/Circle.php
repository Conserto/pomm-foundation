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
 * PHP representation of PostgreSQL circle type.
 *
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Circle implements \Stringable
{
    public Point $center;
    public float $radius;

    /**
     * Create a circle from a description string, in one of the forms PostgreSQL reads: <(x,y),r>, ((x,y),r), (x,y),r,
     * x,y,r or <x,y,r>.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $description)
    {
        $numbers = FloatText::match($description, '<(N,N),N>', '((N,N),N)', '(N,N),N', '<N,N,N>', 'N,N,N');

        // PostgreSQL refuses a negative radius.
        if ($numbers === null || $numbers[2] < 0) {
            throw new \InvalidArgumentException(
                sprintf("Could not parse circle description '%s'.", trim($description, ' <>'))
            );
        }

        [$x, $y, $this->radius] = $numbers;
        $this->center = $this->createPointFrom(sprintf('(%s,%s)', FloatText::write($x), FloatText::write($y)));
    }

    /** Create a point from a description. */
    protected function createPointFrom(string $description): Point
    {
        return new Point($description);
    }

    /** Create a string representation of the Circle. Actually, it dumps a SQL compatible circle representation. */
    public function __toString(): string
    {
        return sprintf("<%s,%s>", $this->center, FloatText::write($this->radius));
    }
}
