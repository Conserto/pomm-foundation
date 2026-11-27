<?php

declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\PgArray;
use PommProject\Foundation\Converter\PgJson;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

#[CoversClass(PgJson::class)]
final class PgJsonTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $json = <<<JSON
              {"az": {"b": [" c ", "d\\\\\\":"], "e": {"fé": "gù:\\"pika\\""}}, "h": ["'i'", "j"]}
            JSON;

        $converter = new PgJson();
        self::assertSame(
            ['az' => ['b' => [' c ', 'd\\":'], 'e' => ['fé' => 'gù:"pika"']], 'h' => ["'i'", 'j']],
            $converter->fromPg($json, 'json', $session)
        );
        self::assertNull($converter->fromPg(null, 'json', $session));

        $object = new PgJson(false)->fromPg($json, 'json', $session);
        self::assertInstanceOf(\stdClass::class, $object);
        self::assertInstanceOf(\stdClass::class, $object->az);
        self::assertSame([' c ', 'd\\":'], $object->az->b);
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgJson();
        $data = ['a' => ['b' => [' c ', 'd'], 'e' => 'f'], 'g' => ['h', 'i']];

        self::assertSame(
            'json \'{"a":{"b":[" c ","d"],"e":"f"},"g":["h","i"]}\'',
            $converter->toPg($data, 'json', $session)
        );
        self::assertSame('NULL::json', $converter->toPg(null, 'json', $session));
        self::assertSame('jsonb \'{"e":"f"}\'', $converter->toPg(['e' => 'f'], 'jsonb', $session));
        self::assertSame('NULL::jsonb', $converter->toPg(null, 'jsonb', $session));
    }

    /**
     * Values written by toPg() are escaped: they come back unchanged from PostgreSQL.
     *
     * @throws FoundationException
     */
    #[TestWith(["l'eau", 'json'])]
    #[TestWith(["l'eau", 'jsonb'])]
    #[TestWith(['C:\\dossier "x"', 'jsonb'])]
    #[TestWith(["x'; SELECT 1; --", 'jsonb'])]
    #[TestWith([['k\'ey' => ["it's", 'a\\b"c']], 'jsonb'])]
    public function testToPgEscapesValue(mixed $value, string $type): void
    {
        $session = $this->buildSession();
        $literal = new PgJson()->toPg($value, $type, $session);

        self::assertSame($value, $this->fetchLiteral($literal, $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPgEscapesApostrophe(): void
    {
        $session = $this->buildSession();

        self::assertSame("jsonb '\"l''eau\"'", new PgJson()->toPg("l'eau", 'jsonb', $session));
    }

    /**
     * json[] values go through PgArray::toPg(), which calls PgJson::toPg() for each element.
     *
     * @throws FoundationException
     */
    public function testToPgEscapesArrayElements(): void
    {
        $session = $this->buildSession();
        $value = [["l'eau"], ['k' => "x'; SELECT 1; --"]];
        $literal = new PgArray()->toPg($value, 'json', $session);

        self::assertSame($value, $this->fetchLiteral($literal, $session));
    }

    /**
     * Runs a literal produced by toPg() and returns the value read back by Pomm.
     *
     * @throws FoundationException
     */
    private function fetchLiteral(string $literal, Session $session): mixed
    {
        $row = $session->getQueryManager()->query(sprintf('select %s as my_test', $literal))->current();

        return $row['my_test'];
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgJson();
        $data = ['a' => ['b' => [' c ', 'd'], 'e' => 'f'], 'g' => ['h', 'i']];

        self::assertSame(
            '{"a":{"b":[" c ","d"],"e":"f"},"g":["h","i"]}',
            $converter->toPgStandardFormat($data, 'json', $session)
        );
        self::assertNull($converter->toPgStandardFormat(null, 'json', $session));

        if (!$this->isPgVersionAtLeast('9.2', $session)) {
            self::markTestSkipped('Skipping JSON tests as Json type does not exist for Pg < 9.2.');
        }

        self::assertSame($data, $this->sendToPostgres($data, 'json', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testFromPgWithBooleanPrimitive(): void
    {
        $session = $this->buildSession();
        $converter = new PgJson();

        self::assertFalse($converter->fromPg('false', 'json', $session));
        self::assertTrue($converter->fromPg('true', 'json', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testFromPgWithNull(): void
    {
        $session = $this->buildSession();
        $converter = new PgJson();

        self::assertNull($converter->fromPg('null', 'json', $session));
        self::assertNull($converter->fromPg(null, 'json', $session));
    }
}
