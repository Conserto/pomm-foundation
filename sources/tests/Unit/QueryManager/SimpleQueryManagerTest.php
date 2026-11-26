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

namespace PommProject\Foundation\Tests\Unit\QueryManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Listener\Listener;
use PommProject\Foundation\QueryManager\SimpleQueryManager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum as TestUnitEnum;
use PommProject\Foundation\Tests\Fixture\QueryManager\ListenerTester;
use PommProject\Foundation\Where;

#[CoversClass(SimpleQueryManager::class)]
final class SimpleQueryManagerTest extends FoundationSessionTestCase
{
    /**
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    public function testSimpleQuery(): void
    {
        $session = $this->buildSession();
        $iterator = $this->getQueryManager($session)->query('select true as "one one", null::int4 as "TWO"');

        $row = $iterator->current();
        self::assertNotNull($row);
        self::assertTrue($row['one one']);
        self::assertNull($row['TWO']);
    }

    /**
     * @throws FoundationException from buildSession() and registerClient()
     */
    public function testQueryWithExtraParameter(): void
    {
        $session = $this->buildSession();

        $this->expectException(SqlException::class);
        $this->getQueryManager($session)->query('select true', ['extra']);
    }

    /**
     * Parameters are sent in order, whatever their keys: pgsql reads the values only.
     *
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    public function testParametersWithAnyKeys(): void
    {
        $queryManager = $this->getQueryManager($this->buildSession());

        self::assertSame(
            ['a' => 'x', 'b' => 2],
            $queryManager->query('select $* as a, $*::int4 as b', ['first' => 'x', 'second' => 2])->current() // @phpstan-ignore argument.type (named keys are not documented, but accepted)
        );
        self::assertSame(
            ['a' => 'x', 'b' => 2],
            $queryManager->query('select $* as a, $*::int4 as b', [3 => 'x', 7 => 2])->current()
        );
        // Types used to be matched by key: with named keys, $*::bool was not converted and false was sent as ''.
        self::assertSame(['f' => false], $queryManager->query('select $*::bool as f', ['flag' => false])->current()); // @phpstan-ignore argument.type (named keys are not documented, but accepted)
    }

    /**
     * The position given in the message is the placeholder's, whatever the keys.
     *
     * @throws FoundationException from buildSession() / query()
     */
    public function testRefusedParameterPositionIgnoresKeys(): void
    {
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage('Parameter 2 ');
        $this->getQueryManager($session)->query('select $*::int4, $* as v', ['id' => 1, 'list' => [1, 2]]); // @phpstan-ignore argument.type (named keys are not documented, but accepted)
    }

    /**
     * A value an untyped parameter cannot send is refused before reaching pgsql, which wrote 'Array'.
     *
     * @throws FoundationException from buildSession() / query()
     */
    public function testRefusesArrayOnUntypedParameter(): void
    {
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage('Parameter 2 is not converted by Pomm (only $*::type parameters are) and was given a value of type array');
        $this->getQueryManager($session)->query('select $*::int4, $* as v', [1, [1, 2]]);
    }

    /**
     * A query sent in a loop notices an untyped true, false or float once per position, even when it is refused.
     *
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    #[IgnoreDeprecations]
    public function testNoticesOncePerQuery(): void
    {
        $queryManager = $this->getQueryManager($this->buildSession());
        $notices = [];
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $queryManager->query('select $* as v', [true]);
            $queryManager->query('select $* as v', [false]);
            $queryManager->query('select $* as v', [1.5]);
            $queryManager->query('select $* as w', [true]);

            for ($attempt = 0; $attempt < 2; ++$attempt) {
                try {
                    $queryManager->query('select $* as a, $* as b', [2.5, [1]]);
                    self::fail('The array value should be refused.');
                } catch (ConverterException) {
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertCount(5, $notices);
        self::assertStringContainsString("value of type bool, sent as '1'", $notices[0]);
        self::assertStringContainsString("value of type bool, sent as ''", $notices[1]);
        self::assertStringContainsString('value of type float', $notices[2]);
        self::assertStringContainsString("value of type bool, sent as '1'", $notices[3]);
        self::assertStringContainsString("value of type float, sent as '2.5'", $notices[4]);
    }

    /**
     * Past 100 queries, only the least recently sent query's notices are forgotten.
     *
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    #[IgnoreDeprecations]
    public function testForgetsLeastRecentlySentNoticesFirst(): void
    {
        $queryManager = $this->getQueryManager($this->buildSession());
        $notices = [];
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            for ($query = 0; $query < 100; ++$query) {
                $queryManager->query(sprintf('select $* as v%d', $query), [true]);
            }

            // v0 is sent again, so v1 becomes the least recently sent query and is forgotten by v100.
            $queryManager->query('select $* as v0', [true]);
            $queryManager->query('select $* as v100', [true]);
            $queryManager->query('select $* as v0', [true]);
            $queryManager->query('select $* as v2', [true]);
            $queryManager->query('select $* as v1', [true]);
        } finally {
            restore_error_handler();
        }

        // 100 first notices, v100, then v1 again; v0 and v2 are still remembered.
        self::assertCount(102, $notices);
    }

    /**
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    public function testParametrizedQuery(): void
    {
        $session = $this->buildSession();
        $sql = <<<SQL
            select
              p.id, p.pika, p.a_timestamp, p.a_point
            from (values
                (1, 'one', '1999-08-08'::timestamp, ARRAY[point(1.3, 1.6)], 't'::bool),
                (2, 'two', '2000-09-07'::timestamp, ARRAY[point(1.5, 1.5)], 'f'::bool),
                (3, 'three', '2001-10-25 15:43'::timestamp, ARRAY[point(1.6, 1.4)], 'f'::bool),
                (4, 'four', '2002-01-01 01:10'::timestamp, ARRAY[point(1.8, 2.3)], 't'::bool)
            ) p (id, pika, a_timestamp, a_point, a_bool)
            where {condition}
            SQL;

        $iteratorFromComplex = $this->getQueryManager($session)->query(
            strtr($sql, ['{condition}' => '(p.id >= $* or p.pika = ANY($*::text[])) and p.a_timestamp > $*::timestamp and $*::pg_catalog."circle" @> ANY (p.a_point)']),
            [2, ['chu', 'three'], new \DateTime('2000-01-01'), new Circle('<(1.5,1.5), 0.3>')]
        );
        self::assertSame([2, 3], $iteratorFromComplex->slice('id'));

        $iteratorFromBool = $this->getQueryManager($session)->query(
            strtr($sql, ['{condition}' => 'a_bool = $*::bool']),
            [false]
        );
        self::assertSame([2, 3], $iteratorFromBool->slice('id'));
    }

    /**
     * @throws FoundationException|SqlException from buildSession() / getClientUsingPooler() / query()
     */
    public function testSendNotification(): void
    {
        $session = $this->buildSession('test session');
        $listenerTester = new ListenerTester();
        $listener = $session->getClientUsingPooler('listener', 'query');
        self::assertInstanceOf(Listener::class, $listener);
        $listener->attachAction($listenerTester->call(...));

        $this->getQueryManager($session)->query('select $*::bool as one', [true]);

        self::assertTrue($listenerTester->is_called);
        self::assertSame('select $*::bool as one', $listenerTester->sql);
        self::assertSame(['t'], $listenerTester->parameters);
        self::assertSame('test session', $listenerTester->session_stamp);
        self::assertSame(1, $listenerTester->result_count);
    }

    /**
     * Bare $* placeholders (e.g. those built by Where::createWhereIn) carry no
     * type cast, so the value must still reach the driver as a scalar when it's
     * a PHP enum rather than a primitive.
     */
    public function testQueryWithEnumOnUntypedPlaceholder(): void
    {
        $session = $this->buildSession();

        $where = Where::createWhereIn("'a'::text", [BackedEnum::A, BackedEnum::NUMERIC]);
        $matchIterator = $this->getQueryManager($session)
            ->query(sprintf('select %s as match', (string) $where), $where->getValues());
        $row = $matchIterator->current();
        self::assertNotNull($row);
        self::assertTrue($row['match']);

        $intIterator = $this->getQueryManager($session)
            ->query('select $* as v', [IntBackedEnum::TWO]);
        $row = $intIterator->current();
        self::assertNotNull($row);
        self::assertSame((string) IntBackedEnum::TWO->value, $row['v']);

        $unitIterator = $this->getQueryManager($session)
            ->query('select $* as v', [TestUnitEnum::Active]);
        $row = $unitIterator->current();
        self::assertNotNull($row);
        self::assertSame(TestUnitEnum::Active->name, $row['v']);
    }

    protected function initializeSession(Session $session): void
    {
    }

    /**
     * @throws FoundationException from registerClient()
     */
    private function getQueryManager(Session $session): SimpleQueryManager
    {
        $queryManager = new SimpleQueryManager();
        $session->registerClient($queryManager);

        return $queryManager;
    }

    /**
     * A null value for a typed parameter is sent as SQL NULL (it used to end in a TypeError).
     *
     * @throws FoundationException|SqlException from buildSession() / query()
     */
    public function testNullTypedParameters(): void
    {
        $session = $this->buildSession();
        $row = $this->getQueryManager($session)
            ->query('select $*::int4 is null as a, $*::text is null as b, $*::timestamptz is null as c, $*::int4 as d', [null, null, null, 4])
            ->current();

        self::assertSame(['a' => true, 'b' => true, 'c' => true, 'd' => 4], $row);
    }
}
