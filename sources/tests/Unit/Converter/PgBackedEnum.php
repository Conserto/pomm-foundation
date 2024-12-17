<?php

namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Converter\PgBackedEnum as PommEnum;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Test\Unit\Enum\BackedEnum;

class PgBackedEnum extends BaseConverter
{
    /** @throws FoundationException */
    public function setUp(): void
    {
        $connection = $this->buildSession()->getConnection();
        $connection->executeAnonymousQuery("CREATE TYPE test_type_1 AS ENUM ('a','b')");
    }

    /** @throws FoundationException */
    public function tearDown(): void
    {
        $connection = $this->buildSession()->getConnection();
        $connection->executeAnonymousQuery("DROP TYPE test_type_1 CASCADE");
    }

    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $converterBackedEnum = $this->newTestedInstance(BackedEnum::class);
        $session = $this->buildSession();

        $fromPgBackedEnum = $converterBackedEnum->fromPg('a', 'test_type_1', $session);

        // Success
        $this->boolean($fromPgBackedEnum instanceof BackedEnum)->isTrue();

        // Exception
        $this->exception(function () use ($converterBackedEnum, $session): void {
            $converterBackedEnum->fromPg('wrong_value', 'test_type_1', $session);
        })
            ->hasMessage(sprintf('Value "wrong_value" not found in BackedEnum "%s"', BackedEnum::class));
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $converterBackedEnum = $this->newTestedInstance(BackedEnum::class);
        $session = $this->buildSession();

        // Success
        $this->string($converterBackedEnum->toPg(BackedEnum::A, 'test_type_1', $session))->isEqualTo('a');
        $this->string($converterBackedEnum->toPg(null, 'test_type_1', $session))->isEqualTo('NULL::test_type_1');
    }

    /** @throws FoundationException */
    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $converterHolder = $session
            ->getPoolerForType('converter')
            ->getConverterHolder();

        $converterHolder->registerConverter('MyBackedEnum', new PommEnum(BackedEnum::class), ['test_type_1']);
    }
}
