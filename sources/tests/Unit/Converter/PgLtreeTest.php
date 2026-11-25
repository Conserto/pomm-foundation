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

namespace PommProject\Foundation\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\PgLtree;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;

#[CoversClass(PgLtree::class)]
final class PgLtreeTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame(['_a_b_', 'c', 'd'], $converter->fromPg('_a_b_.c.d', 'ltree', $session));
        self::assertNull($converter->fromPg(null, 'ltree', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame("ltree '_a_b_.c.d'", $converter->toPg(['_a_b_', 'c', 'd'], 'ltree', $session));
        self::assertSame('NULL::ltree', $converter->toPg(null, 'ltree', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame('_a_b_.c.d', $converter->toPgStandardFormat(['_a_b_', 'c', 'd'], 'ltree', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'ltree', $session));
    }

    /**
     * A label holding a quote is escaped: it used to end the literal and inject SQL.
     *
     * @throws FoundationException
     */
    public function testToPgEscapesLabels(): void
    {
        $session = $this->buildSession();
        $sql = new PgLtree()->toPg(['a', "b'); select 1; --"], 'ltree', $session);

        self::assertSame("ltree 'a.b''); select 1; --'", $sql);
        $this->expectException(SqlException::class);
        $this->expectExceptionMessage('ltree syntax error');
        $session->getConnection()->executeAnonymousQuery('select ' . $sql);
    }
}
