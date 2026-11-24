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
use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Exception\FoundationException;

#[CoversClass(ConverterClient::class)]
final class ConverterClientTest extends BaseConverterTestCase
{
    /**
     * The standard format of a null value is null (sent as SQL NULL), as the wrapped converter returns.
     *
     * @throws FoundationException
     */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();

        self::assertSame('5', $session->getConverter('int4')->toPgStandardFormat(5));
        self::assertNull($session->getConverter('int4')->toPgStandardFormat(null));
        self::assertNull($session->getConverter('text')->toPgStandardFormat(null, 'text'));
    }
}
