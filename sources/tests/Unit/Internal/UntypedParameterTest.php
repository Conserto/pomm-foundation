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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Internal\UntypedParameter;
use PommProject\Foundation\Tests\Fixture\Enum\BackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\IntBackedEnum;
use PommProject\Foundation\Tests\Fixture\Enum\UnitEnum;

#[CoversClass(UntypedParameter::class)]
final class UntypedParameterTest extends TestCase
{
    /** @return iterable<string, array{mixed, mixed}> */
    public static function faithfulValues(): iterable
    {
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'text';
            }
        };

        yield 'null' => [null, null];
        yield 'string' => ['a', 'a'];
        yield 'int' => [42, 42];
        yield 'Stringable' => [$stringable, $stringable];
        yield 'backed enum' => [BackedEnum::A, 'a'];
        yield 'int backed enum' => [IntBackedEnum::TWO, 2];
        yield 'unit enum' => [UnitEnum::Active, 'Active'];
    }

    /** Values pgsql sends faithfully are passed on, enums unwrapped. */
    #[DataProvider('faithfulValues')]
    public function testPassesFaithfulValues(mixed $value, mixed $expected): void
    {
        self::assertSame($expected, UntypedParameter::check($value, 0));
    }

    /**
     * A bool or a float is sent with a notice giving the text sent: false becomes '' and 1/3 keeps 14 digits.
     *
     * @param non-empty-string $message
     */
    #[IgnoreDeprecations]
    #[TestWith([false, "Parameter 2 is not converted by Pomm (only \$*::type parameters are) and was given a value of type bool, sent as '' (false is sent as ''): write every parameter of the query as \$* and this one \$*::bool, or pass the value as a string."])]
    #[TestWith([true, "Parameter 2 is not converted by Pomm (only \$*::type parameters are) and was given a value of type bool, sent as '1' (false is sent as ''): write every parameter of the query as \$* and this one \$*::bool, or pass the value as a string."])]
    #[TestWith([1 / 3, "Parameter 2 is not converted by Pomm (only \$*::type parameters are) and was given a value of type float, sent as '0.33333333333333': write every parameter of the query as \$* and this one \$*::float8 (or ::numeric), or pass the value as a string."])]
    public function testNoticesBoolAndFloat(bool|float $value, string $message): void
    {
        $this->expectUserDeprecationMessage($message);

        self::assertSame($value, UntypedParameter::check($value, 1));
    }

    /** A notice is given once per position and value kind (true, false, float) for the same $noticed array. */
    public function testNoticesOncePerPositionAndType(): void
    {
        $notices = [];
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $noticed = [];
            UntypedParameter::check(true, 0, $noticed);
            UntypedParameter::check(false, 0, $noticed);
            UntypedParameter::check(0.5, 0, $noticed);
            UntypedParameter::check(true, 1, $noticed);
            UntypedParameter::check(true, 0);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['0:true' => true, '0:false' => true, '0:float' => true, '1:true' => true], $noticed);
        self::assertCount(5, $notices);
        self::assertStringContainsString("value of type bool, sent as '1'", $notices[0]);
        self::assertStringContainsString("value of type bool, sent as ''", $notices[1]);
        self::assertStringContainsString('value of type float', $notices[2]);
    }

    /** An object PHP turns into text without being Stringable (GMP) is sent as that text, as pgsql did. */
    #[RequiresPhpExtension('gmp')]
    public function testSendsObjectsPhpTurnsIntoText(): void
    {
        self::assertSame('123456789012345678901234567890', UntypedParameter::check(gmp_init('123456789012345678901234567890'), 0));
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function unsendableValues(): iterable
    {
        yield 'array' => [[1, 2], 'array'];
        yield 'DateTime' => [new \DateTime(), 'DateTime'];
        yield 'object' => [new \stdClass(), 'stdClass'];
        yield 'closure' => [static fn (): null => null, 'Closure'];
        yield 'closed resource' => [self::closedResource(), 'resource (closed)'];
    }

    /** A value pgsql cannot send is refused: it wrote 'Array', raised a PHP Error or wrote an arbitrary text. */
    #[DataProvider('unsendableValues')]
    public function testRefusesOtherValues(mixed $value, string $type): void
    {
        $this->expectException(ConverterException::class);
        $this->expectExceptionMessage(sprintf(
            'Parameter 3 is not converted by Pomm (only $*::type parameters are) and was given a value of type %s,'
            . ' which cannot be sent to PostgreSQL: write every parameter of the query as $* and this one $*::type, or'
            . ' pass the value as a string.',
            $type
        ));

        UntypedParameter::check($value, 2);
    }

    /** @return resource */
    private static function closedResource(): mixed
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        fclose($resource);

        return $resource;
    }
}
