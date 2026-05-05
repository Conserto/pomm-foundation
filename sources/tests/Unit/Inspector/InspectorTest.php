<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 * (c) Conserto
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Tests\Unit\Inspector;

use PHPUnit\Framework\Attributes\CoversClass;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Inspector\Inspector;
use PommProject\Foundation\ResultIterator;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Tester\FoundationSessionTestCase;
use PommProject\Foundation\Tests\Fixture\InspectorFixture;

#[CoversClass(Inspector::class)]
class InspectorTest extends FoundationSessionTestCase
{
    private ?Session $session = null;

    protected function setUp(): void
    {
        $this->getFixture()->createSchema();
    }

    protected function tearDown(): void
    {
        $this->getFixture()->dropSchema();
    }

    public function testGetSchemas(): void
    {
        self::assertInstanceOf(ResultIterator::class, $this->getInspector()->getSchemas());
        self::assertContains('inspector_test', $this->getInspector()->getSchemas()->slice('name'));
    }

    public function testGetTableOid(): void
    {
        $inspector = $this->getInspector();

        self::assertIsInt($inspector->getTableOid('inspector_test', 'no_pk'));
        self::assertNull($inspector->getTableOid('no schema', 'no table'));
        self::assertNull($inspector->getTableOid('inspector_test', 'no table'));
    }

    public function testGetTableFieldInformation(): void
    {
        $complexInfo = $this->getInspector()
            ->getTableFieldInformation($this->getTableOid('with_complex_pk'));

        self::assertInstanceOf(ResultIterator::class, $complexInfo);
        self::assertSame(
            ['with_complex_pk_id', 'another_id', 'created_at'],
            $complexInfo->slice('name')
        );
        self::assertSame(['int4', 'int4', 'timestamp'], $complexInfo->slice('type'));
        self::assertSame(['Test comment', null, null], $complexInfo->slice('comment'));
        self::assertSame(
            ['with_complex_pk_id', 'int4', null, true, 'Test comment', 1, true],
            array_values($complexInfo->get(0))
        );

        $simpleInfo = $this->getInspector()
            ->getTableFieldInformation($this->getTableOid('with_simple_pk'));
        self::assertSame(
            ['int4', 'inspector_test._someone', '_timestamptz'],
            $simpleInfo->slice('type')
        );
    }

    public function testGetPrimaryKey(): void
    {
        $inspector = $this->getInspector();

        self::assertSame([], $inspector->getPrimaryKey($this->getTableOid('no_pk')));
        self::assertSame(
            ['with_simple_pk_id'],
            $inspector->getPrimaryKey($this->getTableOid('with_simple_pk'))
        );
        self::assertSame(
            ['another_id', 'with_complex_pk_id'],
            $inspector->getPrimaryKey($this->getTableOid('with_complex_pk'))
        );
    }

    public function testChangePrimaryKey(): void
    {
        $this->getFixture()->renamePks('with_simple_pk', 'with_simple_pk_id', 'with_simple_pk_id_renamed');
        $this->getFixture()->renamePks('with_complex_pk', 'another_id', 'another_id_renamed');

        try {
            $inspector = $this->getInspector();

            self::assertSame([], $inspector->getPrimaryKey($this->getTableOid('no_pk')));
            self::assertSame(
                ['with_simple_pk_id_renamed'],
                $inspector->getPrimaryKey($this->getTableOid('with_simple_pk'))
            );
            self::assertSame(
                ['another_id_renamed', 'with_complex_pk_id'],
                $inspector->getPrimaryKey($this->getTableOid('with_complex_pk'))
            );
        } finally {
            $this->getFixture()->renamePks('with_simple_pk', 'with_simple_pk_id_renamed', 'with_simple_pk_id');
            $this->getFixture()->renamePks('with_complex_pk', 'another_id_renamed', 'another_id');
        }
    }

    public function testGetSchemaOid(): void
    {
        $inspector = $this->getInspector();

        self::assertIsInt($inspector->getSchemaOid('inspector_test'));
        self::assertNull($inspector->getSchemaOid('whatever'));
    }

    public function testGetSchemaRelations(): void
    {
        $inspector = $this->getInspector();
        $tablesInfo = $inspector->getSchemaRelations($inspector->getSchemaOid('inspector_test'));

        self::assertInstanceOf(ResultIterator::class, $tablesInfo);
        self::assertSame(
            ['no_pk', 'with_complex_pk', 'with_simple_pk'],
            $tablesInfo->slice('name')
        );
        self::assertTrue($inspector->getSchemaRelations(null)->isEmpty());

        foreach (['name', 'type', 'oid', 'comment'] as $key) {
            self::assertArrayHasKey($key, $tablesInfo->current());
        }

        self::assertSame('This table has no primary key', $tablesInfo->get(0)['comment']);
        self::assertNull($tablesInfo->get(1)['comment']);
    }

    public function testGetTableComment(): void
    {
        $inspector = $this->getInspector();

        self::assertNull($inspector->getTableComment($this->getTableOid('with_simple_pk')));
        self::assertSame(
            'This table has no primary key',
            $inspector->getTableComment($this->getTableOid('no_pk'))
        );
    }

    public function testGetTypeEnumValues(): void
    {
        $inspector = $this->getInspector();
        $type = $inspector->getTypeInformation('sponsor_rating', 'inspector_test');

        self::assertSame(
            ['platinum', 'gold', 'silver', 'bronze', 'aluminium'],
            $inspector->getTypeEnumValues($type['oid'])
        );
        self::assertNull($inspector->getTypeEnumValues(1));
    }

    public function testGetVersion(): void
    {
        self::assertSame(1, version_compare($this->getInspector()->getVersion(), '9.1.0'));
    }

    protected function initializeSession(Session $session): void
    {
        $session->registerClient(new InspectorFixture());
    }

    private function getSession(): Session
    {
        if ($this->session === null) {
            $this->session = $this->buildSession();
        }

        return $this->session;
    }

    private function getInspector(): Inspector
    {
        return $this->getSession()->getInspector();
    }

    private function getFixture(): InspectorFixture
    {
        $fixture = $this->getSession()->getClient('fixture', 'inspector');

        if (!$fixture instanceof InspectorFixture) {
            throw new FoundationException("Unable to get client 'fixture'::'inspector' from the session's client pool.");
        }

        return $fixture;
    }

    /**
     * @throws FoundationException
     */
    private function getTableOid(string $tableName): ?int
    {
        return $this->getInspector()->getTableOid('inspector_test', $tableName);
    }
}
