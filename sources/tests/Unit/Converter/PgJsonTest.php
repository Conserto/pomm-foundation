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
use PommProject\Foundation\Converter\PgJson;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(PgJson::class)]
class PgJsonTest extends BaseConverterTestCase
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
