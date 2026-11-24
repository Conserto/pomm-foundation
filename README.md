# Foundation (Conserto fork)

[![Latest Stable Version](https://poser.pugx.org/conserto/pomm-foundation/v/stable)](https://packagist.org/packages/conserto/pomm-foundation)
![CI Status](https://github.com/conserto/pomm-foundation/actions/workflows/ci.yml/badge.svg)
[![Monthly Downloads](https://poser.pugx.org/conserto/pomm-foundation/d/monthly.png)](https://packagist.org/packages/conserto/pomm-foundation)
[![License](https://poser.pugx.org/conserto/pomm-foundation/license.svg)](https://packagist.org/packages/conserto/pomm-foundation)

> **This is a maintained fork** of [pomm-project/foundation](https://github.com/pomm-project/foundation) by Grégoire HUBERT. Conserto maintains this fork to keep the library working on modern PHP versions, ship bug fixes, and extend the converter stack. Issues and pull requests should be filed against **this** repository.

## Differences from upstream

 * PHP 8.4+ required (upstream targeted PHP 8.1).
 * Rector-driven modernization of type declarations and PHP 8.x syntax.
 * Extra converters and enum compatibility: `PgBackedEnum`, enum-aware `PgString` / `PgInteger` / `PgFloat`.
 * Active CI on PHP 8.4 and 8.5.
 * See [CHANGELOG](CHANGELOG) for the detailed release history.

## What is Foundation ?

Foundation is the main block of Pomm database framework. It makes clients able to communicate either with the database or with each others through a session. One of these clients -- the query manager -- can make Foundation to be used as DBAL replacement. If you are looking for a library to use PostgreSQL in your web development, you might want to look at [Pomm’s model manager](https://github.com/pomm-project/ModelManager). If you want to create a custom database access layer or just perform SQL queries, Foundation is the right tool.

Foundation provides out of the box:

 * Converters (all built-in Postgresql types are supported + arrays, HStore etc.) see [this SO comment](http://stackoverflow.com/questions/31643297/pg-query-result-contains-strings-instead-of-integer-numeric/31740990#31740990).
 * Prepared Queries.
 * Parametrized queries.
 * Seekable iterators on results.
 * LISTEN / NOTIFY asynchronous messages support.
 * Service manager for easy integration with dependency injection containers.

## Requirements

 * PHP >= 8.4
    * ext-pgsql (not PDO)
 * PostgreSQL (tested on recent versions; older ones may work but are not covered by CI)

## Installation

This fork is distributed on Packagist as [`conserto/pomm-foundation`](https://packagist.org/packages/conserto/pomm-foundation). Add it with Composer:

```bash
composer require conserto/pomm-foundation
```

**Note:** It is important the PHP configuration file defines the correct [timezone](http://php.net/manual/en/datetime.configuration.php) setting. Pomm also sets the PostgreSQL connection to this timezone to prevent time shifts between the application and the database.

## Documentation

Foundation’s documentation is available [either online](https://github.com/conserto/pomm-foundation/blob/main/documentation/foundation.rst) or directly in the `documentation` folder of the project.

## Tests

This package uses [PHPUnit](https://phpunit.de/) as unit test framework. The tests are located in `sources/tests`. The test suite needs to access the database to ensure that read and write operations are made in a consistent manner. You need to set up a database for that and fill the `sources/tests/config.php` file with the according DSN. For convenience, Foundation provides two PHPUnit base classes that build a `Session`:

 * `PommProject\Foundation\Tester\VanillaSessionTestCase`
 * `PommProject\Foundation\Tester\FoundationSessionTestCase`

Making your test class extend one of these grants it a `buildSession` method that returns a newly created session. Subclasses must implement `initializeSession(Session $session)` (even as an empty method). It is often a good idea to provide a fixture class as a session client — `initializeSession` is the right place to register it.

## Known bugs

Unfortunately there is a bug we can not fix easily or without degrading performances of the whole stack:
* The `ConvertedResultIterator` can not recognize custom composite types when they are defined in schemas other than `public`. This is because the `pg_type` function does not return the schema the type belongs to. There are not turns around unless the schema is inspected manually by issuing a lot of queries. (see #53)

## License

Foundation is released under the MIT license — see [LICENSE](LICENSE). Original copyright © 2014 Grégoire HUBERT, fork maintenance © 2022-present Conserto.