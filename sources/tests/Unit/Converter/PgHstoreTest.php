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
use PommProject\Foundation\Converter\ConverterPooler;
use PommProject\Foundation\Converter\PgHstore;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;

#[CoversClass(PgHstore::class)]
final class PgHstoreTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
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

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgHstore();

        self::assertSame('NULL::hstore', $converter->toPg(null, 'hstore', $session));
        self::assertSame(
            'hstore(\'"a" => "b", "b" => NULL, "a b c" => "d \'\'é\'\' f"\')',
            $converter->toPg(['a' => 'b', 'b' => null, 'a b c' => 'd \'é\' f'], 'hstore', $session)
        );

        try {
            $converter->toPg('foo', 'hstore', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertStringContainsString('Array converter data must be an array', $e->getMessage());
        }
    }

    /**
     * @throws FoundationException
     */
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

    /**
     * @throws FoundationException from getPoolerForType() / registerConverter()
     */
    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $converterPooler = $session->getPoolerForType('converter');
        self::assertInstanceOf(ConverterPooler::class, $converterPooler);
        $converterPooler
            ->getConverterHolder()
            ->registerConverter('hstore', new PgHstore(), ['hstore', 'public.hstore']);
    }

    /**
     * Numeric keys are int array keys in PHP: they must still be written as hstore keys (the file declares strict_types).
     *
     * @throws FoundationException
     */
    public function testIntKeys(): void
    {
        $session = $this->buildSession();
        $converter = new PgHstore();

        self::assertSame('"1" => "a", "2" => NULL', $converter->toPgStandardFormat([1 => 'a', '2' => null], 'hstore', $session));
    }

    /**
     * A key or a value holding $hs$ or a quote is escaped: $hs$ used to close the delimiter and inject SQL.
     *
     * @throws FoundationException
     */
    public function testToPgEscapesDelimiters(): void
    {
        $session = $this->buildSession();
        $data = ['k$hs$' => 'v$hs$); select 1; --', "it's" => 'a\\b'];
        $result = $session->getConnection()->executeAnonymousQuery(
            'select ' . new PgHstore()->toPg($data, 'hstore', $session) . '::text as h'
        );

        self::assertInstanceOf(ResultHandler::class, $result);
        $read = new PgHstore()->fromPg($result->fetchRow(0)['h'], 'hstore', $session);
        self::assertIsArray($read);
        // PostgreSQL sorts hstore keys.
        ksort($data);
        ksort($read);
        self::assertSame($data, $read);
    }
}
