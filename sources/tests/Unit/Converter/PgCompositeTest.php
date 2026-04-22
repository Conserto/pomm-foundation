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
use PommProject\Foundation\Converter\PgComposite;
use PommProject\Foundation\Session\Session;

#[CoversClass(PgComposite::class)]
class PgCompositeTest extends BaseConverterTestCase
{
    private const array STRUCTURE = ['a' => 'int4', 'b' => 'varchar[]', 'c' => 'text'];

    protected function setUp(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery('create type test_type as (a int4, b varchar[], c text)');
    }

    protected function tearDown(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery('drop type test_type cascade');
    }

    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgComposite(self::STRUCTURE);

        self::assertSame(
            ['a' => 3, 'b' => ['pika', 'chu'], 'c' => 'close)'],
            $converter->fromPg('(3,"{pika,chu}","close)")', 'test_type', $session)
        );
        self::assertNull($converter->fromPg(null, 'test_type', $session));
        self::assertSame(
            ['a' => null, 'b' => [], 'c' => ''],
            $converter->fromPg('(,{},)', 'test_type', $session)
        );
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgComposite(self::STRUCTURE);

        self::assertSame(
            "ROW(int4 '3',ARRAY[varchar 'pika',varchar 'chu']::varchar[],text 'close)')::test_type",
            $converter->toPg(['a' => 3, 'b' => ['pika', 'chu'], 'c' => 'close)'], 'test_type', $session)
        );
        self::assertSame(
            'ROW(NULL::int4,ARRAY[]::varchar[],NULL::text)::test_type',
            $converter->toPg(['a' => null, 'b' => []], 'test_type', $session)
        );
        self::assertSame('NULL::test_type', $converter->toPg(null, 'test_type', $session));
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgComposite(self::STRUCTURE);
        $data = ['a' => 3, 'b' => ['pika', 'chu'], 'c' => 'close)'];

        self::assertSame(
            '(3,"{pika,chu}","close)")',
            $converter->toPgStandardFormat($data, 'test_type', $session)
        );
        self::assertSame(
            '(,{},)',
            $converter->toPgStandardFormat(['a' => null, 'b' => [], 'c' => null], 'test_type', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'test_type', $session));
        self::assertSame($data, $this->sendToPostgres($data, 'test_type', $session));
    }

    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $session->getPoolerForType('converter')
            ->getConverterHolder()
            ->registerConverter('MyComposite', new PgComposite(self::STRUCTURE), ['test_type']);
    }
}
