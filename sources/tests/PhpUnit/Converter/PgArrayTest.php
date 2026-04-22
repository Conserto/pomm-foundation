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
use PommProject\Foundation\Converter\PgArray;

#[CoversClass(PgArray::class)]
class PgArrayTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgArray();

        self::assertNull($converter->fromPg(null, 'int4', $session));
        self::assertSame([], $converter->fromPg('{}', 'int4', $session));
        self::assertSame([null], $converter->fromPg('{NULL}', 'int4', $session));
        self::assertSame([1, 2, 3, null], $converter->fromPg('{1,2,3,NULL}', 'int4', $session));
        self::assertSame(
            [1.634, 2.00001, 3.99999, null],
            $converter->fromPg('{1.634,2.00001,3.99999,NULL}', 'float4', $session)
        );
        self::assertSame(
            ['ab a', 'aba', 'a b a', null],
            $converter->fromPg('{"ab a",aba,"a b a",NULL}', 'varchar', $session)
        );
        self::assertSame(
            [true, true, false, null],
            $converter->fromPg('{t,t,f,NULL}', 'bool', $session)
        );
        self::assertEquals(
            [
                new \DateTime('2014-09-29 18:24:54.591767'),
                new \DateTime('2014-07-29 14:50:01'),
                new \DateTime('2012-12-14 04:17:09.063948'),
            ],
            $converter->fromPg(
                '{"2014-09-29 18:24:54.591767","2014-07-29 14:50:01","2012-12-14 04:17:09.063948"}',
                'timestamp',
                $session
            )
        );
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgArray();

        self::assertSame('NULL::int4[]', $converter->toPg(null, 'int4', $session));
        self::assertSame('ARRAY[NULL::int4]::int4[]', $converter->toPg([null], 'int4', $session));
        self::assertSame(
            "ARRAY[int4 '1',int4 '2',int4 '3',NULL::int4]::int4[]",
            $converter->toPg([1, 2, 3, null], 'int4', $session)
        );
        self::assertSame(
            "ARRAY[float4 '1.634',float4 '2',float4 '3.99999',NULL::float4]::float4[]",
            $converter->toPg([1.634, 2.000, 3.99999, null], 'float4', $session)
        );
        self::assertSame(
            "ARRAY[varchar '',varchar ' ab a',varchar 'aba',varchar 'a ''b'' a',NULL::varchar]::varchar[]",
            $converter->toPg(['', ' ab a', 'aba', 'a \'b\' a', null], 'varchar', $session)
        );
        self::assertSame(
            "ARRAY[bool 'true',bool 'true',bool 'false',NULL::bool]::bool[]",
            $converter->toPg([true, true, false, null], 'bool', $session)
        );
        // The original atoum test exercises the call without asserting the exact output — kept as-is.
        self::assertIsString(
            $converter->toPg(
                [
                    new \DateTime('2014-09-29 18:24:54.591767'),
                    new \DateTime('2014-07-29 14:50:01'),
                    new \DateTime('2012-12-14 04:17:09.063948'),
                ],
                'timestamp',
                $session
            )
        );
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgArray();

        self::assertNull($converter->toPgStandardFormat(null, 'int4', $session));
        self::assertSame('{NULL}', $converter->toPgStandardFormat([null], 'int4', $session));
        self::assertSame('{1,2,3,NULL}', $converter->toPgStandardFormat([1, 2, 3, null], 'int4', $session));
        self::assertSame(
            '{1.634,2,3.99999,NULL}',
            $converter->toPgStandardFormat([1.634, 2.000, 3.99999, null], 'float4', $session)
        );
        self::assertSame(
            '{""," ab a",aba,"a \"\\\\b\" a",NULL}',
            $converter->toPgStandardFormat(['', ' ab a', 'aba', "a \"\\b\" a", null], 'varchar', $session)
        );
        self::assertSame(
            '{t,t,f,NULL}',
            $converter->toPgStandardFormat([true, true, false, null], 'bool', $session)
        );
        self::assertSame(
            '{"2014-09-29 18:24:54.591767+02:00","2014-07-29 14:50:01.000000+02:00","2012-12-14 04:17:09.063948+01:00"}',
            $converter->toPgStandardFormat(
                [
                    new \DateTime('2014-09-29 18:24:54.591767+02:00'),
                    new \DateTime('2014-07-29 14:50:01+02:00'),
                    new \DateTime('2012-12-14 04:17:09.063948+01:00'),
                ],
                'timestamp',
                $session
            )
        );

        self::assertSame(
            [true, null, false],
            $this->sendToPostgres([true, null, false], 'bool[]', $session)
        );
        self::assertSame(
            [' a varchar ', 'another one with "\\quotes\\"', null],
            $this->sendToPostgres([' a varchar ', 'another one with "\\quotes\\"', null], 'varchar[]', $session)
        );
    }
}
