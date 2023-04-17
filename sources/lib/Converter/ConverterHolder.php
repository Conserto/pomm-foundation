<?php
/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;

/**
 * Responsible for holding all converters associated with their corresponding types.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ConverterHolder
{
    /** @var array<string, ConverterInterface> */
    protected array $converters = [];

    /** @var array<string, string> */
    protected array $types = [];

    /**
     * Declare a converter and assign types to it.
     *
     * @throws ConverterException
     * @param ConverterInterface $converter
     * @param array<int, string> $types
     * @param bool|null $strict
     * @return ConverterHolder
     * @param string $name
     */
    public function registerConverter(
        string $name,
        ConverterInterface $converter,
        array $types,
        bool $strict = null
    ): ConverterHolder {
        $this->addConverter($name, $converter, $strict);

        foreach ($types as $type) {
            $this->addTypeToConverter($name, $type);
        }

        return $this;
    }

    /**
     * Add a converter with a new name. If strict is set to true and the converter for this type has already been
     * registered, then it throws and exception.
     *
     * @throws ConverterException if $name already exists and strict.
     */
    protected function addConverter(string $name, ConverterInterface $converter, bool $strict = true): ConverterHolder
    {
        if ($strict && $this->hasConverterName($name)) {
            throw new ConverterException(
                sprintf(
                    "A converter named '%s' already exists. (Known converters are {%s}).",
                    $name,
                    join(', ', $this->getConverterNames())
                )
            );
        }

        $this->converters[$name] = $converter;

        return $this;
    }

    /** Tell if the converter exists or not. */
    public function hasConverterName(string $name): bool
    {
        return isset($this->converters[$name]);
    }

    /** Return the converter associated with this name. If no converters found, NULL is returned. */
    public function getConverter(string $name): ?ConverterInterface
    {
        return $this->hasConverterName($name) ? $this->converters[$name] : null;
    }

    /**
     * Returns an array with the names of the registered converters.
     *
     * @return array<int, string>
     */
    public function getConverterNames(): array
    {
        return array_keys($this->converters);
    }

    /**
     * Make the given converter to support a new PostgreSQL type. If the given type is already defined, it is overrided
     * with the new converter.
     *
     * @throws  ConverterException if $name does not exist.
     */
    public function addTypeToConverter(string $name, string $type): ConverterHolder
    {
        if (!$this->hasConverterName($name)) {
            throw new ConverterException(
                sprintf(
                    "No such converter name '%s'. Registered converters are {%s}.",
                    $name,
                    join(', ', $this->getConverterNames())
                )
            );
        }

        $this->types[$type] = $name;

        return $this;
    }

    /**
     * Returns the converter instance for the given type.
     *
     * @throws  ConverterException if there are no converters associated.
     */
    public function getConverterForType(string $type): ConverterInterface
    {
        if (!$this->hasType($type)) {
            throw new ConverterException(
                sprintf(
                    "No converters associated with type '%s'. Handled types are {%s}.",
                    $type,
                    join(', ', $this->getTypes())
                )
            );
        }

        return $this->converters[$this->types[$type]];
    }

    /** Does the type exist ? */
    public function hasType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * Return the list of handled types.
     *
     * @return array<int, string>
     */
    public function getTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * Return the list of types with the related converter name.
     *
     * @return array<string, string>
     */
    public function getTypesWithConverterName(): array
    {
        return $this->types;
    }
}
