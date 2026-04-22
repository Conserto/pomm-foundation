# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) and other coding agents when working with code in this repository.

> **Note:** This is the canonical instructions file. `CLAUDE.md` is a thin pointer to this document — make all future edits here.

## Project

Conserto's fork of Pomm Foundation — a PostgreSQL DBAL for PHP. The supported PHP version and runtime extensions are declared in [`composer.json`](./composer.json) (`require` section); in particular the library uses the native `pgsql` extension directly, not PDO.

## Commands

Composer scripts expose the common tasks. Raw invocations are still shown for cases the scripts don't cover (e.g. running a single test).

```bash
# Install dependencies
composer install

# Full unit test suite — requires a live Postgres DB (see "Test database" below)
composer test

# Static analysis — level and paths in phpstan.neon
composer stan

# Rector preview / apply — target PHP version and active sets in rector.php
composer rector
composer rector:fix

# PHP-CS-Fixer preview / apply — rules in .php-cs-fixer.dist.php
composer cs
composer cs:fix

# Run a single test file
php vendor/bin/phpunit sources/tests/Unit/PommTest.php

# Run a single test method
php vendor/bin/phpunit --filter testConstructor sources/tests/Unit/PommTest.php
```

### Test database

PHPUnit tests hit a real PostgreSQL instance — there is no mocking layer. Bootstrap (`.bootstrap.phpunit.php`) loads `sources/tests/config.php` if present, otherwise falls back to `sources/tests/config.github.php`. The DSN is read from `$GLOBALS['pomm_db1']['dsn']`.

The `pomm_test` database must exist and have the `hstore` and `ltree` extensions enabled (see `.github/workflows/ci.yml` for the exact setup commands). `config.php` is gitignored for per-dev DSN overrides.

## Architecture

Foundation is a layered client/pooler system built around a single `Session` that owns one Postgres `Connection`. Understanding the three collaborating concepts below is the fastest way to be productive here.

### Session + ClientHolder + Pooler triad

- `Session\Session` is the hub. It owns the `Connection` and a `ClientHolder` (the client registry), and exposes `getClientUsingPooler($type, $identifier)` as the main lookup API.
- **Clients** (`Client\ClientInterface`) are stateful objects scoped to a session (e.g. a prepared query, an observer, a converter for one PG type). Each client has a `type` and an `identifier` — uniqueness is `(type, identifier)`.
- **Poolers** (`Client\ClientPoolerInterface`) are per-type factories/caches. When `Session::getClientUsingPooler('prepared_query', $sql)` is called, the registered `PreparedQueryPooler` is asked to return a `PreparedQuery` client — creating it lazily and caching it in the `ClientHolder` on first use.

All subsystems (QueryManager, PreparedQuery, Converter, Observer, Listener, Inspector) follow this pattern. The wiring lives in `SessionBuilder::postConfigure()` (the Foundation-flavored builder, in `sources/lib/SessionBuilder.php`) — that method is the canonical list of pre-registered poolers. The plain `Session\SessionBuilder` is a lower-level base with no poolers; `Foundation\SessionBuilder` extends it and adds the batteries.

### Two SessionBuilder classes (important, easy to confuse)

- `PommProject\Foundation\Session\SessionBuilder` — vanilla builder, no pre-registered poolers/converters. Use when you want a minimal session.
- `PommProject\Foundation\SessionBuilder` — extends the vanilla one; registers the full stack of poolers and all built-in type converters in `initializeConverterHolder()`.

`Pomm` (the service manager, `sources/lib/Pomm.php`) is `ArrayAccess`-keyed by configuration name; each configuration is fed to a `SessionBuilder` (defaulting to the Foundation one, overridable via `class:session_builder`).

### Converters

Registered in `SessionBuilder::initializeConverterHolder()`. Each converter implements `ConverterInterface` (`fromPg`/`toPg`) and is associated with one or more PG type names. Composite/array/range converters wrap base converters, so registration order and the sub-converter lookup through the session matter.

**ConverterHolder ownership** — the Foundation `SessionBuilder::postConfigure()` passes `clone $this->converterHolder` to every `ConverterPooler`, so each session sees a distinct holder instance and session-level `registerConverter` calls do not leak. The clone is **shallow**: the `ConverterInterface` objects inside the holder are shared across sessions. That is fine for the built-in leaf converters (they are stateless), but note that `ArrayTypeConverter` subclasses cache their sub-converter lookups on the instance — a session that overrides a built-in type with its own converter will still see the originally-cached leaf if the array/composite/hstore converter was already used by another session. Replacing built-in leaf types per-session is therefore only reliable on fresh sessions.

Known limitation documented in README: `ConvertedResultIterator` can't resolve custom composite types defined outside the `public` schema (`pg_type` doesn't return the schema).

### Query parameter conversion

`SimpleQueryManager::prepareArguments()` and `PreparedQuery::prepareValues()` run the same per-parameter conversion (`toPgStandardFormat` looked up through the converter pooler), but with deliberately different caching:

- **SimpleQueryManager** re-resolves the `ConverterClient` on every call — simple queries are one-shot by design.
- **PreparedQuery** builds closure converters once in `prepareConverters()` and reuses them for every `execute()`, since the SQL is fixed for the lifetime of the prepared statement.

Do not try to factor this into a shared helper by forcing a single caching policy — the divergence is the point.

### Testing helpers

Tests extending `Tester\VanillaSessionTestCase` or `Tester\FoundationSessionTestCase` get a `buildSession()` method; subclasses must implement `initializeSession(Session $session)` (where fixture clients are typically registered). Both helpers are part of the library's public API — downstream packages (model-manager, etc.) subclass them for their own test suites.

The helpers force `connection:persist => true` so each call to `buildSession()` opens a fresh `pg_connect(..., FORCE_NEW)` backend. Without this, PHPUnit's single-process execution would reuse `pg_pconnect`'s pooled connection across tests and leak prepared statements.

Fixtures live in `sources/tests/Fixture/`.

## Conventions

- PHP strict types are expected everywhere. The minimum PHP version and extension constraints live in [`composer.json`](./composer.json).
- Modern PHP syntax is enforced by Rector — see [`rector.php`](./rector.php) for the active sets and skipped rules.
- Static analysis level and scope are defined in [`phpstan.neon`](./phpstan.neon) (tests are currently excluded).
- Style is enforced by PHP-CS-Fixer — rules live in [`.php-cs-fixer.dist.php`](./.php-cs-fixer.dist.php); run `composer cs` (dry-run) before opening a PR.
- This is a **library** with downstream subclass users: do not change public or protected signatures, even for cleanup (see the Pager / Session / Client API shapes — they are part of the contract).
- File headers (`@author` / `@copyright` docblocks and top-of-file comments) start out crediting Grégoire HUBERT. When a file has been substantially rewritten on the fork, it is fine to update the authorship to Conserto — either by replacing the block for wholly new files, or by adding a Conserto line alongside the original for heavily edited ones. Never remove the pre-existing Grégoire HUBERT notice from a file whose origin content survives (MIT requires preserving the original copyright for substantial portions of the work); the repository-wide notice in [`LICENSE`](./LICENSE) already lists both parties.
- CI matrix (PHP versions, services, steps) is defined in [`.github/workflows/ci.yml`](./.github/workflows/ci.yml) — keep every matrix entry green.
- Always update [`CHANGELOG`](./CHANGELOG) as part of the same change that modifies behaviour, tooling, docs or infra. Add a concise bullet under the in-progress version block — do not batch multiple unrelated changes into a single entry, and do not leave the CHANGELOG to be filled at release time.
