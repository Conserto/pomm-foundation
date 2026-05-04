<?php

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;

/**
 * Shared entry point to unwrap PHP enums into scalar values usable by the
 * primitive Pg* converters. Each consumer only declares which enums it
 * {@see acceptsEnum() accepts}; the trait handles value extraction and
 * rejection.
 */
trait UnwrapsEnum
{
    /** @throws ConverterException */
    private function unwrapEnum(mixed $data, string $type): mixed
    {
        return $data instanceof \UnitEnum
            ? $this->convertEnumValue($data, $type)
            : $data;
    }

    /** @throws ConverterException */
    protected function convertEnumValue(\UnitEnum $enum, string $type): string|int
    {
        if (!$this->acceptsEnum($enum)) {
            throw new ConverterException(sprintf(
                "Enum '%s' is not compatible with Pg type '%s'.",
                $enum::class,
                $type
            ));
        }

        return $enum instanceof \BackedEnum ? $enum->value : $enum->name;
    }

    /** Whether this converter accepts the given enum instance. */
    abstract protected function acceptsEnum(\UnitEnum $enum): bool;
}
