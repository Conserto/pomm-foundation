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
use PommProject\Foundation\Session\Session;

/**
 * HStore converter
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ArrayTypeConverter
 */
class PgHstore extends ArrayTypeConverter
{
    /**
     * @throws ConverterException
     * @see \Pomm\Converter\ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): mixed
    {
        if ($data === null) {
            return null;
        }

        return $this->parseHstore($data);
    }

    /**
     * Parse the Postgres hstore text representation into an array.
     *
     * Postgres emits pairs of the form `"key"=>value` separated by `, `, where
     * the key is always quoted and the value is either the unquoted literal
     * `NULL` or a double-quoted string. Inside quoted strings the only escapes
     * are `\"` (a literal double quote) and `\\` (a literal backslash).
     *
     * @throws ConverterException
     *
     * @return array<string, ?string>
     */
    private function parseHstore(string $data): array
    {
        $length = strlen($data);
        $result = [];
        $pos = 0;

        while ($pos < $length) {
            while ($pos < $length && $data[$pos] === ' ') {
                $pos++;
            }
            if ($pos >= $length) {
                break;
            }

            if ($data[$pos] !== '"') {
                throw $this->parseError($data);
            }
            $pos++;
            $key = $this->readQuotedString($data, $pos);

            while ($pos < $length && $data[$pos] === ' ') {
                $pos++;
            }
            if ($pos + 1 >= $length || $data[$pos] !== '=' || $data[$pos + 1] !== '>') {
                throw $this->parseError($data);
            }
            $pos += 2;
            while ($pos < $length && $data[$pos] === ' ') {
                $pos++;
            }

            if ($pos + 4 <= $length && strcasecmp(substr($data, $pos, 4), 'NULL') === 0) {
                $result[$key] = null;
                $pos += 4;
            } elseif ($pos < $length && $data[$pos] === '"') {
                $pos++;
                $result[$key] = $this->readQuotedString($data, $pos);
            } else {
                throw $this->parseError($data);
            }

            while ($pos < $length && $data[$pos] === ' ') {
                $pos++;
            }
            if ($pos < $length) {
                if ($data[$pos] !== ',') {
                    throw $this->parseError($data);
                }
                $pos++;
            }
        }

        return $result;
    }

    /**
     * Read a quoted-string body starting at $pos (after the opening `"`).
     * Advances $pos past the closing `"`. Handles `\"` and `\\` escapes.
     *
     * @throws ConverterException
     */
    private function readQuotedString(string $data, int &$pos): string
    {
        $length = strlen($data);
        $out = '';

        while ($pos < $length && $data[$pos] !== '"') {
            if ($data[$pos] === '\\' && $pos + 1 < $length) {
                $out .= $data[$pos + 1];
                $pos += 2;
            } else {
                $out .= $data[$pos];
                $pos++;
            }
        }

        if ($pos >= $length) {
            throw $this->parseError($data);
        }

        $pos++;

        return $out;
    }

    private function parseError(string $data): ConverterException
    {
        return new ConverterException(sprintf("Could not parse hstore string '%s' to array.", $data));
    }

    /**
     * @throws ConverterException
     * @see \Pomm\Converter\ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        return sprintf("%s(\$hs\$%s\$hs\$)", $type, join(', ', $this->buildArray($this->checkArray($data))));
    }

    /**
     * @throws ConverterException
     * @see \Pomm\Converter\ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        return sprintf('%s', join(', ', $this->buildArray($this->checkArray($data))));
    }

    /**
     * Return an array of HStore elements.
     *
     * @param  array<string, null|string> $data
     * @return array<int, string>
     */
    protected function buildArray(array $data): array
    {
        $insertValues = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                $insertValues[] = sprintf('%s => NULL', $this->escape($key));
            } else {
                $insertValues[] = sprintf(
                    '%s => %s',
                    $this->escape($key),
                    $this->escape($value)
                );
            }
        }

        return $insertValues;
    }

    /** Escape a string. */
    protected function escape(string $string): string
    {
        if (preg_match('/["\s,]/', $string) === false) {
            return addslashes($string);
        } else {
            return sprintf('"%s"', addcslashes($string, '"\\'));
        }
    }
}
