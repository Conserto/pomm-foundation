<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Inflector;

#[CoversClass(Inflector::class)]
class InflectorTest extends TestCase
{
    #[TestWith([null, null])]
    #[TestWith(['', ''])]
    #[TestWith(['one', 'One'])]
    #[TestWith(['oNe', 'One'])]
    #[TestWith(['one_two', 'OneTwo'])]
    #[TestWith(['one_tWo', 'OneTwo'])]
    #[TestWith(['two_three_four', 'TwoThreeFour'])]
    #[TestWith(['one__two', 'One_Two'])]
    #[TestWith(['one_2_three', 'One_2Three'])]
    #[TestWith(['one2_three', 'One2Three'])]
    #[TestWith(['_one', 'One'])]
    public function testStudlyCaps(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Inflector::studlyCaps($input));
    }

    #[TestWith([null, null])]
    #[TestWith(['', ''])]
    #[TestWith(['one', 'one'])]
    #[TestWith(['oneTwo', 'one_two'])]
    #[TestWith(['twoThreeFour', 'two_three_four'])]
    #[TestWith(['one_Two', 'one__two'])]
    #[TestWith(['one2Three', 'one2_three'])]
    #[TestWith(['one_2Three', 'one_2_three'])]
    #[TestWith(['One', 'one'])]
    public function testUnderscore(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Inflector::underscore($input));
    }
}
