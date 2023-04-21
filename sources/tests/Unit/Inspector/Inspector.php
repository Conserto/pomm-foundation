<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Inspector;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\ResultIterator;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Test\Fixture\InspectorFixture;
use PommProject\Foundation\Tester\FoundationSessionAtoum;

/** @engine isolate */
class Inspector extends FoundationSessionAtoum
{
    protected ?Session $session = null;

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function setUp(): void
    {
        $this->getFixture()->createSchema();
    }

    /** @throws FoundationException */
    protected function getFixture(): InspectorFixture
    {
        /** @var ?InspectorFixture $fixture */
        $fixture = $this->getSession()->getClient('fixture', 'inspector');

        if ($fixture === null) {
            throw new FoundationException("Unable to get client 'fixture'::'inspector' from the session's client pool.");
        }

        return $fixture;
    }

    /** @throws FoundationException */
    protected function getSession(): Session
    {
        if ($this->session === null) {
            $this->session = $this->buildSession();
        }

        return $this->session;
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    public function tearDown(): void
    {
        $this->getFixture()->dropSchema();
    }

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function beforeTestMethod($method): void
    {
        if ('testChangePrimaryKey' === $method) {
            $this->getFixture()
                ->renamePks('with_simple_pk', 'with_simple_pk_id', 'with_simple_pk_id_renamed');
            $this->getFixture()->renamePks('with_complex_pk', 'another_id', 'another_id_renamed');
        }
    }

    /**
     * @throws FoundationException
     * @throws SqlException
     * @throws ConnectionException
     */
    public function afterTestMethod($method): void
    {
        if ('testChangePrimaryKey' === $method) {
            $this->getFixture()
                ->renamePks('with_simple_pk', 'with_simple_pk_id_renamed', 'with_simple_pk_id');
            $this->getFixture()->renamePks('with_complex_pk', 'another_id_renamed', 'another_id');
        }
    }

    /** @throws FoundationException */
    public function testGetSchemas(): void
    {
        $this->object($this->getInspector()->getSchemas())
            ->isInstanceOf(ResultIterator::class)
            ->array($this->getInspector()->getSchemas()->slice('name'))
            ->contains('inspector_test');
    }

    /** @throws FoundationException */
    protected function getInspector(): \PommProject\Foundation\Inspector\Inspector
    {
        return $this->getSession()->getInspector();
    }

    /** @throws FoundationException */
    public function testGetTableOid(): void
    {
        $this->integer($this->getInspector()->getTableOid('inspector_test', 'no_pk'))
            ->variable($this->getInspector()->getTableOid('no schema', 'no table'))
            ->isNull()
            ->variable($this->getInspector()->getTableOid('inspector_test', 'no table'))
            ->isNull();
    }

    /**
     * @throws FoundationException
     */
    protected function getTableOid($table_name): ?int
    {
        return $this->getInspector()->getTableOid('inspector_test', $table_name);
    }

    /** @throws FoundationException */
    public function testGetTableFieldInformation(): void
    {
        $fields_info = $this->getInspector()->getTableFieldInformation($this->getTableOid('with_complex_pk'));

        $this->object($fields_info)
            ->isInstanceOf(ResultIterator::class)
            ->array($fields_info->slice('name'))
            ->isIdenticalTo(['with_complex_pk_id', 'another_id', 'created_at'])
            ->array($fields_info->slice('type'))
            ->isIdenticalTo(['int4', 'int4', 'timestamp'])
            ->array($fields_info->slice('comment'))
            ->isIdenticalTo(['Test comment', null, null])
            ->array(array_values($fields_info->get(0)))
            ->isIdenticalTo(['with_complex_pk_id', 'int4', null, true, 'Test comment', 1, true]);

        $fields_info = $this->getInspector()->getTableFieldInformation($this->getTableOid('with_simple_pk'));
        $this->array($fields_info->slice('type'))
            ->isIdenticalTo(['int4', 'inspector_test._someone', '_timestamptz']);
    }

    /** @throws FoundationException */
    public function testGetPrimaryKey(): void
    {
        $inspector = $this->getInspector();
        $this->array($inspector->getPrimaryKey($this->getTableOid('no_pk')))
            ->isEmpty()
            ->array($inspector->getPrimaryKey($this->getTableOid('with_simple_pk')))
            ->isIdenticalTo(['with_simple_pk_id'])
            ->array($inspector->getPrimaryKey($this->getTableOid('with_complex_pk')))
            ->isIdenticalTo(['another_id', 'with_complex_pk_id']);
    }

    /** @throws FoundationException */
    public function testChangePrimaryKey(): void
    {
        $inspector = $this->getInspector();
        $this->array($inspector->getPrimaryKey($this->getTableOid('no_pk')))
            ->isEmpty()
            ->array($inspector->getPrimaryKey($this->getTableOid('with_simple_pk')))
            ->isIdenticalTo(['with_simple_pk_id_renamed'])
            ->array($inspector->getPrimaryKey($this->getTableOid('with_complex_pk')))
            ->isIdenticalTo(['another_id_renamed', 'with_complex_pk_id']);
    }

    /** @throws FoundationException */
    public function testGetSchemaOid(): void
    {
        $this->integer($this->getInspector()->getSchemaOid('inspector_test'))
            ->variable($this->getInspector()->getSchemaOid('whatever'))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testGetSchemaRelations(): void
    {
        $tablesInfo = $this->getInspector()
            ->getSchemaRelations($this->getInspector()
                ->getSchemaOid('inspector_test')
            );

        $this->object($tablesInfo)
            ->isInstanceOf(ResultIterator::class)
            ->array($tablesInfo->slice('name'))
            ->isIdenticalTo(['no_pk', 'with_complex_pk', 'with_simple_pk'])
            ->boolean($this->getInspector()->getSchemaRelations(null)->isEmpty())
            ->isTrue()
            ->array($tablesInfo->current())
            ->hasKeys(['name', 'type', 'oid', 'comment'])
            ->string($tablesInfo->get(0)['comment'])
            ->isEqualTo('This table has no primary key')
            ->variable($tablesInfo->get(1)['comment'])
            ->isNull();
    }

    /** @throws FoundationException */
    public function testGetTableComment(): void
    {
        $this->variable($this->getInspector()->getTableComment($this->getTableOid('with_simple_pk')))
            ->isNull()
            ->string($this->getInspector()->getTableComment($this->getTableOid('no_pk')))
            ->isEqualTo('This table has no primary key');
    }

    /** @throws FoundationException */
    public function getTypeCategory(): void
    {
        $result = $this->getInspector()->getTableOid('someone', 'inspector_test');

        $this->isArray($result)->hasKeys(['oid', 'category']);

        $oid_result = $this->getInspector()->getTypeCategory($result['oid']);

        $this->array($oid_result)
            ->isIdenticalTo(['name' => 'inspector_test.someone', 'category' => 'C']);
    }

    /** @throws FoundationException */
    public function testGetTypeEnumValues(): void
    {
        $result = $this->getInspector()->getTypeInformation('sponsor_rating', 'inspector_test');
        $this->array($this->getInspector()->getTypeEnumValues($result['oid']))
            ->isIdenticalTo(['platinum', 'gold', 'silver', 'bronze', 'aluminium'])
            ->variable($this->getInspector()->getTypeEnumValues(1))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testGetVersion(): void
    {
        $result = $this->getInspector()->getVersion();

        $this->boolean(version_compare($result, "9.1.0") === 1)->isTrue();
    }

    protected function initializeSession(Session $session): void
    {
        $session->registerClient(new InspectorFixture());
    }
}
