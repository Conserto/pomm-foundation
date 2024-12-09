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
        $description = trim($description, ' ()');
        $regex = '/([0-9e\-+\.]+), *([0-9e\-+\.]+) *\), *\( *([0-9e\-+\.]+), *([0-9e\-+\.]+)/';

        if (!preg_match($regex, $description, $matches)) {
            throw new \InvalidArgumentException(
                sprintf("Could not parse box representation '%s'.", $description)
            );
        }

        $this->topX = (float) $matches[1];
        $this->topY = (float) $matches[2];
        $this->bottomX = (float) $matches[3];
        $this->bottomY = (float) $matches[4];

    }

    /** Return a string representation of Box. */
    public function __toString(): string
    {
        return sprintf(
            "((%s,%s),(%s,%s))",
            $this->topX,
            $this->topY,
            $this->bottomX,
            $this->bottomY
        );
    }
}
