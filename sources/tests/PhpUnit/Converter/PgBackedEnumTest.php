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
use PommProject\Foundation\Converter\PgBackedEnum;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Test\Unit\Enum\BackedEnum;

#[CoversClass(PgBackedEnum::class)]
class PgBackedEnumTest extends BaseConverterTestCase
{
    protected function setUp(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery("CREATE TYPE test_type_1 AS ENUM ('a','b')");
    }

    protected function tearDown(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery('DROP TYPE test_type_1 CASCADE');
    }

    public function testFromPg(): void
    {
        $converter = new PgBackedEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertInstanceOf(BackedEnum::class, $converter->fromPg('a', 'test_type_1', $session));

        try {
            $converter->fromPg('wrong_value', 'test_type_1', $session);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertSame(
                sprintf('Value "wrong_value" not found in BackedEnum "%s"', BackedEnum::class),
                $e->getMessage()
            );
        }
    }

    public function testToPg(): void
    {
        $converter = new PgBackedEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame('a', $converter->toPg(BackedEnum::A, 'test_type_1', $session));
        self::assertSame('NULL::test_type_1', $converter->toPg(null, 'test_type_1', $session));
    }

    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $session->getPoolerForType('converter')
            ->getConverterHolder()
            ->registerConverter('MyBackedEnum', new PgBackedEnum(BackedEnum::class), ['test_type_1']);
    }
}
