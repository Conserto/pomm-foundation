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

class PgJson extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $json = <<<JSON
          {"az": {"b": [" c ", "d\\\\\\":"], "e": {"fé": "gù:\\"pika\\""}}, "h": ["'i'", "j"]}
        JSON;
        $converter = $this->newTestedInstance();
        $this->array(
            $converter->fromPg($json, 'json', $session)
        )
            ->isIdenticalTo(["az" => ['b' => [' c ', 'd\\":'], 'e' => ['fé' => 'gù:"pika"']], 'h' => ['\'i\'', 'j']])
            ->variable($converter->fromPg(null, 'json', $session));

        $object = $this->newTestedInstance(false)->fromPg($json, 'json', $session);
        $this->object($object)
            ->isInstanceOf((object)[])
            ->object($object->az)
            ->array($object->az->b)
            ->isIdenticalTo([' c ', 'd\\":']);
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $data = ['a' => ['b' => [' c ', 'd'], 'e' => 'f'], 'g' => ['h', 'i']];
        $this->string(
            $converter->toPg($data, 'json', $session)
        )
            ->isEqualTo('json \'{"a":{"b":[" c ","d"],"e":"f"},"g":["h","i"]}\'')
            ->string(
                $converter->toPg(null, 'json', $session)
            )
            ->isEqualTo('NULL::json');
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $data = ['a' => ['b' => [' c ', 'd'], 'e' => 'f'], 'g' => ['h', 'i']];
        $this->string(
            $this->newTestedInstance()
                ->toPgStandardFormat($data, 'json', $session)
        )
            ->isEqualTo('{"a":{"b":[" c ","d"],"e":"f"},"g":["h","i"]}')
            ->variable(
                $this->newTestedInstance()
                    ->toPgStandardFormat(null, 'json', $session)
            )
            ->isNull();

        if ($this->isPgVersionAtLeast('9.2', $session) === false) {
            $this->skip("Skipping JSON tests as Json type does not exist for Pg < 9.2.");

            return;
        }

        $this->array($this->sendToPostgres($data, 'json', $session))
            ->isIdenticalTo($data);
    }

    /** @throws FoundationException */
    public function testFromPgWithBooleanPrimitive(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $this->boolean(
            $converter->fromPg('false', 'json', $session)
        )
            ->isFalse()
            ->boolean(
                $converter->fromPg('true', 'json', $session)
            )
            ->isTrue();

    }

    /** @throws FoundationException */
    public function testFromPgWithNull(): void
    {
        $session = $this->buildSession();
        $converter = $this->newTestedInstance();
        $this->variable(
            $converter->fromPg('null', 'json', $session)
        )
            ->isIdenticalTo(null)
            ->variable(
                $converter->fromPg(null, 'json', $session)
            )
            ->isIdenticalTo(null);
    }
}
