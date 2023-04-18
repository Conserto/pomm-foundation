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

/**
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @implements \ArrayAccess<string,bool|string|array|null>
 * @implements \IteratorAggregate<string,bool|string|array|null>
 */
class ParameterHolder implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @param array<string,bool|string|array<mixed,mixed>> $parameters */
    public function __construct(protected array $parameters = [])
    {
    }

    /**
     * Throw an exception if a param is not set
     *
     * @param string $name the parameter's name
     * @return ParameterHolder $this
     * @throws  FoundationException
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
            $this->setParameter($name, $value);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param bool|string|array<mixed,mixed>|null $value
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
     * @throws  FoundationException
     * @param string $name the parameter's name
     * @param array<mixed,mixed> $values
     * @return ParameterHolder $this
     */
    public function mustBeOneOf(string $name, array $values): ParameterHolder
    {
        if (!in_array($this[$name], $values)) {
            throw new FoundationException(
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
     * @param mixed $offset
     * @return bool|array<mixed,mixed>|string|null
     */
    public function offsetGet(mixed $offset): bool|array|string|null
    {
        return $this->getParameter($offset);
    }

    /** Returns the parameter "name" or "default" if not set.
     *
     * @param string $name
     * @param bool|string|array<mixed,mixed>|null $default Optional default value if name not set.
     * @return bool|string|array<mixed,mixed>|null Parameter's value or default.
     */
    public function getParameter(string $name, bool|string|array|null $default = null): bool|string|array|null
    {
        return $this->hasParameter($name) ? $this->parameters[$name] : $default;
    }

    /**
     * @see ArrayAccess
     *
     * @param mixed $offset
     * @param bool|string|array<mixed,mixed>|null $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setParameter($offset, $value);
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
     * @return \ArrayIterator<string,bool|string|array<mixed,mixed>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->parameters);
    }
}
