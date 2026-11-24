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

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session as BaseSession;

/**
 * Iterator on converted results.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ResultIterator
 *
 * @template T = array<string, mixed>
 * @extends ResultIterator<T>
 */
class ConvertedResultIterator extends ResultIterator
{
    /** @var array<string, string> */
    protected array $types = [];

    /** @var array<string, ConverterClient> */
    protected array $converters = [];

    /** @throws FoundationException */
    public function __construct(ResultHandler $result, protected BaseSession $session)
    {
        parent::__construct($result);
        $this->initTypes();
    }

    /**
     * Return a particular result. An array with converted values is returned.
     * pg_fetch_array is muted because it produces untrappable warnings on errors.
     *
     * @return array<string, mixed>
     */
    public function get(int $index): array
    {
        // Same as parent::get(), with the row type ResultHandler::fetchRow() documents.
        return $this->parseRow($this->result->fetchRow($index));
    }

    /**
     * Get the result types from the result handler.
     * @throws FoundationException
     *
     * @return ResultIterator<T>
     */
    protected function initTypes(): ResultIterator
    {
        foreach ($this->result->getFieldNames() as $name) {
            $type = $this->result->getFieldType($name);

            $type ??= 'text';

            $this->types[$name] = $type;

            /** @var ConverterClient $converter */
            $converter = $this->session->getClientUsingPooler('converter', $type);
            $this->converters[$name] = $converter;
        }

        return $this;
    }

    /**
     * Convert values from Pg.
     *
     * @param array<array-key, ?string> $values (PHP turns numeric column names into int keys)
     * @return array<string, mixed>
     */
    protected function parseRow(array $values): array
    {
        $outputValues = [];

        foreach ($values as $name => $value) {
            $outputValues[$name] = $this->convertField($name, $value) ;
        }

        // @phpstan-ignore return.type (numeric column names become int keys: rows are documented as string-keyed)
        return $outputValues;
    }

    /** Return converted value for a result field. */
    protected function convertField(string $name, ?string $value): mixed
    {
        return $this->converters[$name]->fromPg($value, $this->types[$name]);
    }

    /**
     * see @ResultIterator
     *
     * @return array<int, mixed>
     */
    public function slice(string $field): array
    {
        $values = [];
        // Same as parent::slice(), with the column type ResultHandler::fetchColumn() documents.
        foreach ($this->isEmpty() ? [] : $this->result->fetchColumn($field) as $value) {
            $values[] = $this->convertField($field, $value);
        }

        return $values;
    }
}
