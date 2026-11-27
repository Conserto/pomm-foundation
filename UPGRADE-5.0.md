# Upgrading to 5.0

This release replaces atoum with PHPUnit 11.5 as the project's test framework: downstream packages that ship their own test suites on top of Foundation's `Tester\*` helpers need the changes described in sections 1 to 6.

It also changes the library itself: `ConverterClient::toPgStandardFormat()` may return null (section 7), and the integer, boolean and date converters follow PostgreSQL instead of silently altering values (section 8). Applications writing untyped data (request or import values, test fixtures) to such columns should read section 8.

## 1. Update your test base classes

The atoum helpers are gone:

| Removed (4.x)                                      | Replacement (5.0)                                    |
| -------------------------------------------------- | ---------------------------------------------------- |
| `PommProject\Foundation\Tester\VanillaSessionAtoum` | `PommProject\Foundation\Tester\VanillaSessionTestCase` |
| `PommProject\Foundation\Tester\FoundationSessionAtoum` | `PommProject\Foundation\Tester\FoundationSessionTestCase` |

Both replacements extend `PHPUnit\Framework\TestCase`. The contract is otherwise identical: `buildSession(?string $stamp = null)` and the abstract `initializeSession(Session $session)` are preserved verbatim, so subclasses only need to update the `extends` clause and rewrite the test method bodies themselves.

```diff
-use PommProject\Foundation\Tester\FoundationSessionAtoum;
+use PommProject\Foundation\Tester\FoundationSessionTestCase;

-class MyConverterTest extends FoundationSessionAtoum
+class MyConverterTest extends FoundationSessionTestCase
 {
     protected function initializeSession(Session $session): void { /* … */ }
 }
```

## 2. Rewrite atoum assertions to PHPUnit

Foundation's own migration followed these rules; applying them verbatim to downstream tests is mechanical:

| atoum                                                    | PHPUnit                                                   |
| -------------------------------------------------------- | --------------------------------------------------------- |
| `$this->string($x)->isEqualTo('y')`                      | `self::assertSame('y', $x)`                               |
| `$this->integer($x)->isEqualTo(N)`                       | `self::assertSame(N, $x)`                                 |
| `$this->float($x)->isEqualTo(N)`                         | `self::assertSame(N, $x)`                                 |
| `$this->boolean($x)->isTrue()` / `isFalse()`             | `self::assertTrue($x)` / `assertFalse($x)`                |
| `$this->array($x)->isIdenticalTo([...])`                 | `self::assertSame([...], $x)`                             |
| `$this->array($x)->isEmpty()`                            | `self::assertEmpty($x)` or `assertSame([], $x)`           |
| `$this->array($x)->hasSize(N)` / `->size->isEqualTo(N)`  | `self::assertCount(N, $x)`                                |
| `$this->array($x)->hasKey('k')`                          | `self::assertArrayHasKey('k', $x)`                        |
| `$this->object($x)->isInstanceOf(Y::class)`              | `self::assertInstanceOf(Y::class, $x)`                    |
| `$this->object($x)->isIdenticalTo($y)`                   | `self::assertSame($y, $x)`                                |
| `$this->variable($x)->isNull()` / property `->isNull`    | `self::assertNull($x)`                                    |
| `$this->string($x)->contains('y')`                       | `self::assertStringContainsString('y', $x)`               |
| `$this->string($x)->length->isEqualTo(N)`                | `self::assertSame(N, strlen($x))`                         |
| `$this->newTestedInstance(...)`                          | Explicit `new TestedClass(...)`                           |

### Exceptions

A single atoum chain can assert many exceptions in one test method. PHPUnit's `$this->expectException(...)` only declares one exception per method, so chains with multiple `->exception(...)` blocks must be rewritten with `try { … } catch (…) { … }`:

```diff
-$this->exception(fn () => $obj->doA())
-    ->isInstanceOf(SomeException::class)
-    ->message->contains('boom')
-    ->exception(fn () => $obj->doB())
-    ->isInstanceOf(OtherException::class);
+try {
+    $obj->doA();
+    self::fail('Expected SomeException was not thrown.');
+} catch (SomeException $e) {
+    self::assertStringContainsString('boom', $e->getMessage());
+}
+
+try {
+    $obj->doB();
+    self::fail('Expected OtherException was not thrown.');
+} catch (OtherException $e) {
+}
```

### Mocks

| atoum                                                    | PHPUnit                                                   |
| -------------------------------------------------------- | --------------------------------------------------------- |
| `new Mock\Some\Interface()`                              | `$this->createMock(Interface::class)`                     |
| `$mock->getMockController()->m = 'value'`                | `$mock->method('m')->willReturn('value')`                 |
| `$this->calling($mock)->m = fn (): never => throw $e`    | `$mock->method('m')->willThrowException($e)`              |
| `$this->mock($mock)->call('m')->once()`                  | `$mock->expects(self::once())->method('m')` (declared *before* the action) |
| `$this->mock($mock)->call('m')->withArguments('x')->once()` | `$mock->expects(self::once())->method('m')->with('x')` |

Atoum generated `Mock\…` subclasses automatically. If you were mocking a concrete class with constructor arguments, use `getMockBuilder(Foo::class)->setConstructorArgs([…])->onlyMethods([…])->getMock()`.

## 3. Narrative `assert` calls

atoum's `$this->assert("scenario")` lines are labels for reporting. Drop them, or convert them to one test method per scenario (`#[DataProvider]` also works if the scenarios share a single assertion body).

## 4. Forced new connections

The new `*TestCase` helpers inject `connection:persist => true` into the session builder so that each call to `buildSession()` goes through `pg_connect(..., PGSQL_CONNECT_FORCE_NEW)` instead of `pg_pconnect`. Atoum worked fine with the default (shared `pg_pconnect`) because it forked a process per test method. PHPUnit keeps the whole suite in a single process, so without `FORCE_NEW` the shared backend would hang on to prepared statements from previous tests and surface `prepared statement "…" already exists` on the second call. If you extend `VanillaSessionTestCase` you get this for free; if you build sessions by hand, do the same explicitly.

## 5. Composer script

`composer test` now invokes PHPUnit. The single-file / single-method runners change accordingly:

```diff
-php vendor/atoum/atoum/bin/atoum --no-code-coverage -f sources/tests/Unit/PommTest.php
+php vendor/bin/phpunit sources/tests/Unit/PommTest.php

-php vendor/atoum/atoum/bin/atoum --no-code-coverage -f sources/tests/Unit/PommTest.php -m testConstructor
+php vendor/bin/phpunit --filter testConstructor sources/tests/Unit/PommTest.php
```

## 6. Autoload-dev prefix

The PSR-4 prefix for the package's own tests changed from `PommProject\Foundation\Test\` to `PommProject\Foundation\Tests\` (the `sources/tests/` directory already used the plural name). This does not affect downstream packages — only contributors who patch Foundation's own test tree.

## 7. `ConverterClient::toPgStandardFormat()` may return null

Its return type is widened from `string` to `?string`, matching `ConverterInterface::toPgStandardFormat()`: it returns null when the wrapped converter has no standard format for the value — with Foundation's converters, for a null value (sent to PostgreSQL as SQL NULL). It used to fail with a `TypeError` in that case.

- Subclasses overriding it with a `string` return type remain valid (covariant return type): nothing to change.
- Code calling it directly and relying on a `string` should handle null; static analysers will now report it. None of the Pomm packages (ModelManager, Cli, bundle, Symfony bridge) call it: they use `getConverter()` and call the converter itself.

## 8. Integer, boolean and date values follow PostgreSQL

The integer (`int2`, `int4`, `int8`, `oid`), boolean and date (`date`, `time`, `timestamp`, `timestamptz`) converters used to turn any value into an integer, a boolean or a `DateTime` with PHP's rules, silently storing another value than the one given. They now follow PostgreSQL:

- strings are passed unchanged: PostgreSQL accepts or rejects them by its own input rules, raising a `SqlException`;
- other PHP values follow PostgreSQL's assignment rules, i.e. what PostgreSQL does when storing a value of that type into a column of the target type: a PHP float, which is a `double precision`, is rounded half to even into an integer, as `12.5::float8::int4` gives `12` (a `numeric` such as the SQL literal `12.5::int4` would give `13`); a boolean is not stored into an integer or a date column, a number into a boolean or a date column (`ConverterException`).

| Converter | Value | 4.x wrote | 5.0 |
|---|---|---|---|
| `PgInteger` | `''`, `'abc'` | `0` | rejected by PostgreSQL |
| | `'12.7'` | `12` | rejected by PostgreSQL |
| | `12.7`, `12.5`, `13.5` | `12`, `12`, `13` (truncated) | `13`, `12`, `14` (rounded half to even) |
| | `'99999999999999999999'`, `1e20` | a clamped value | rejected by PostgreSQL (out of range) |
| | `true`, `false` | `1`, `0` | `ConverterException` |
| `PgBoolean` | `'1'`, `'t'`, `'true'`, `'yes'`, `'on'` | `false` | `true` |
| | `''`, `'abc'` | `false` | rejected by PostgreSQL |
| | `1`, `0`, `2`, `1.0` | `false` | `ConverterException` |
| `PgTimestamp` | `''` | now | rejected by PostgreSQL |
| | `'2024-02-30'` | 2024-03-01 | rejected by PostgreSQL |
| | `'+1 day'`, `'12.7'`, `'t'` | tomorrow, today 12:07, now in another time zone | rejected by PostgreSQL |
| | `'26-11-25'` | 2026-11-25 | rejected by PostgreSQL (year-month-day needs four year digits) |
| | `false`, `20240101`, `1.5` | now, 2024-01-01, today 01:05 | `ConverterException` |

Unchanged: integers, integer strings (`'12'`, `' 7 '`, `'+3'`), integer backed enums; PHP booleans; `DateTimeInterface` objects and the date strings PostgreSQL reads (`'2024-01-02'`, `'2024-01-02 03:04:05+02:00'`, `'19940623'`, `'2050/03/29'`, `'now'`, `'today'`). Date strings are now read in the session time zone, which Pomm sets to PHP's default time zone (`connection:configuration` / `timezone`): the result is the same unless that setting is overridden. `'now'` is the start of the current transaction, as in PostgreSQL.

NAN / INF, arrays and non Stringable objects raise a `ConverterException` in the three converters.

To find the values concerned before upgrading, look for writes to integer, boolean and date columns fed by untyped data (request or import values, test fixtures) or by numbers written as booleans (`$x ? 1 : 0`): they now fail loudly instead of storing a wrong value. Parameters without a type hint (`$*`, `$1`) never went through these converters and are unchanged.

## 9. `PgBackedEnum` is replaced with `PgEnum`

`PgEnum` converts both backed enums (a PostgreSQL label maps to the case with the same value) and pure enums (to the case with the same name), the rule Foundation already follows when it turns an enum into a value. `PgBackedEnum` is kept as a deprecated subclass of `PgEnum`, still restricted to backed enums and returning `?\BackedEnum`. It raises a silenced `E_USER_DEPRECATED`, reported by debug tools and deprecation handlers, and will be removed in 6.0: rename it where it is registered.

```php
// 4.x
->registerConverter('Status', new PgBackedEnum(Status::class), ['status', 'public.status'])
// 5.0
->registerConverter('Status', new PgEnum(Status::class), ['status', 'public.status'])
```

It fixes two outputs of `PgBackedEnum` that were invalid:

| Method | Value | 4.x returned | 5.0 |
|---|---|---|---|
| `toPg()` | `Status::A` | `a` (invalid in an array or a composite type) | `status 'a'` |
| `toPgStandardFormat()` | `null` | `'NULL::status'` (rejected by PostgreSQL) | `null` (SQL NULL) |

- Code calling these methods directly and adapting their output (adding quotes, testing for `'NULL::'`) must drop that adaptation.
- A subclass of `PgBackedEnum` overriding `toPg()` also changed the `$*::status` parameters, since `toPgStandardFormat()` called `toPg()`. Extend `PgEnum` and override `toPgStandardFormat()` too.
- `PgEnum` accepts a raw value besides the cases when writing (`'a'`, `2` / `'2'` for an int backed enum, `'Active'` for a pure enum, or a `Stringable` giving one of them), so code writing raw values can register it without changing its writes. An unknown value raises a `ConverterException`.
- For an int backed enum, a label matches only the value as the case writes it (`'2'` for `2`), so every label read can be written back. `'02'`, `' 2'` or `'2.0'`, which PHP converted to `2`, raise a `ConverterException`, as `'two'` does (it used to raise a `TypeError`).
- Error messages change: `Value "x" not found in enum "Status".`, `Enum "X" does not exist.`, `"X" is not an enum.`, and for `PgBackedEnum` given a pure enum, `"X" is not a backed enum: use PgEnum.`
