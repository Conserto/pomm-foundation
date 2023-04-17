<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Exception\FoundationException;

class PgLtree extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $this->array(
            $converter->fromPg('_a_b_.c.d', 'ltree', $session)
        )
            ->isIdenticalTo(['_a_b_', 'c', 'd'])
            ->variable($converter->fromPg(null, 'ltree', $session))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $this->string(
            $converter->toPg(['_a_b_', 'c', 'd'], 'ltree', $session)
        )
            ->isEqualTo("ltree '_a_b_.c.d'")
            ->string($converter->toPg(null, 'ltree', $session))
            ->isEqualTo('NULL::ltree');
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $this->string(
            $converter->toPgStandardFormat(['_a_b_', 'c', 'd'], 'ltree', $session)
        )
            ->isEqualTo('_a_b_.c.d')
            ->variable($converter->toPgStandardFormat(null, 'ltree', $session))
            ->isNull();
    }
}
