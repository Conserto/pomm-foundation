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

namespace PommProject\Foundation\Test\PhpUnit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\PgLtree;

#[CoversClass(PgLtree::class)]
class PgLtreeTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame(['_a_b_', 'c', 'd'], $converter->fromPg('_a_b_.c.d', 'ltree', $session));
        self::assertNull($converter->fromPg(null, 'ltree', $session));
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame("ltree '_a_b_.c.d'", $converter->toPg(['_a_b_', 'c', 'd'], 'ltree', $session));
        self::assertSame('NULL::ltree', $converter->toPg(null, 'ltree', $session));
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgLtree();

        self::assertSame('_a_b_.c.d', $converter->toPgStandardFormat(['_a_b_', 'c', 'd'], 'ltree', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'ltree', $session));
    }
}
