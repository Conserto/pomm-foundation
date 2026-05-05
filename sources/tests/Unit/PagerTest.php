<?php

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Pager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;

#[CoversClass(Pager::class)]
class PagerTest extends FoundationSessionTestCase
{
    /**
     * @throws FoundationException from buildSession() / QueryManager::query()
     */
    public function testGetters(): void
    {
        $iterator = $this->getResultIterator(10);
        $pager = new Pager($iterator, 100, 10, 1);

        self::assertInstanceOf(ConvertedResultIterator::class, $pager->getIterator());
        self::assertSame($iterator, $pager->getIterator());
        self::assertSame(100, $pager->getCount());
        self::assertSame($iterator->count(), $pager->getResultCount());
        self::assertSame(1, $pager->getPage());
    }

    /**
     * @param array{start: int, end: int, totalCount: int, maxPerPage: int, page: int} $input
     * @param array{min: int, max: int, lastPage: int, maxPerPage: int, previous: bool, next: bool} $expected
     *
     * @throws FoundationException from buildSession() / QueryManager::query()
     */
    #[DataProvider('pageStatsProvider')]
    public function testPageStats(array $input, array $expected): void
    {
        $iterator = $this->getResultIterator($input['end'], $input['start']);
        $pager = new Pager($iterator, $input['totalCount'], $input['maxPerPage'], $input['page']);

        self::assertSame($expected['min'], $pager->getResultMin());
        self::assertSame($expected['max'], $pager->getResultMax());
        self::assertSame($expected['lastPage'], $pager->getLastPage());
        self::assertSame($expected['maxPerPage'], $pager->getMaxPerPage());
        self::assertSame($expected['previous'], $pager->isPreviousPage());
        self::assertSame($expected['next'], $pager->isNextPage());
    }

    /**
     * @return iterable<string, array{
     *     input: array{start: int, end: int, totalCount: int, maxPerPage: int, page: int},
     *     expected: array{min: int, max: int, lastPage: int, maxPerPage: int, previous: bool, next: bool},
     * }>
     */
    public static function pageStatsProvider(): iterable
    {
        yield 'single page, partial' => [
            'input' => ['start' => 1, 'end' => 3, 'totalCount' => 3, 'maxPerPage' => 10, 'page' => 1],
            'expected' => ['min' => 1, 'max' => 3, 'lastPage' => 1, 'maxPerPage' => 10, 'previous' => false, 'next' => false],
        ];
        yield 'single page, full' => [
            'input' => ['start' => 1, 'end' => 10, 'totalCount' => 10, 'maxPerPage' => 10, 'page' => 1],
            'expected' => ['min' => 1, 'max' => 10, 'lastPage' => 1, 'maxPerPage' => 10, 'previous' => false, 'next' => false],
        ];
        yield 'just over one page' => [
            'input' => ['start' => 11, 'end' => 11, 'totalCount' => 11, 'maxPerPage' => 10, 'page' => 2],
            'expected' => ['min' => 11, 'max' => 11, 'lastPage' => 2, 'maxPerPage' => 10, 'previous' => true, 'next' => false],
        ];
        yield 'empty iterator' => [
            'input' => ['start' => 1, 'end' => 0, 'totalCount' => 0, 'maxPerPage' => 10, 'page' => 1],
            'expected' => ['min' => 0, 'max' => 0, 'lastPage' => 1, 'maxPerPage' => 10, 'previous' => false, 'next' => false],
        ];
    }

    protected function initializeSession(Session $session): void
    {
    }

    /**
     * @return ConvertedResultIterator<array{row: int}>
     *
     * @throws FoundationException from buildSession() / QueryManager::query()
     */
    private function getResultIterator(int $end, int $start = 1): ConvertedResultIterator
    {
        return $this->buildSession()
            ->getQueryManager()
            ->query('SELECT generate_series($*::int4, $*::int4) AS row', [$start, $end]);
    }
}
