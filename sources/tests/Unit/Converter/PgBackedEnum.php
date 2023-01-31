<?php

namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Converter\PgBackedEnum as PommEnum;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Test\Unit\Enum\BackedEnum;

class PgBackedEnum extends BaseConverter
{
    protected function initializeSession(Session $session)
    {
        parent::initializeSession($session);

        $converterHolder = $session
            ->getPoolerForType('converter')
            ->getConverterHolder();

        $converterHolder->registerConverter('MyBackedEnum', new PommEnum(BackedEnum::class), ['test_type_1']);
    }

    public function setUp()
    {
        $connection = $this->buildSession()->getConnection();
        $connection->executeAnonymousQuery("CREATE TYPE test_type_1 AS ENUM ('a','b')");
    }

    public function tearDown()
    {
        $connection = $this->buildSession()->getConnection();
        $connection->executeAnonymousQuery("DROP TYPE test_type_1 CASCADE");
    }

    public function testFromPg()
    {
        $converterBackedEnum = $this->newTestedInstance(BackedEnum::class);
        $session = $this->buildSession();

        $fromPgBackedEnum = $converterBackedEnum->fromPg('a', 'test_type_1', $session);

        // Success
        $this->boolean($fromPgBackedEnum instanceof BackedEnum)->isTrue();

        // Exception
        $this
            ->exception(function () use ($converterBackedEnum, $session) {
                $converterBackedEnum->fromPg('wrong_value', 'test_type_1', $session);
            })
            ->hasMessage(sprintf('Value "wrong_value" not found in BackedEnum "%s"', BackedEnum::class))
        ;
    }

    public function testToPg()
    {
        $converterBackedEnum = $this->newTestedInstance(BackedEnum::class);
        $session = $this->buildSession();

        // Success
        $this->string($converterBackedEnum->toPg(BackedEnum::A, 'test_type_1', $session))->isEqualTo('a');
        $this->string($converterBackedEnum->toPg(null, 'test_type_1', $session))->isEqualTo('NULL::test_type_1');
    }
}
