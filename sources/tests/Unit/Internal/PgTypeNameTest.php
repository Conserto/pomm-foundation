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

namespace PommProject\Foundation\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Internal\PgTypeName;

#[CoversClass(PgTypeName::class)]
final class PgTypeNameTest extends TestCase
{
    /** Names written unchanged: no part mixes upper and lower case. */
    #[TestWith(['int4'])]
    #[TestWith(['pg_catalog.int4'])]
    #[TestWith(['my_schema.my_type'])]
    #[TestWith(['TEXT'])]
    #[TestWith(['PG_CATALOG.INT4'])]
    #[TestWith(['timestamp with time zone'])]
    #[TestWith(['varchar(255)'])]
    #[TestWith(['numeric(10,2)'])]
    #[TestWith(['"Status"'])]
    #[TestWith(['"My""Type"'])]
    #[TestWith(['public."Status"'])]
    #[TestWith(['été'])]
    #[TestWith(['ÉTAT'])]
    #[TestWith(['order'])]
    #[TestWith(["Status\n"])]
    public function testKeepsNames(string $type): void
    {
        self::assertSame($type, PgTypeName::quote($type));
    }

    /** A part mixing upper and lower case letters is quoted. */
    #[TestWith(['Status', '"Status"'])]
    #[TestWith(['public.Status', 'public."Status"'])]
    #[TestWith(['MySchema.status', '"MySchema".status'])]
    #[TestWith(['MySchema.Status', '"MySchema"."Status"'])]
    #[TestWith(['"MySchema".Status', '"MySchema"."Status"'])]
    #[TestWith(['Catégorie', '"Catégorie"'])]
    public function testQuotesMixedCaseNames(string $type, string $expected): void
    {
        self::assertSame($expected, PgTypeName::quote($type));
    }
}
