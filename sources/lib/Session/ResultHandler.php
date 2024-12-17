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
        if ($this->handler !== null) {
            pg_free_result($this->handler);
            $this->handler = null;
        }

        return $this;
    }

    /**
     * Fetch a row as associative array. Index starts from 0.
     *
     * @param int $index
     * @return array<string, mixed>
     * @throws \OutOfBoundsException if $index out of bounds.
     */
    public function fetchRow(int $index): array
    {
        $values = @pg_fetch_assoc($this->handler, $index);

        if ($values === false) {
            throw new \OutOfBoundsException(sprintf("Cannot jump to non existing row %d.", $index));
        }

        return $values;
    }

    /** Return the number of fields of a result. */
    public function countFields(): int
    {
        return pg_num_fields($this->handler);
    }

    /** Return the number of rows in a result. */
    public function countRows(): int
    {
        return pg_num_rows($this->handler);
    }

    /** Return the number of affected rows in a result. */
    public function countAffectedRows(): int
    {
        return pg_affected_rows($this->handler);
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
        $type = pg_field_type($this->handler, $this->getFieldNumber($name));

        return $type !== 'unknown' ? $type : null;
    }

    /**
     * Return the name from a field number.
     *
     * @throws \InvalidArgumentException
     */
    public function getFieldName(int $fieldNo): string
    {
        return pg_field_name($this->handler, $fieldNo);
    }

    /** Return the field index from its name. */
    protected function getFieldNumber(string $name): int
    {
        $no = pg_field_num($this->handler, "\"$name\"");

        if ($no ===  -1) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Could not find field name '%s'. Available fields are {%s}.",
                    $name,
                    join(', ', array_keys(pg_fetch_assoc($this->handler))))
            );
        }

        return $no;
    }

    /**
     * Fetch a column from a result.
     *
     * @param string $name
     * @return array<int, mixed>
     */
    public function fetchColumn(string $name): array
    {
        return pg_fetch_all_columns($this->handler, $this->getFieldNumber($name));
    }

    /** Check if a field exist or not. */
    public function fieldExist(mixed $name): bool
    {
        return pg_field_num($this->handler, $name) > -1;
    }

    /** Return the type oid of the given field. */
    public function getTypeOid(int $field): ?int
    {
        return  pg_field_type_oid($this->handler, $field);
    }
}
