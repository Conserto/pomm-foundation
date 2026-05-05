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
use PHPUnit\Framework\Attributes\TestWith;
use PommProject\Foundation\Converter\PgBoolean;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(PgBoolean::class)]
class PgBooleanTest extends BaseConverterTestCase
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
}
