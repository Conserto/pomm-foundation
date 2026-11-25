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
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\ConverterPooler;
use PommProject\Foundation\Converter\PgArray;
use PommProject\Foundation\Converter\PgBackedEnum;
use PommProject\Foundation\Converter\PgEnum;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(PgEnum::class)]
#[CoversClass(PgBackedEnum::class)]
final class PgEnumTest extends BaseConverterTestCase
{
    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() / executeAnonymousQuery()
     */
    protected function setUp(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery(
                "CREATE TYPE test_type_1 AS ENUM ('a','b'); CREATE TYPE test_type_unit AS ENUM ('Active','Inactive','Archived')"
            );
    }

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() / executeAnonymousQuery()
     */
    protected function tearDown(): void
    {
        $this->buildSession()
            ->getConnection()
            ->executeAnonymousQuery('DROP TYPE test_type_1, test_type_unit CASCADE');
    }

    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame(BackedEnum::A, $converter->fromPg('a', 'test_type_1', $session));
        self::assertNull($converter->fromPg(null, 'test_type_1', $session));

        try {
            $converter->fromPg('wrong_value', 'test_type_1', $session);
            self::fail('Expected FoundationException was not thrown.');
        } catch (FoundationException $e) {
            self::assertSame(
                sprintf('Value "wrong_value" not found in enum "%s".', BackedEnum::class),
                $e->getMessage()
            );
        }
    }

    /**
     * PostgreSQL sends a string: it is matched against the int value written as a string.
     *
     * @throws FoundationException
     */
    public function testFromPgIntBacked(): void
    {
        $converter = new PgEnum(IntBackedEnum::class);
        $session = $this->buildSession();

        self::assertSame(IntBackedEnum::TWO, $converter->fromPg('2', 'test_type_int', $session));
        self::assertSame('2', $converter->toPgStandardFormat(IntBackedEnum::TWO, 'test_type_int', $session));
        self::assertSame('2', $converter->toPgStandardFormat(2, 'test_type_int', $session));
        self::assertSame('2', $converter->toPgStandardFormat('2', 'test_type_int', $session));

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf('Value "two" not found in enum "%s".', IntBackedEnum::class));
        $converter->fromPg('two', 'test_type_int', $session);
    }

    /**
     * Only the label a case is written as is read, so that every label read can be written back.
     *
     * @throws FoundationException
     */
    #[TestWith(['02'])]
    #[TestWith([' 2'])]
    #[TestWith(['+2'])]
    #[TestWith(['2.0'])]
    #[TestWith(['2.5'])]
    #[TestWith(['9223372036854775808'])]
    public function testIntBackedRejectsOtherLabels(string $label): void
    {
        $converter = new PgEnum(IntBackedEnum::class);
        $session = $this->buildSession();

        try {
            $converter->fromPg($label, 'test_type_int', $session);
            self::fail('Expected ConverterException was not thrown.');
        } catch (ConverterException $e) {
            self::assertSame(
                sprintf('Value "%s" not found in enum "%s".', $label, IntBackedEnum::class),
                $e->getMessage()
            );
        }

        $this->expectException(ConverterException::class);
        $converter->toPgStandardFormat($label, 'test_type_int', $session);
    }

    /**
     * PgBackedEnum, the 4.x name, is kept as a deprecated subclass: it follows PgEnum, restricted to backed enums.
     *
     * @throws FoundationException
     */
    #[IgnoreDeprecations]
    public function testPgBackedEnum(): void
    {
        $this->expectUserDeprecationMessage(sprintf(
            'Since 5.0, "%s" is deprecated and will be removed in 6.0: use "%s".',
            PgBackedEnum::class,
            PgEnum::class
        ));
        $converter = new PgBackedEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame(BackedEnum::A, $converter->fromPg('a', 'test_type_1', $session));
        self::assertNull($converter->fromPg(null, 'test_type_1', $session));
        self::assertSame("test_type_1 'a'", $converter->toPg(BackedEnum::A, 'test_type_1', $session));

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf('"%s" is not a backed enum: use PgEnum.', UnitEnum::class));
        new PgBackedEnum(UnitEnum::class);
    }

    /**
     * A Stringable is written as its string, like PgString does.
     *
     * @throws FoundationException
     */
    public function testToPgAcceptsStringable(): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();
        $value = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'a';
            }
        };

        self::assertSame('a', $converter->toPgStandardFormat($value, 'test_type_1', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame("test_type_1 'a'", $converter->toPg(BackedEnum::A, 'test_type_1', $session));
        self::assertSame('NULL::test_type_1', $converter->toPg(null, 'test_type_1', $session));
        self::assertSame(
            BackedEnum::A,
            $this->selectExpression($converter->toPg(BackedEnum::A, 'test_type_1', $session), $session)
        );
    }

    /**
     * An enum inside an array is written by the toPg() of its converter.
     *
     * @throws FoundationException
     */
    public function testToPgInArray(): void
    {
        $session = $this->buildSession();
        $sql = new PgArray()->toPg([BackedEnum::A, null], 'test_type_1[]', $session);

        self::assertSame([BackedEnum::A, null], $this->selectExpression($sql, $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame('a', $converter->toPgStandardFormat(BackedEnum::A, 'test_type_1', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'test_type_1', $session));
        self::assertSame(BackedEnum::A, $this->sendToPostgres(BackedEnum::A, 'test_type_1', $session));
        self::assertNull($this->sendToPostgres(null, 'test_type_1', $session));
        self::assertSame(
            [BackedEnum::A, null],
            $this->sendToPostgres([BackedEnum::A, null], 'test_type_1[]', $session)
        );
    }

    /**
     * A raw value of the enum is accepted, so that code writing raw values can adopt the converter progressively.
     *
     * @throws FoundationException
     */
    public function testToPgAcceptsValues(): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();

        self::assertSame("test_type_1 'a'", $converter->toPg('a', 'test_type_1', $session));
        self::assertSame('a', $converter->toPgStandardFormat('a', 'test_type_1', $session));
        self::assertSame(BackedEnum::A, $this->sendToPostgres('a', 'test_type_1', $session));
        self::assertSame([BackedEnum::A], $this->sendToPostgres(['a'], 'test_type_1[]', $session));
    }

    /**
     * @throws FoundationException
     */
    #[TestWith(['b', 'Value "b" not found in enum "%s".'])]
    #[TestWith([true, 'Expected a case or a value of enum "%s", got "bool".'])]
    #[TestWith([1.5, 'Expected a case or a value of enum "%s", got "float".'])]
    #[TestWith([IntBackedEnum::TWO, 'Expected a case or a value of enum "%s", got "%2$s".'])]
    public function testToPgRejectsOtherValues(mixed $value, string $message): void
    {
        $converter = new PgEnum(BackedEnum::class);
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf($message, BackedEnum::class, IntBackedEnum::class));
        $converter->toPgStandardFormat($value, 'test_type_1', $session);
    }

    /**
     * A pure enum maps a label to the case with the same name.
     *
     * @throws FoundationException
     */
    public function testUnitEnum(): void
    {
        $converter = new PgEnum(UnitEnum::class);
        $session = $this->buildSession();

        self::assertSame(UnitEnum::Active, $converter->fromPg('Active', 'test_type_unit', $session));
        self::assertSame(UnitEnum::Inactive, $converter->fromPg('Inactive', 'test_type_unit', $session));
        self::assertSame("test_type_unit 'Active'", $converter->toPg(UnitEnum::Active, 'test_type_unit', $session));
        self::assertSame('Active', $converter->toPgStandardFormat('Active', 'test_type_unit', $session));
        self::assertSame(UnitEnum::Inactive, $this->sendToPostgres(UnitEnum::Inactive, 'test_type_unit', $session));
        self::assertSame(UnitEnum::Inactive, $this->sendToPostgres('Inactive', 'test_type_unit', $session));
        self::assertSame(
            [UnitEnum::Active, null],
            $this->selectExpression(
                new PgArray()->toPg([UnitEnum::Active, null], 'test_type_unit[]', $session),
                $session
            )
        );
    }

    /**
     * @throws FoundationException
     */
    #[TestWith(['Archived', 'Value "Archived" not found in enum "%s".'])]
    #[TestWith(['active', 'Value "active" not found in enum "%s".'])]
    #[TestWith([0, 'Value "0" not found in enum "%s".'])]
    #[TestWith([BackedEnum::A, 'Expected a case or a value of enum "%s", got "%2$s".'])]
    public function testUnitEnumRejectsOtherValues(mixed $value, string $message): void
    {
        $converter = new PgEnum(UnitEnum::class);
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf($message, UnitEnum::class, BackedEnum::class));
        $converter->toPgStandardFormat($value, 'test_type_unit', $session);
    }

    /**
     * @throws FoundationException
     */
    public function testUnitEnumRejectsUnknownLabels(): void
    {
        $converter = new PgEnum(UnitEnum::class);
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf('Value "Archived" not found in enum "%s".', UnitEnum::class));
        $converter->fromPg('Archived', 'test_type_unit', $session);
    }

    /**
     * @throws ConverterException
     */
    public function testConstructorRejectsNonEnums(): void
    {
        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage('Enum "UnitEnum" does not exist.');
        // The interface itself: a valid class-string<UnitEnum> that is not an enum.
        new PgEnum(\UnitEnum::class);
    }

    /**
     * @throws FoundationException|SqlException from getQueryManager() / QueryManager::query()
     */
    private function selectExpression(string $sql, Session $session): mixed
    {
        $result = $session->getQueryManager()
            ->query(sprintf('select %s as my_test', $sql))
            ->current();

        return $result['my_test'];
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
            ->registerConverter('MyBackedEnum', new PgEnum(BackedEnum::class), ['test_type_1'])
            ->registerConverter('MyUnitEnum', new PgEnum(UnitEnum::class), ['test_type_unit']);
    }
}
