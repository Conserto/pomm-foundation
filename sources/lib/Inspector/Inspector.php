<?php
/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Inspector;

use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Client\Client;
use PommProject\Foundation\PreparedQuery\PreparedQueryManager;
use PommProject\Foundation\Where;

/**
 * Database structure inspector.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       Client
 */
class Inspector extends Client
{
    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'inspector';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return $this::class;
    }

    /**
     * Return a list of available schemas in the current database.
     *
     * @throws FoundationException
     *
     * @return ConvertedResultIterator<array{name: string, oid: int, comment: ?string, relations: int}>
     */
    public function getSchemas(): ConvertedResultIterator
    {
        $sql = <<<SQL
select
    n.nspname     as "name",
    n.oid         as "oid",
    d.description as "comment",
    count(c)      as "relations"
from pg_catalog.pg_namespace n
    left join pg_catalog.pg_description d on n.oid = d.objoid
    left join pg_catalog.pg_class c on
        c.relnamespace = n.oid and c.relkind in ('r', 'v')
where :condition
group by 1, 2, 3
order by 1;
SQL;
        $condition = new Where('n.nspname !~ $* and n.nspname <> $*', ['^pg_', 'information_schema']);

        return $this->executeSql($sql, $condition);
    }

    /**
     * Return the table oid from PostgreSQL catalog. If no table is found, null is returned.
     *
     * @throws FoundationException
     */
    public function getTableOid(string $schema, string $table): ?int
    {
        $sql = <<<SQL
select
  c.oid as oid
from
    pg_catalog.pg_class c
        left join pg_catalog.pg_namespace n on n.oid = c.relnamespace
where
:condition
SQL;

        $where = Where::create('n.nspname =  $*', [$schema])
            ->andWhere('c.relname = $*', [$table]);

        $iterator = $this->executeSql($sql, $where);

        return $iterator->isEmpty() ? null : $iterator->current()['oid'];
    }

    /**
     * Get table's field information. If no fields are found, null is returned.
     *
     * @throws FoundationException
     *
     * @return ConvertedResultIterator<array{name: string, type: string, default: mixed, is_notnull: bool, comment: ?string, is_primary: bool}>
     */
    public function getTableFieldInformation(int $oid): ?ConvertedResultIterator
    {
        $sql = <<<SQL
select
    att.attname      as "name",
    case
        when name.nspname = 'pg_catalog' then typ.typname
        else format('%s.%s', name.nspname, typ.typname)
    end as "type",
    pg_catalog.pg_get_expr(def.adbin, def.adrelid) as "default",
    att.attnotnull   as "is_notnull",
    dsc.description  as "comment",
    att.attnum       as "position",
    att.attnum = any(ind.indkey) as "is_primary"
from
  pg_catalog.pg_attribute att
    join pg_catalog.pg_type  typ  on att.atttypid = typ.oid
    join pg_catalog.pg_class cla  on att.attrelid = cla.oid
    left join pg_catalog.pg_description dsc on cla.oid = dsc.objoid and att.attnum = dsc.objsubid
    left join pg_catalog.pg_attrdef def     on att.attrelid = def.adrelid and att.attnum = def.adnum
    left join pg_catalog.pg_index ind       on cla.oid = ind.indrelid and ind.indisprimary
    left join pg_catalog.pg_namespace name  on typ.typnamespace = name.oid
where
:condition
order by
    att.attnum
SQL;
        $where = Where::create('att.attrelid = $*', [$oid])
            ->andWhere('att.attnum > 0')
            ->andWhere('not att.attisdropped');

        return $this->executeSql($sql, $where);
    }

    /**
     * Return the given schema oid, null if the schema is not found.
     *
     * @param string $schema
     * @param Where|null $where optional where clause.
     * @return int|null
     * @throws FoundationException
     */
    public function getSchemaOid(string $schema, Where $where = null): ?int
    {
        $condition =
            Where::create("s.nspname = $*", [$schema])
            ->andWhere($where)
            ;
        $sql = <<<SQL
select
    s.oid as oid
from
    pg_catalog.pg_namespace s
where
    :condition
SQL;

        $iterator = $this->executeSql($sql, $condition);

        return $iterator->isEmpty() ? null : $iterator->current()['oid'];
    }

    /**
     * Get relation's primary key if any.
     *
     * @throws FoundationException
     *
     * @param int $tableOid
     * @return array<int,string>
     */
    public function getPrimaryKey(int $tableOid): array
    {
        $sql = <<<SQL
with
    pk_field as (
        select
            att.attname as field
        from
            pg_catalog.pg_attribute att
                join pg_catalog.pg_index ind on
                    att.attrelid = ind.indrelid and att.attnum = any(ind.indkey)
        where
            :condition
        order by att.attnum asc
)
select array_agg(field) as fields from pk_field
SQL;
        $condition =
            Where::create('ind.indrelid = $*', [$tableOid])
            ->andWhere('ind.indisprimary');

        $pk = $this->executeSql($sql, $condition)->current();

        return ($pk['fields'] === null || $pk['fields'][0] === null) ? [] : array_reverse($pk['fields']);
    }

    /**
     * Return information on relations in a given schema. An additional Where condition can be passed to filter against
     * other criteria.
     *
     * @throws FoundationException
     *
     * @return ConvertedResultIterator<array{name: string ,type: string, oid: int, comment: ?string}>
     */
    public function getSchemaRelations(?int $schemaOid, Where $where = null): ConvertedResultIterator
    {
        $condition = Where::create('relnamespace = $*', [$schemaOid])
            ->andWhere(Where::createWhereIn('relkind', ['r', 'v', 'm', 'f']))
            ->andWhere($where)
            ;

        $sql = <<<SQL
select
    cl.relname      as "name",
    case
        when cl.relkind = 'r' then 'table'
        when cl.relkind = 'v' then 'view'
        when cl.relkind = 'm' then 'materialized view'
        when cl.relkind = 'f' then 'foreign table'
        else 'other'
    end             as "type",
    cl.oid          as "oid",
    des.description as "comment"
from
    pg_catalog.pg_class cl
        left join pg_catalog.pg_description des on
            cl.oid = des.objoid and des.objsubid = 0
where :condition
order by name asc
SQL;

        return $this->executeSql($sql, $condition);
    }

    /**
     * Return the comment on a table if set. Null otherwise.
     *
     * @throws FoundationException
     */
    public function getTableComment(int $tableOid): ?string
    {
        $sql      = <<<SQL
select description from pg_catalog.pg_description where :condition
SQL;

        $where    = Where::create('objoid = $*', [$tableOid]);
        $iterator = $this->executeSql($sql, $where);

        return $iterator->isEmpty() ? null : $iterator->current()['description'];
    }

    /**
     * Return the Oid of the given type name. It Additionally returns the type category.
     *
     * @throws FoundationException
     *
     * @return null|array{oid: int, category: string}
     */
    public function getTypeInformation(string $typeName, string $typeSchema = null): ?array
    {
        $condition = Where::create("t.typname = $*", [$typeName]);
        $sql = <<<SQL
select
    t.oid as "oid",
    t.typcategory as "category"
from
    pg_catalog.pg_type t :join
where
    :condition
SQL;

        if ($typeSchema !== null) {
            $sql = strtr($sql, [':join' => 'join pg_namespace n on n.oid = t.typnamespace']);
            $condition->andWhere('n.nspname = $*', [$typeSchema]);
        } else {
            $sql = strtr($sql, [':join' => '']);
        }

        $iterator = $this->executeSql($sql, $condition);

        return $iterator->isEmpty() ? null : $iterator->current();
    }

    /**
     * Get type category.
     *
     * @throws FoundationException
     *
     * @return null|array{name: string, category: string}
     */
    public function getTypeCategory(int $oid): ?array
    {
        $sql = <<<SQL
select
    case
        when n is null then t.type_name
        else n.nspname||'.'||t.type_name
    end as "name",
    t.typcategory as "category"
from
    pg_catalog.pg_type t
        left join pg_namespace n on n.oid = t.typnamespace
where
    :condition
SQL;
        $iterator = $this->executeSql($sql, Where::create('t.oid = $*', [$oid]));

        return $iterator->isEmpty() ? null : $iterator->current();
    }

    /**
     * Return all possible values from an enumerated type in its natural order.
     *
     * @throws FoundationException
     *
     * @return null|array<int,string>
     */
    public function getTypeEnumValues(int $oid): ?array
    {
        $sql = <<<SQL
with
    enum_value as (
        select
            e.enumlabel as "label"
        from
            pg_catalog.pg_enum e
        where
            :condition
    )
select array_agg(label) as labels from enum_value
SQL;

        $result = $this
            ->executeSql($sql, Where::create('e.enumtypid = $*', [$oid]))
            ->current();

        return $result['labels'];
    }

    /**
     * Return the structure of a composite row.
     *
     * @throws FoundationException
     *
     * @return ConvertedResultIterator<array{name: string, type: string}>
     */
    public function getCompositeInformation(int $oid): ConvertedResultIterator
    {
        $sql = <<<SQL
select
    a.attname as "name",
    t.typname as "type"
from
    pg_type orig
        join pg_catalog.pg_class c      on orig.typrelid = c.oid
        join pg_catalog.pg_attribute a  on a.attrelid = c.oid and a.attnum > 0
        join pg_catalog.pg_type t       on t.oid = a.atttypid
where
    :condition
SQL;

        return $this->executeSql($sql, Where::create('orig.oid = $*', [$oid]));
    }

    /**
     * Return server version.
     *
     * @throws FoundationException
     */
    public function getVersion(): string
    {
        $row = $this->executeSql("show server_version")->current();

        return $row['server_version'];
    }

    /**
     * Launch query execution.
     *
     * @throws FoundationException
     *
     * @param Where|null $condition
     * @param string $sql
     * @return ConvertedResultIterator<mixed>
     */
    protected function executeSql(string $sql, Where $condition = null): ConvertedResultIterator
    {
        $condition = (new Where())->andWhere($condition);
        $sql = strtr($sql, [':condition' => $condition]);

        /** @var PreparedQueryManager $queryManager */
        $queryManager = $this
            ->getSession()
            ->getClientUsingPooler('query_manager', PreparedQueryManager::class);

        return $queryManager->query($sql, $condition->getValues());
    }
}
