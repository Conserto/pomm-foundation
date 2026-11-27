<?php

declare(strict_types=1);

/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\ConverterPooler;
use PommProject\Foundation\Converter\PgArray;
use PommProject\Foundation\Converter\PgEnum;
use PommProject\Foundation\Converter\PgString;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Internal\PgTypeName;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;

/**
 * Types created with quotes keep their upper case letters: the SQL written by the converters must quote them.
 */
#[CoversClass(PgTypeName::class)]
final class MixedCaseTypeNameTest extends BaseConverterTestCase
{
    /** One session per test, closed afterwards, so that the suite stays below PostgreSQL's connection limit. */
    private Session $session;

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() / executeAnonymousQuery()
     */
    protected function setUp(): void
    {
        $this->session = $this->buildSession();
        $this->session
            ->getConnection()
            ->executeAnonymousQuery(
                // Dropped first, in case an interrupted run left them behind.
                'DROP TYPE IF EXISTS "MixedStatus", "UPPER_STATUS", "order" CASCADE; '
                . 'DROP DOMAIN IF EXISTS "MixedLabel" CASCADE; '
                . "CREATE TYPE \"MixedStatus\" AS ENUM ('a','b'); CREATE DOMAIN \"MixedLabel\" AS text; "
                . "CREATE TYPE \"UPPER_STATUS\" AS ENUM ('a','b'); CREATE TYPE \"order\" AS ENUM ('a','b')"
            );
    }

    /**
     * @throws ConnectionException|FoundationException|SqlException from buildSession() / executeAnonymousQuery()
     */
    protected function tearDown(): void
    {
        $this->session
            ->getConnection()
            ->executeAnonymousQuery(
                'DROP TYPE IF EXISTS "MixedStatus", "UPPER_STATUS", "order" CASCADE; DROP DOMAIN IF EXISTS "MixedLabel" CASCADE'
            );
        $this->session->shutdown();
    }

    /**
     * @throws FoundationException
     */
    public function testEnum(): void
    {
        $session = $this->session;
        $converter = new PgEnum(BackedEnum::class);

        self::assertSame("\"MixedStatus\" 'a'", $converter->toPg(BackedEnum::A, 'MixedStatus', $session));
        self::assertSame(BackedEnum::A, $this->select($converter->toPg(BackedEnum::A, 'MixedStatus', $session), $session));
        self::assertNull($this->select($converter->toPg(null, 'MixedStatus', $session), $session));
        self::assertSame(
            [BackedEnum::A, null],
            $this->select(new PgArray()->toPg([BackedEnum::A, null], 'MixedStatus[]', $session), $session)
        );
        self::assertNull($this->select(new PgArray()->toPg(null, 'MixedStatus[]', $session), $session));
    }

    /**
     * A non ASCII name holding an ASCII upper case letter is quoted too.
     *
     * @throws FoundationException
     */
    public function testNonAsciiName(): void
    {
        $session = $this->session;
        $session->getConnection()->executeAnonymousQuery(
            'DROP DOMAIN IF EXISTS "Catégorie" CASCADE; CREATE DOMAIN "Catégorie" AS text'
        );

        try {
            $sql = new PgString()->toPg('x', 'Catégorie', $session);
            self::assertSame('x', $this->select($sql, $session));
        } finally {
            $session->getConnection()->executeAnonymousQuery('DROP DOMAIN "Catégorie" CASCADE');
        }
    }

    /**
     * A type created quoted in upper case only, or under a reserved word, is given quoted.
     *
     * @throws FoundationException
     */
    #[TestWith(['"UPPER_STATUS"'])]
    #[TestWith(['"order"'])]
    public function testUpperCaseAndReservedNames(string $type): void
    {
        $session = $this->session;
        $converter = new PgEnum(BackedEnum::class);

        self::assertSame(BackedEnum::A, $this->select($converter->toPg(BackedEnum::A, $type, $session), $session));
        self::assertSame(
            [BackedEnum::A, null],
            $this->select(new PgArray()->toPg([BackedEnum::A, null], $type . '[]', $session), $session)
        );
    }

    /**
     * Upper case built-in names are left to PostgreSQL's folding, as before.
     *
     * @throws FoundationException
     */
    public function testUpperCaseBuiltInName(): void
    {
        $session = $this->session;

        self::assertSame('x', $this->select(new PgString()->toPg('x', 'TEXT', $session), $session));
    }

    /**
     * @throws FoundationException
     */
    public function testDomain(): void
    {
        $session = $this->session;
        $converter = new PgString();

        self::assertSame("public.\"MixedLabel\" 'x'", $converter->toPg('x', 'public.MixedLabel', $session));
        self::assertSame('x', $this->select($converter->toPg('x', 'public.MixedLabel', $session), $session));
        self::assertNull($this->select($converter->toPg(null, 'MixedLabel', $session), $session));
    }

    /**
     * @throws FoundationException|SqlException from getQueryManager() / QueryManager::query()
     */
    private function select(string $sql, Session $session): mixed
    {
        $result = $session->getQueryManager()
            ->query(sprintf('select %s as my_test', $sql))
            ->current();

        return $result['my_test'];
    }

    /**
     * @throws FoundationException from getPoolerForType() / registerConverter() / addTypeToConverter()
     */
    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);

        $converterPooler = $session->getPoolerForType('converter');
        self::assertInstanceOf(ConverterPooler::class, $converterPooler);
        $converterPooler
            ->getConverterHolder()
            ->registerConverter('MixedStatus', new PgEnum(BackedEnum::class), ['MixedStatus', 'UPPER_STATUS', '"UPPER_STATUS"', 'order', '"order"'])
            ->addTypeToConverter('String', 'MixedLabel')
            ->addTypeToConverter('String', 'Catégorie');
    }
}
