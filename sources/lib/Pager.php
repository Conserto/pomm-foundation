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

/**
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @template-covariant T
 */
class Pager
{
    /**
     * @param ResultIterator<T> $iterator
     * @param int $count Total number of results.
     * @param int $maxPerPage Results per page
     * @param int $page Page index.
     */
    public function __construct(
        private readonly ResultIterator $iterator,
        protected int $count,
        protected int $maxPerPage,
        protected int $page
    ) {
    }

    /**
     * Return the Pager's iterator.
     *
     * @return ResultIterator<T>
     */
    public function getIterator(): ResultIterator
    {
        return $this->iterator;
    }

    /** Get the number of results in this page. */
    public function getResultCount(): int
    {
        return $this->iterator->count();
    }

    /** Get the index of the last element of this page. */
    public function getResultMax(): int
    {
        return max(($this->getResultMin() + $this->iterator->count() - 1), 0);
    }

    /** Get the index of the first element of this page. */
    public function getResultMin(): int
    {
        return min((1 + $this->maxPerPage * ($this->page - 1)), $this->count);
    }

    /** True if a next page exists. */
    public function isNextPage(): bool
    {
        return $this->getPage() < $this->getLastPage();
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /** Get the last page index. */
    public function getLastPage(): int
    {
        return $this->count == 0 ? 1 : (int) ceil($this->count / $this->maxPerPage);
    }

    /** True if a previous page exists. */
    public function isPreviousPage(): bool
    {
        return $this->page > 1;
    }

    /** Get the total number of results in all pages. */
    public function getCount(): int
    {
        return $this->count;
    }

    /** Get maximum result per page. */
    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }
}
