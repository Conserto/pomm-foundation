<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Converter between a PostgreSQL enum type and a PHP enum.
 *
 * A label maps to the case with the same value for a backed enum, to the case with the same name for a pure enum,
 * as Foundation does everywhere it turns an enum into a value. When writing, the raw value (backing value or case
 * name) is accepted as well as the case.
 *
 * @copyright   2023 Tovski (original PgBackedEnum)
 * @author      Tovski
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ConverterInterface
 */
class PgEnum implements ConverterInterface
{
    /**
     * Cases by PostgreSQL label, built on first use.
     *
     * @var array<string, \UnitEnum>|null
     */
    private ?array $cases = null;

    /**
     * @param class-string<\UnitEnum> $enumFqcn
     *
     * @throws ConverterException
     */
    public function __construct(private readonly string $enumFqcn)
    {
        if (!enum_exists($this->enumFqcn)) {
            throw new ConverterException(sprintf('Enum "%s" does not exist.', $this->enumFqcn));
        }
    }

    /** @throws ConverterException */
    public function fromPg(?string $data, string $type, Session $session): ?\UnitEnum
    {
        if (null === $data) {
            return null;
        }

        return $this->findCase($data) ?? throw $this->notFound($data);
    }

    /** @throws ConnectionException|ConverterException|FoundationException */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        $value = $this->toText($data);

        return $value !== null
            ? sprintf('%s %s', $type, $session->getConnection()->escapeLiteral($value))
            : sprintf('NULL::%s', $type);
    }

    /** @throws ConverterException */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $this->toText($data);
    }

    /**
     * Accept a case of the enum, or its raw value (backing value or case name, as a string, an int or a Stringable)
     * so that code writing raw values keeps working.
     *
     * @throws ConverterException when $data is neither null, a case of the enum, nor the raw value of one
     */
    private function toText(mixed $data): ?string
    {
        if ($data === null) {
            return null;
        }

        if ($data instanceof $this->enumFqcn) {
            $case = $data;
        } elseif (is_string($data) || is_int($data) || $data instanceof \Stringable) {
            $case = $this->findCase((string) $data) ?? throw $this->notFound((string) $data);
        } else {
            throw new ConverterException(sprintf(
                'Expected a case or a value of enum "%s", got "%s".',
                $this->enumFqcn,
                get_debug_type($data)
            ));
        }

        return $this->labelOf($case);
    }

    /**
     * Find the case of a raw value: the case name for a pure enum, the backing value for a backed one. A value matches
     * only in the form the case is written in ('2' or 2 for the int value 2, not '02' or '2.0'), so that every label
     * read can be written back.
     */
    private function findCase(string|int $value): ?\UnitEnum
    {
        if ($this->cases === null) {
            $this->cases = [];

            foreach ($this->enumFqcn::cases() as $case) {
                $this->cases[$this->labelOf($case)] = $case;
            }
        }

        return $this->cases[(string) $value] ?? null;
    }

    /** The PostgreSQL label of a case: its backing value for a backed enum, its name otherwise. */
    private function labelOf(\UnitEnum $case): string
    {
        return $case instanceof \BackedEnum ? (string) $case->value : $case->name;
    }

    private function notFound(string|int $value): ConverterException
    {
        return new ConverterException(sprintf('Value "%s" not found in enum "%s".', $value, $this->enumFqcn));
    }
}
