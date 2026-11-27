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

namespace PommProject\Foundation\Tests\Unit\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Session\SessionBuilder;

#[CoversClass(SessionBuilder::class)]
final class SessionBuilderTest extends TestCase
{
    private const string DSN = 'pgsql://user:pass@host:5432/db_name';

    /**
     * Scalar settings keep being converted the way PHP's coercive typing mode converted them
     * (e.g. `connection:persist: 1` in a YAML file). buildSession() does not open the connection.
     *
     * @param array<string, mixed> $settings
     * @param array{0: string, 1: bool} $expected dsn and persist received by createConnection()
     * @throws FoundationException
     */
    #[DataProvider('scalarSettingsProvider')]
    public function testScalarSettingsAreConverted(array $settings, array $expected): void
    {
        $builder = $this->createSpyBuilder(['dsn' => self::DSN] + $settings);
        $builder->buildSession();

        self::assertSame($expected, [$builder->seen['dsn'] ?? null, $builder->seen['persist'] ?? null]);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: array{0: string, 1: bool}}>
     */
    public static function scalarSettingsProvider(): iterable
    {
        yield 'bool true' => [['connection:persist' => true], [self::DSN, true]];
        yield 'int 1' => [['connection:persist' => 1], [self::DSN, true]];
        yield 'int 0' => [['connection:persist' => 0], [self::DSN, false]];
        yield 'string "0"' => [['connection:persist' => '0'], [self::DSN, false]];
        yield 'string "yes"' => [['connection:persist' => 'yes'], [self::DSN, true]];
    }

    /**
     * Values that used to end in a TypeError now raise a FoundationException naming the setting.
     *
     * @param array<string, mixed> $settings
     */
    #[DataProvider('invalidSettingsProvider')]
    public function testInvalidSettingRaisesFoundationException(array $settings, string $expectedMessage): void
    {
        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->createSpyBuilder($settings + ['dsn' => self::DSN])->buildSession();
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidSettingsProvider(): iterable
    {
        yield 'null dsn' => [['dsn' => null], "Invalid 'dsn' setting: a string is expected, 'null' given."];
        yield 'array persist' => [['connection:persist' => []], "Invalid 'connection:persist' setting: a boolean is expected, 'array' given."];
        yield 'string configuration' => [
            ['connection:configuration' => 'x'],
            "Invalid 'connection:configuration' setting: an array is expected, 'string' given.",
        ];
    }

    /**
     * @throws FoundationException
     */
    public function testSessionClassMustBeASession(): void
    {
        self::assertInstanceOf(Session::class, new SessionBuilder(['dsn' => self::DSN])->buildSession());

        $this->expectException(FoundationException::class);
        $this->expectExceptionMessage("The 'class:session' setting must name a " . Session::class . " subclass, 'stdClass' given.");
        new SessionBuilder(['dsn' => self::DSN, 'class:session' => \stdClass::class])->buildSession();
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSpyBuilder(array $configuration): SpySessionBuilder
    {
        return new SpySessionBuilder($configuration);
    }
}

/** Records what buildSession() passes to createConnection(). */
final class SpySessionBuilder extends SessionBuilder
{
    /** @var array{dsn?: string, persist?: bool} */
    public array $seen = [];

    /** @param array<string, mixed> $connectionConfiguration */
    protected function createConnection(#[\SensitiveParameter] string $dsn, bool $persist, array $connectionConfiguration): Connection
    {
        $this->seen = ['dsn' => $dsn, 'persist' => $persist];

        return parent::createConnection($dsn, $persist, $connectionConfiguration);
    }
}
