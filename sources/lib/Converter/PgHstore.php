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
        if ($data ===  null) {
            return null;
        }

        /** @var mixed $hstore */
        $hstore = null;
        @eval(sprintf("\$hstore = [%s];", $data));

        if (!is_array($hstore)) {
            throw new ConverterException(sprintf("Could not parse hstore string '%s' to array.", $data));
        }

        return $hstore;
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
