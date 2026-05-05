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
use PommProject\Foundation\Converter\PgBytea;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(PgBytea::class)]
class PgByteaTest extends BaseConverterTestCase
{
    private const string HEX = '\x001b5c27200d';

    /**
     * @throws FoundationException
     */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgBytea();
        $binary = $this->binary();

        $output = $converter->fromPg(self::HEX, 'bytea', $session);
        self::assertIsString($output);
        self::assertSame(base64_encode($binary), base64_encode($output));

        self::assertNull($converter->fromPg(null, 'bytea', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $converter = new PgBytea();

        self::assertSame(
            sprintf("bytea '%s'", self::HEX),
            $converter->toPg($this->binary(), 'bytea', $session)
        );
        self::assertSame('NULL::bytea', $converter->toPg(null, 'bytea', $session));
    }

    /**
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $converter = new PgBytea();
        $binary = $this->binary();

        self::assertSame(self::HEX, $converter->toPgStandardFormat($binary, 'bytea', $session));
        self::assertNull($converter->toPgStandardFormat(null, 'bytea', $session));
        self::assertSame($binary, $this->sendToPostgres($binary, 'bytea', $session));
    }

    private function binary(): string
    {
        return chr(0) . chr(27) . chr(92) . chr(39) . chr(32) . chr(13);
    }
}
