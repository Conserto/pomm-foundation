<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Session;

use PgSql\Result as PgSqlResult;
use PommProject\Foundation\Exception\FoundationException;

/**
 * Wrap a PostgreSQL query result resource.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ResultHandler
{
    public function __construct(protected ?PgSqlResult $handler)
    {
    }

    /** Call free() if handler is set. */
    public function __destruct()
    {
        $this->free();
    }

    /** Free a result from memory. */
    public function free(): ResultHandler
    {
        if ($this->handler instanceof PgSqlResult) {
            pg_free_result($this->handler);
            $this->handler = null;
        }

        return $this;
    }

    /**
     * Fetch a row as associative array. Index starts from 0.
     *
     * @return array<array-key, string|null> column name => raw value (PHP turns numeric column names into int keys)
     * @throws \OutOfBoundsException if $index out of bounds.
     */
    public function fetchRow(int $index): array
    {
        $values = @pg_fetch_assoc($this->getResult(), $index);

        if ($values === false) {
            throw new \OutOfBoundsException(sprintf("Cannot jump to non existing row %d.", $index));
        }

        return $values;
    }

    /** Return the number of fields of a result. */
    public function countFields(): int
    {
        return pg_num_fields($this->getResult());
    }

    /** Return the number of rows in a result. */
    public function countRows(): int
    {
        return pg_num_rows($this->getResult());
    }

    /** Return the number of affected rows in a result. */
    public function countAffectedRows(): int
    {
        return pg_affected_rows($this->getResult());
    }

    /**
     * Return an array with the field names of a result.
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array
    {
        $names = [];

        for ($i = 0; $i < $this->countFields(); $i++) {
            $names[] = $this->getFieldName($i);
        }

        return $names;
    }

    /** Return the associated type of field.*/
    public function getFieldType(string $name): ?string
    {
        $type = pg_field_type($this->getResult(), $this->getFieldNumber($name));

        return $type !== 'unknown' ? $type : null;
    }

    /**
     * Return the name from a field number.
     *
     * @throws \InvalidArgumentException
     */
    public function getFieldName(int $fieldNo): string
    {
        return pg_field_name($this->getResult(), $fieldNo);
    }

    /** Return the field index from its name. */
    protected function getFieldNumber(string $name): int
    {
        $no = pg_field_num($this->getResult(), "\"$name\"");

        if ($no === -1) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Could not find field name '%s'. Available fields are {%s}.",
                    $name,
                    implode(', ', $this->getFieldNames())
                )
            );
        }

        return $no;
    }

    /**
     * Fetch a column from a result.
     *
     * @return array<int, string|null>
     */
    public function fetchColumn(string $name): array
    {
        return pg_fetch_all_columns($this->getResult(), $this->getFieldNumber($name));
    }

    /** Check if a field exist or not. */
    public function fieldExist(mixed $name): bool
    {
        // @phpstan-ignore argument.type (fieldExist() accepts any value, converted by pg_field_num(), as it always was)
        return pg_field_num($this->getResult(), $name) > -1;
    }

    /** Return the type oid of the given field. */
    public function getTypeOid(int $field): ?int
    {
        // pg_field_type_oid() only returns a string for oids above PHP_INT_MAX (32-bit builds); the return type
        // already converted it to int.
        return (int) pg_field_type_oid($this->getResult(), $field);
    }

    /** @throws FoundationException if the result has already been freed. */
    private function getResult(): PgSqlResult
    {
        if (!$this->handler instanceof PgSqlResult) {
            // Using a freed result used to end in a TypeError.
            throw new FoundationException('The query result has already been freed.');
        }

        return $this->handler;
    }
}
