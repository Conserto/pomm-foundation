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
 * Abstract classes for range types.
 *
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 * @abstract
 */
abstract class BaseRange implements \Stringable
{
    final const INFINITY_MAX = 'infinity';
    final const INFINITY_MIN = '-infinity';
    final const EMPTY_RANGE  = 'empty';

    public mixed $startLimit;
    public mixed $endLimit;
    public ?bool $startIncl;
    public ?bool $endIncl;

    protected string $description;

    /**
     * This function must capture 4 elements of the description in this order:
     *
     * - left bracket style
     * - left element
     * - right element
     * - right bracket style
     */
    abstract protected function getRegexp(): string;

    /** Return the representation for each element. */
    abstract protected function getSubElement(string $element): mixed;

    /**
     * Create an instance from a string definition. This string definition matches PostgreSQL range definition.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $description)
    {
        if (!preg_match($this->getRegexp(), $description, $matches)) {
            throw new \InvalidArgumentException(sprintf("Could not parse range description '%s'.", $description));
        }

        if (count($matches) === 2) {
            $this->startLimit = self::EMPTY_RANGE;
            $this->endLimit   = self::EMPTY_RANGE;
            $this->startIncl  = null;
            $this->endIncl    = null;
        } else {
            $this->startLimit = $this->getSubElement($matches[3]);
            $this->endLimit   = $this->getSubElement($matches[4]);
            $this->startIncl  = $matches[2] === '[';
            $this->endIncl    = $matches[5] === ']';
        }

        $this->description = $description;
    }

    /** Text representation of a range. */
    public function __toString(): string
    {
        return $this->description;
    }
}
