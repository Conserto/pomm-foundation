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

namespace PommProject\Foundation\Tests\Unit\PreparedQuery;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\PreparedQuery\PreparedQuery;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum as TestUnitEnum;

#[CoversClass(PreparedQuery::class)]
class PreparedQueryTest extends FoundationSessionTestCase
{
    /**
     * @throws FoundationException from the happy-path PreparedQuery::__construct call
     */
    public function testConstruct(): void
    {
        try {
            new PreparedQuery(null);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertStringContainsString('empty query', $e->getMessage());
        }

        $query = new PreparedQuery('abcd');
        // The client identifier must match the stable per-SQL signature so the
        // PreparedQueryPooler can key its cache on it.
        self::assertSame(PreparedQuery::getSignatureFor('abcd'), $query->getClientIdentifier());
    }

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() /
     *         registerClient() and PreparedQuery::execute()
     */
    public function testExecute(): void
    {
        $session = $this->buildSession();
        $sql = <<<SQL
            select
              p.id, p.pika, p.a_timestamp, p.a_point
            from (values
                (1, 'one', '1999-08-08'::timestamp, ARRAY[point(1.3, 1.6)]),
                (2, 'two', '2000-09-07'::timestamp, ARRAY[point(1.5, 1.5)]),
                (3, 'three', '2001-10-25 15:43'::timestamp, ARRAY[point(1.6, 1.4)]),
                (4, 'four', '2002-01-01 01:10'::timestamp, ARRAY[point(1.8, 2.3)])
            ) p (id, pika, a_timestamp, a_point)
            where (p.id >= $* or p.pika = ANY($*::text[])) and p.a_timestamp > $*::timestamp and $*::pg_catalog."circle" @> ANY (p.a_point)
            SQL;
        $query = new PreparedQuery($sql);
        $session->registerClient($query);

        $result = $query->execute(
            [2, ['pika, chu', 'three'], new \DateTime('2000-01-01'), new Circle('<(1.5,1.5), 0.3>')]
        );

        self::assertSame(2, $result->countRows());
    }

    /**
     * Untyped $* placeholders must accept PHP enums and forward their scalar
     * value (or name for unit enums) to the driver, since the converter pooler
     * is not invoked when no type hint is present.
     */
    public function testExecuteWithEnumOnUntypedPlaceholder(): void
    {
        $session = $this->buildSession();

        $query = new PreparedQuery('select $* as v');
        $session->registerClient($query);

        self::assertSame(BackedEnum::A->value, $query->execute([BackedEnum::A])->fetchRow(0)['v']);
        self::assertSame(
            (string) IntBackedEnum::TWO->value,
            $query->execute([IntBackedEnum::TWO])->fetchRow(0)['v']
        );
        self::assertSame(
            TestUnitEnum::Active->name,
            $query->execute([TestUnitEnum::Active])->fetchRow(0)['v']
        );
    }

    protected function initializeSession(Session $session): void
    {
    }
}
