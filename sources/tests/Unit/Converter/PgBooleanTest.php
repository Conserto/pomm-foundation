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
use PommProject\Foundation\Converter\PgBoolean;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;

#[CoversClass(PgBoolean::class)]
final class PgBooleanTest extends BaseConverterTestCase
{
    /**
     * @throws FoundationException from buildSession() / PgBoolean::fromPg()
     */
    #[TestWith(['t', true])]
    #[TestWith(['f', false])]
    #[TestWith([null, null])]
    public function testFromPg(?string $input, ?bool $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgBoolean()->fromPg($input, 'bool', $session));
    }

    /**
     * @throws FoundationException from buildSession(); ConverterException is the asserted one
     */
    public function testFromPgInvalid(): void
    {
        $session = $this->buildSession();

        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage('Unknown bool data');

        new PgBoolean()->fromPg('whatever', 'bool', $session);
    }

    /**
     * @throws FoundationException from buildSession() / PgBoolean::toPg()
     */
    #[TestWith([true, "bool 'true'"])]
    #[TestWith([false, "bool 'false'"])]
    #[TestWith([null, 'NULL::bool'])]
    public function testToPg(?bool $input, string $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgBoolean()->toPg($input, 'bool', $session));
    }

    /**
     * @throws FoundationException from buildSession() / PgBoolean::toPgStandardFormat()
     */
    #[TestWith([true, 't'])]
    #[TestWith([false, 'f'])]
    #[TestWith([null, null])]
    public function testToPgStandardFormat(?bool $input, ?string $expected): void
    {
        $session = $this->buildSession();
        self::assertSame($expected, new PgBoolean()->toPgStandardFormat($input, 'bool', $session));
    }

    /**
     * Strings are passed unchanged: PostgreSQL reads its own literals ('1', 't', 'yes'... used to be written as false).
     *
     * @throws FoundationException
     */
    #[TestWith(['1', true])]
    #[TestWith(['t', true])]
    #[TestWith([' TRUE ', true])]
    #[TestWith(['yes', true])]
    #[TestWith(['on', true])]
    #[TestWith(['0', false])]
    #[TestWith(['f', false])]
    #[TestWith(['false', false])]
    #[TestWith(['no', false])]
    #[TestWith(['off', false])]
    public function testLiteralsAreReadByPostgres(string $input, bool $expected): void
    {
        $session = $this->buildSession();

        self::assertSame($input, new PgBoolean()->toPgStandardFormat($input, 'bool', $session));
        self::assertSame($expected, $this->sendToPostgres($input, 'bool', $session));
    }

    /**
     * Values that are not PostgreSQL booleans are rejected by PostgreSQL (they used to be written as false).
     *
     * @throws FoundationException
     */
    #[TestWith(['not a boolean'])]
    #[TestWith([''])]
    #[TestWith(['2'])]
    public function testPostgresRejectsNonBooleans(string $input): void
    {
        $this->expectException(SqlException::class);
        $this->sendToPostgres($input, 'bool', $this->buildSession());
    }

    /**
     * Numbers (PostgreSQL does not store them into a boolean column), arrays and objects are rejected before reaching
     * PostgreSQL.
     *
     * @throws FoundationException
     */
    public function testRejectsNonStrings(): void
    {
        $session = $this->buildSession();
        $converter = new PgBoolean();

        foreach ([1, 0, 2, 1.0, NAN, [true], new \stdClass()] as $input) {
            try {
                $converter->toPgStandardFormat($input, 'bool', $session);
                self::fail(sprintf("A ConverterException was expected for a '%s' value.", get_debug_type($input)));
            } catch (ConverterException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
