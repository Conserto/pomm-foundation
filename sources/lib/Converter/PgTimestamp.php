<?php

declare(strict_types=1);

/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgText;
use PommProject\Foundation\Internal\StringCoercion;
use PommProject\Foundation\Session\Session;

/**
 * Date and timestamp converter
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgTimestamp implements ConverterInterface
{
    final public const string TS_FORMAT = 'Y-m-d H:i:s.uP';

    /**
     * @throws \Exception
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): ?\DateTime
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        if ($data === '') {
            return null;
        }

        try {
            return new \DateTime($data);
        } catch (\Exception $e) {
            // e.g. 'infinity' / '-infinity', which a DateTime cannot hold.
            throw new ConverterException(sprintf("Cannot convert PostgreSQL value '%s' to a DateTime.", $data), 0, $e);
        }
    }

    /**
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        $text = $this->passedAsText($data, $type);

        return $text !== null
            ? sprintf("%s %s", $type, $session->getConnection()->escapeLiteral($text))
            : sprintf("%s '%s'", $type, $this->checkData($data)->format(self::TS_FORMAT));
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return
            $data !== null
            ? $this->passedAsText($data, $type) ?? $this->checkData($data)->format(self::TS_FORMAT)
            : null;
    }

    /**
     * Ensure a DateTime instance.
     *
     * @throws ConverterException
     */
    protected function checkData(mixed $data): \DateTimeInterface
    {
        if (!$data instanceof \DateTimeInterface) {
            // Converted as PHP's coercive mode converted it for DateTime's string parameter (null meant '', i.e. now);
            // it raised a TypeError otherwise.
            $datetime = $data === null ? '' : StringCoercion::toStringOr($data, fn (): ConverterException => new ConverterException(sprintf(
                "Cannot convert data from invalid datetime representation '%s'.",
                get_debug_type($data)
            )));

            try {
                $data = new \DateTime($datetime);
            } catch (\Exception $e) {
                throw new ConverterException(
                    sprintf(
                        "Cannot convert data from invalid datetime representation '%s'.",
                        $datetime
                    ),
                    0,
                    $e
                );
            }
        }

        return $data;
    }

    /**
     * Strings are passed unchanged, PostgreSQL accepts or rejects them by its own rules ('2024-01-02', '19940623',
     * 'now', 'infinity' pass; '', '2024-02-30', '+1 day', '12.7' raise an error), where PHP's DateTime used to turn
     * them silently into now or another date. Null for DateTimeInterface objects and the other values, formatted by
     * checkData().
     *
     * @throws ConverterException for booleans and numbers (PostgreSQL does not store them into a date column; false
     * used to become now).
     */
    private function passedAsText(mixed $data, string $type): ?string
    {
        // Checked first: a DateTime subclass with __toString() (Carbon...) is Stringable, its string form may lose the
        // time zone or the microseconds.
        if ($data instanceof \DateTimeInterface) {
            return null;
        }

        $text = PgText::of($data);
        if ($text === null && is_scalar($data)) {
            throw new ConverterException(sprintf("Cannot convert a '%s' value to %s.", get_debug_type($data), $type));
        }

        return $text;
    }
}
