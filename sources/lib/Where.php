<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2011 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation;

/**
 * This class represents a WHERE clause of a SQL statement. It deals with AND &
 * OR operator you can add using handy methods. This allows you to build
 * queries dynamically.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Where implements \Stringable
{
    /** @var array<int, Where> */
    public array $stack = [];

    /** @var array<int, mixed> */
    public array $values = [];
    public ?string $operator = null;

    /**
     * @param string|null $element
     * @param array<int, mixed> $values
     */
    public function __construct(public ?string $element = null, array $values = [])
    {
        if ($element !== null) {
            $this->values = $values;
        }
    }

    /**
     * Create an escaped IN clause.
     *
     * @param string $element
     * @param array<int, mixed> $values
     * @return Where
     */
    public static function createWhereIn(string $element, array $values): Where
    {
        return self::createGroupCondition($element, 'IN', $values);
    }

    /**
     * Create a Where instance with multiple escaped parameters. This is mainly useful for IN or NOT IN clauses.
     *
     * @param string $element
     * @param string $operation
     * @param array<int, mixed> $values
     * @return Where
     */
    public static function createGroupCondition(string $element, string $operation, array $values): Where
    {
        return new self(
            sprintf(
                "%s %s (%s)",
                $element,
                $operation,
                join(", ", static::escapeSet($values))
            ),
            static::extractValues($values)
        );
    }

    /**
     * Create an array of escaped strings from a value set.
     *
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    protected static function escapeSet(array $values): array
    {
        $escapedValues = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $escapedValues[] =
                    sprintf("(%s)", join(', ', static::escapeSet($value)));
            } else {
                $escapedValues[] = '$*';
            }
        }

        return $escapedValues;
    }

    /**
     * Extract values with consistent keys.
     *
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    protected static function extractValues(array $values): array
    {
        $array = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($values)) as $value) {
            $array[] = $value;
        }

        return $array;
    }

    /**
     * Create an escaped NOT IN clause.
     *
     * @param string $element
     * @param array<int, mixed> $values
     * @return Where
     */
    public static function createWhereNotIn(string $element, array $values): Where
    {
        return self::createGroupCondition($element, 'NOT IN', $values);
    }

    /**
     * Or use a ready to use AND where clause.
     *
     * @param array<int, mixed> $values
     * @return Where
     */
    public function andWhere(mixed $element, array $values = []): Where
    {
        return $this->addWhere($element, $values, 'AND');
    }

    /**
     * You can add a new WHERE clause with your own operator.
     *
     * @param array<int, mixed> $values
     * @param string $operator
     * @return Where
     */
    public function addWhere(mixed $element, array $values, string $operator): Where
    {
        if (!$element instanceof Where) {
            $element = new self($element, $values);
        }

        if ($element->isEmpty()) {
            return $this;
        }

        if ($this->isEmpty()) {
            $this->transmute($element);

            return $this;
        }

        if ($this->hasElement()) {
            $this->stack = [new self($this->getElement(), $this->values), $element];
            $this->element = null;
            $this->values = [];
        } else {
            if ($this->operator == $operator) {
                $this->stack[] = $element;
            } else {
                $this->stack = [
                    self::create()
                        ->setStack($this->stack)
                        ->setOperator($this->operator),
                    $element
                ];
            }
        }

        $this->operator = $operator;

        return $this;
    }

    /** is it a fresh brand new object ? */
    public function isEmpty(): bool
    {
        return $this->element === null && count($this->stack) == 0;
    }

    /** Absorbing another Where instance. */
    private function transmute(Where $where): void
    {
        $this->stack = $where->stack;
        $this->element = $where->element;
        $this->operator = $where->operator;
        $this->values = $where->values;
    }

    public function hasElement(): bool
    {
        return $this->element !== null;
    }

    public function getElement(): string
    {
        return $this->element;
    }

    /**
     * is it an AND or an OR ? or something else.
     * XOR can be expressed as "A = !B"
     */
    public function setOperator(string $operator): Where
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * @param array<int, Where> $stack
     * @return Where
     */
    public function setStack(array $stack): Where
    {
        $this->stack = $stack;

        return $this;
    }

    /**
     * A constructor you can chain from.
     *
     * @param string|null $element
     * @param array<int, mixed> $values
     * @return Where
     */
    public static function create(?string $element = null, array $values = []): Where
    {
        return new self($element, $values);
    }

    /**
     * orWhere
     *
     * @param array<int, mixed> $values
     * @return Where
     */
    public function orWhere(mixed $element, array $values = []): Where
    {
        return $this->addWhere($element, $values, 'OR');
    }

    /** where your SQL statement is built. */
    public function __toString(): string
    {
        return $this->isEmpty() ? 'true' : $this->parse();
    }

    protected function parse(): string
    {
        if ($this->hasElement()) {
            return $this->getElement();
        }

        $stack = array_map(fn(Where $where): string => $where->parse(), $this->stack);

        return sprintf('(%s)', join(sprintf(' %s ', $this->operator), $stack));
    }

    /**
     * Get all the values back for the prepared statement.
     *
     * @return array<int, mixed>
     */
    public function getValues(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        if ($this->hasElement()) {
            return $this->values;
        }

        $values = [];

        foreach ($this->stack as $where) {
            $values[] = $where->getValues();
        }

        return call_user_func_array('array_merge', $values);
    }
}
