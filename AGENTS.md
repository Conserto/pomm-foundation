# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) and other coding agents when working with code in this repository.

> **Note:** This is the canonical instructions file. `CLAUDE.md` is a thin pointer to this document — make all future edits here.

## Project

Conserto's fork of Pomm Foundation — a PostgreSQL DBAL for PHP. Requires PHP >= 8.4 and `ext-pgsql` (NOT PDO — the library uses the native `pgsql` extension directly). Package name: `conserto/pomm-foundation`.

## Commands

Composer scripts expose the common tasks. Raw invocations are still shown for cases the scripts don't cover (e.g. running a single test).

```bash
# Install dependencies
composer install

# Full unit test suite — requires a live Postgres DB (see "Test database" below)
composer test

# Static analysis (PHPStan level 6, scope = sources/lib only)
composer stan

# Rector preview / apply (configured for PHP 8.4; see rector.php)
composer rector
composer rector:fix

# PHP-CS-Fixer preview / apply (config in .php-cs-fixer.dist.php)
composer cs
composer cs:fix

# Run a single test file
php vendor/atoum/atoum/bin/atoum --no-code-coverage -f sources/tests/Unit/Pomm.php

# Run a single test method
php vendor/atoum/atoum/bin/atoum --no-code-coverage -f sources/tests/Unit/Pomm.php -m testConstructor
```

### Test database

Atoum tests hit a real PostgreSQL instance — there is no mocking layer. Bootstrap (`.bootstrap.atoum.php`) loads `sources/tests/config.php` if present, otherwise falls back to `sources/tests/config.github.php`. The DSN is read from `$GLOBALS['pomm_db1']['dsn']`.

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

Registered in `SessionBuilder::initializeConverterHolder()`. Each converter implements `ConverterInterface` (`fromPg`/`toPg`) and is associated with one or more PG type names. `ConverterHolder` is cloned per session so per-session registrations don't leak. Composite/array/range converters wrap base converters, so registration order and the sub-converter lookup through the session matter.

Known limitation documented in README: `ConvertedResultIterator` can't resolve custom composite types defined outside the `public` schema (`pg_type` doesn't return the schema).

### Testing helpers

Tests extending `Tester\VanillaSessionAtoum` or `Tester\FoundationSessionAtoum` get a `buildSession()` method; subclasses must implement `initializeSession(Session $session)` (where fixture clients are typically registered). Fixtures live in `sources/tests/Fixture/`.

## Conventions

- PHP 8.4+ features and strict types are expected (Rector enforces the `UP_TO_PHP_84` level set).
- PHPStan level 6 over `sources/lib` only — tests are not statically analyzed.
- PHP-CS-Fixer (`@PSR12` + `@PHP84Migration`) is available via `composer cs` / `composer cs:fix`; run the dry-run before opening a PR.
- Rector skips `AddOverrideAttributeToOverriddenMethodsRector` (noisy) and `NewInInitializerRector` (incompatible with Atoum).
- This is a **library** with downstream subclass users: do not change public or protected signatures, even for cleanup (see the Pager / Session / Client API shapes — they are part of the contract).
- CI matrix runs on PHP 8.4 and 8.5; keep both green.
