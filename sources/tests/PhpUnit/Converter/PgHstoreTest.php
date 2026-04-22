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
use PommProject\Foundation\Converter\PgHstore;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Session\Session;

#[CoversClass(PgHstore::class)]
class PgHstoreTest extends BaseConverterTestCase
{
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgHstore();

        self::assertSame(
            ['a' => 'b', 'b' => null, 'a \\b\\ c' => 'd \'é\' f'],
            $converter->fromPg('"a"=>"b", "b"=>NULL, "a \\\\b\\\\ c"=>"d \'é\' f"', 'hstore', $session)
        );
        self::assertSame(
            ['pika' => '"chu, rechu'],
            $converter->fromPg('"pika"=>"\\"chu, rechu"', 'hstore', $session)
        );
        self::assertNull($converter->fromPg(null, 'hstore', $session));
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgHstore();

        self::assertSame('NULL::hstore', $converter->toPg(null, 'hstore', $session));
        self::assertSame(
            'hstore($hs$"a" => "b", "b" => NULL, "a b c" => "d \'é\' f"$hs$)',
            $converter->toPg(['a' => 'b', 'b' => null, 'a b c' => 'd \'é\' f'], 'hstore', $session)
        );

        try {
            $converter->toPg('foo', 'hstore', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('Array converter data must be an array', $e->getMessage());
        }
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgHstore();
        $hstore = ['a' => 'b', 'b' => null, 'a \b\ c' => 'd \'é\' f'];

        self::assertNull($converter->toPgStandardFormat(null, 'hstore', $session));
        self::assertSame(
            '"a" => "b", "b" => NULL, "a \\\\b\\\\ c" => "d \'é\' f"',
            $converter->toPgStandardFormat($hstore, 'hstore', $session)
        );

        try {
            $converter->toPgStandardFormat('foo', 'hstore', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('Array converter data must be an array', $e->getMessage());
        }

        if (!$this->doesTypeExist('hstore', $session)) {
            self::markTestSkipped('HSTORE extension is not installed, skipping tests.');
        }

        self::assertSame($hstore, $this->sendToPostgres($hstore, 'hstore', $session));
    }

    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $session->getPoolerForType('converter')
            ->getConverterHolder()
            ->registerConverter('hstore', new PgHstore(), ['hstore', 'public.hstore']);
    }
}
