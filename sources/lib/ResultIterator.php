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

use PommProject\Foundation\Session\ResultHandler;

/**
 * Iterator on database results.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       \Iterator
 * @see       \Countable
 * @see       \JsonSerializable
 *
 * @template-covariant T
 * @template-implements \Iterator<int, T>
 * @template-implements \SeekableIterator<int, T>
 */
class ResultIterator implements \Iterator, \Countable, \JsonSerializable, \SeekableIterator
{
    private int $position = 0;
    private ?int $rowsCount = null;

    public function __construct(protected ResultHandler $result)
    {
    }

    /** Closes the cursor when the collection is cleared. */
    public function __destruct()
    {
        $this->result->free();
    }

    /** Alias for get(), required to be a Seekable iterator. */
    public function seek(int $offset): void
    {
        $this->get($offset); // throw exception if out of bounds
    }

    /**
     * Return a particular result. An array with converted values is returned.
     * pg_fetch_array is muted because it produces untrappable warnings on errors.
     *
     * @return mixed (not array to allow method to be overriden)
     */
    public function get(int $index): mixed
    {
        return $this->result->fetchRow($index);
    }

    /** Return true if the given index exists false otherwise. */
    public function has(int $index): bool
    {
        return $index < $this->count();
    }

    /** @see    \Countable */
    public function count(): int
    {
        if ($this->rowsCount == null) {
            $this->rowsCount = $this->result->countRows();
        }

        return $this->rowsCount;
    }

    /** @see \Iterator */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @see \Iterator
     * @return T|null
     */
    public function current(): mixed
    {
        return (($this->rowsCount != null && $this->rowsCount > 0 ) || !$this->isEmpty())
            ? $this->get($this->position)
            : null;
    }

    /** @see \Iterator */
    public function key(): int
    {
        return $this->position;
    }

    /** @see \Iterator */
    public function next(): void
    {
        ++$this->position;
    }

    /** @see \Iterator */
    public function valid(): bool
    {
        return $this->has($this->position);
    }

    /**
     * Is the iterator on the first element ?
     * Returns null if the iterator is empty.
     */
    public function isFirst(): ?bool
    {
        return !$this->isEmpty()
            ? $this->position === 0
            : null;
    }

    /**
     * Is the iterator on the last element ?
     * Returns null if the iterator is empty.
     */
    public function isLast(): ?bool
    {
        return !$this->isEmpty()
            ? $this->position === $this->count() - 1
            : null;
    }

    /** Is the collection empty (no element) ? */
    public function isEmpty(): bool
    {
        return $this->rowsCount === 0 || $this->count() === 0;
    }

    /** Is the iterator on an even position ? */
    public function isEven(): bool
    {
        return ($this->position % 2) === 0;
    }

    /** Is the iterator on an odd position ? */
    public function isOdd(): bool
    {
        return ($this->position % 2) === 1;
    }

    /**
     * Return 'odd' or 'even' depending on the element index position.
     * Useful to style list elements when printing lists to do
     * <li class="line_<?php $list->getOddEven() ?>">.
     */
    public function getOddEven(): string
    {
        return $this->position % 2 === 1 ? 'odd' : 'even';
    }

    /**
     * Extract an array of values for one column.
     *
     * @param string $field
     * @return array<int, mixed>
     */
    public function slice(string $field): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return $this->result->fetchColumn($field);
    }

    /**
     * Dump an iterator. This actually stores all the results in PHP allocated memory.
     * THIS MAY USE A LOT OF MEMORY.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(): array
    {
        $results = [];

        foreach ($this as $result) {
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @see \JsonSerializable
     *
     * @return array<int, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->extract();
    }
}
