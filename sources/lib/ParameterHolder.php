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

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\StringCoercion;

/**
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @implements \ArrayAccess<string, bool|string|array|null>
 * @implements \IteratorAggregate<string, bool|string|array|null>
 */
class ParameterHolder implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @param array<string, bool|string|array<mixed, mixed>|null> $parameters */
    public function __construct(protected array $parameters = [])
    {
    }

    /**
     * Throw an exception if a param is not set
     *
     * @param string $name the parameter's name
     * @return ParameterHolder $this
     * @throws FoundationException
     */
    public function mustHave(string $name): ParameterHolder
    {
        if (!$this->hasParameter($name)) {
            throw new FoundationException(sprintf('The parameter "%s" is mandatory.', $name));
        }

        return $this;
    }

    public function hasParameter(string $name): bool
    {
        return isset($this->parameters[$name]) || array_key_exists($name, $this->parameters);
    }

    /**
     * Sets a default value if the param $name is not set
     *
     * @param string $name the parameter's name
     * @param mixed $value the default value
     * @return ParameterHolder $this
     */
    public function setDefaultValue(string $name, mixed $value): ParameterHolder
    {
        if (!$this->hasParameter($name)) {
            // @phpstan-ignore argument.type (any default value: the union parameter type converts it, as it always did)
            $this->setParameter($name, $value);
        }

        return $this;
    }

    /**
     * @param bool|string|array<mixed, mixed>|null $value
     * @return $this
     */
    public function setParameter(string $name, bool|string|array|null $value): ParameterHolder
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * Check if the given parameter is one of the values passed as argument. If not, an exception is thrown.
     *
     * @throws FoundationException
     * @param string $name the parameter's name
     * @param array<mixed, mixed> $values
     * @return ParameterHolder $this
     */
    public function mustBeOneOf(string $name, array $values): ParameterHolder
    {
        if (!in_array($this[$name], $values)) {
            throw new FoundationException(
                // @phpstan-ignore argument.type (implode() converts each allowed value for the message, as it always did)
                sprintf('The parameters "%s" must be one of [%s].', $name, implode(', ', $values))
            );
        }

        return $this;
    }

    /** @see ArrayAccess */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasParameter($offset);
    }

    /**
     * @see ArrayAccess
     *
     * @return bool|array<mixed, mixed>|string|null
     */
    public function offsetGet(mixed $offset): bool|array|string|null
    {
        return $this->getParameter($offset);
    }

    /** Returns the parameter "name" or "default" if not set.
     *
     * @param bool|string|array<mixed, mixed>|null $default Optional default value if name not set.
     * @return bool|string|array<mixed, mixed>|null Parameter's value or default.
     */
    public function getParameter(string $name, bool|string|array|null $default = null): bool|string|array|null
    {
        return $this->hasParameter($name) ? $this->parameters[$name] : $default;
    }

    /**
     * Returns the parameter as a string. Scalars and Stringable objects are converted exactly as PHP's coercive
     * typing mode converts them for a string parameter (`1` gives "1", `true` gives "1").
     *
     * @throws FoundationException when the value cannot be converted (unset/null, array, other objects).
     */
    public function getString(string $name): string
    {
        $value = $this->getParameter($name);

        return StringCoercion::toStringOr($value, fn (): FoundationException => $this->invalidSetting($name, 'a string', $value));
    }

    /**
     * Returns the parameter as a boolean. Scalars are converted exactly as PHP's coercive typing mode converts them
     * for a bool parameter (`1` and "yes" give true, `0`, "0" and "" give false).
     *
     * @throws FoundationException when the value cannot be converted (unset/null, array).
     */
    public function getBool(string $name): bool
    {
        $value = $this->getParameter($name);

        if (!is_scalar($value)) {
            throw $this->invalidSetting($name, 'a boolean', $value);
        }

        return (bool) $value;
    }

    /**
     * Returns the parameter as an array.
     *
     * @throws FoundationException when the value is not an array (unset/null included).
     * @return array<mixed>
     */
    public function getArray(string $name): array
    {
        $value = $this->getParameter($name);

        if (!is_array($value)) {
            throw $this->invalidSetting($name, 'an array', $value);
        }

        return $value;
    }

    private function invalidSetting(string $name, string $expected, mixed $value): FoundationException
    {
        return new FoundationException(
            sprintf("Invalid '%s' setting: %s is expected, '%s' given.", $name, $expected, get_debug_type($value))
        );
    }

    /**
     * @see ArrayAccess
     *
     * @param bool|string|array<mixed, mixed>|null $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_scalar($offset)) {
            // Appending ($holder[] = …) used to end in a TypeError: parameters are named.
            throw new FoundationException(sprintf("A parameter name is required, '%s' given.", get_debug_type($offset)));
        }

        // Scalars are converted as the string parameter type converted them (no strict_types in this file).
        $this->setParameter((string) $offset, $value);
    }

    /** @see ArrayAccess */
    public function offsetUnset(mixed $offset): void
    {
        $this->unsetParameter($offset);
    }

    public function unsetParameter(string $name): ParameterHolder
    {
        unset($this->parameters[$name]);

        return $this;
    }

    /** @see \Countable */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * @see \IteratorAggregate
     *
     * @return \ArrayIterator<string, bool|string|array<mixed, mixed>|null>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->parameters);
    }
}
