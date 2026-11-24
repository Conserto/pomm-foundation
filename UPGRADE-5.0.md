# Upgrading to 5.0

This release replaces atoum with PHPUnit 11.5 as the project's test framework. The library code itself is unchanged — only the test helpers and the autoload-dev prefix are affected. Downstream packages that ship their own test suites on top of Foundation's `Tester\*` helpers need the changes described below.

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
