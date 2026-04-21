<?php

/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Composite type converter.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ArrayTypeConverter
 */
class PgComposite extends ArrayTypeConverter
{
    /**
     * Takes the composite type structure as parameter. The structure is $name => $type.
     *
     * @param array<string, string> $structure structure definition.
     */
    public function __construct(protected array $structure)
    {
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     *
     * @param string $type
     * @param Session $session
     * @param string|null $data
     * @return array<string, mixed>|null
     */
    public function fromPg(?string $data, string $type, Session $session): ?array
    {
        if ($data === null) {
            return null;
        }

        $values = str_getcsv(stripcslashes(trim($data, '()')), escape: "\\");

        return $this->convertArrayFromPg(array_combine(array_keys($this->structure), $values), $session);
    }

    /**
     * @throws ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        $this->checkArray($data);

        return sprintf(
            "ROW(%s)::%s",
            join(',', $this->convertArrayToPg($data, $session)),
            $type
        );
    }

    /**
     * @throws ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        $this->checkArray($data);

        return
            sprintf(
                "(%s)",
                join(',', array_map(
                    fn (mixed $val): mixed => match (true) {
                        (null === $val) => '',
                        ('' === $val) => '""',
                        (bool) preg_match('/[,\s()]/', (string) $val) =>
                            sprintf('"%s"', str_replace('"', '""', $val)),
                        default => $val,
                    },
                    $this->convertArrayToPgStandardFormat($data, $session)
                ))
            );
    }

    /**
     * @throws FoundationException
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function convertArrayFromPg(array $data, Session $session): array
    {
        $values = [];

        foreach ($this->structure as $name => $subtype) {
            $values[$name] = $this
                ->getSubtypeConverter($subtype, $session)
                ->fromPg($data[$name] ?? null, $subtype, $session);
        }

        return $values;
    }

    /**
     * @throws FoundationException
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function convertArrayToPg(array $data, Session $session): array
    {
        $values = [];

        foreach ($this->structure as $name => $subtype) {
            $values[$name] = $this
                ->getSubtypeConverter($subtype, $session)
                ->toPg($data[$name] ?? null, $subtype, $session);
        }

        return $values;
    }

    /**
     * @throws FoundationException
     *
     * @param array<string, mixed> $data
     * @return array<string, ?string>
     */
    private function convertArrayToPgStandardFormat(array $data, Session $session): array
    {
        $values = [];

        foreach ($this->structure as $name => $subtype) {
            $values[$name] = $this
                ->getSubtypeConverter($subtype, $session)
                ->toPgStandardFormat($data[$name] ?? null, $subtype, $session);
        }

        return $values;
    }
}
