<?php

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Session\Session;

/**
 * Converter for BackedEnum types (>= PHP 8.1).
 *
 * @copyright   2023 Tovski
 * @author      Tovski
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ConverterInterface
 */
class PgBackedEnum implements ConverterInterface
{
    /**
     * @throws ConverterException
     *
     * @param class-string $enumFqcn
     */
    public function __construct(private readonly string $enumFqcn)
    {
        if (!enum_exists($this->enumFqcn) || !is_a($this->enumFqcn, \BackedEnum::class, true)) {
            throw new ConverterException(sprintf('BackedEnum "%s" does not exist', $this->enumFqcn));
        }
    }

    /** @throws ConverterException */
    public function fromPg(?string $data, string $type, Session $session): ?\BackedEnum
    {
        if (null === $data) {
            return null;
        }

        $enum = $this->enumFqcn::tryFrom($data);
        if (null === $enum) {
            throw new ConverterException(sprintf('Value "%s" not found in BackedEnum "%s"', $data, $this->enumFqcn));
        }

        return $enum;
    }

    /** @throws ConverterException */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        if (!$data instanceof $this->enumFqcn) {
            throw new ConverterException(sprintf(
                'Expected instance of "%s", got "%s".',
                $this->enumFqcn,
                get_debug_type($data)
            ));
        }

        return (string) $data->value;
    }

    /** @throws ConverterException */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $this->toPg($data, $type, $session);
    }
}
