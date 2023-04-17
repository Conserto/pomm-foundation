<?php
/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter\Type;

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
     * Create a circle from a description string.
     *
     * @param  string $description
     * @throws \InvalidArgumentException
     */
    public function __construct(string $description)
    {
        $description = trim($description, ' <>');
        $elts = preg_split(
            '/[,\s]*(\([^\)]+\))[,\s]*|[,\s]+/',
            $description,
            0,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );

        if ((is_countable($elts) ? count($elts) : 0) !== 2) {
            throw new \InvalidArgumentException(
                sprintf("Could not parse circle description '%s'.", $description)
            );
        }

        $this->center = $this->createPointFrom($elts[0]);
        $this->radius = (float) $elts[1];
    }

    /** Create a point from a description. */
    protected function createPointFrom(string $description): Point
    {
        return new Point($description);
    }

    /** Create a string representation of the Circle. Actually, it dumps a SQL compatible circle representation. */
    public function __toString(): string
    {
        return sprintf("<%s,%s>", $this->center, $this->radius );
    }
}
