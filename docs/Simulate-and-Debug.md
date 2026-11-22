# Simulate & Debug Mode

The [`Events` facade](Events-Facade.md) (and the underlying
[`Event`](API-Reference.md#class-initphpeventsevent) class it wraps) ships
two opt-in modes for tests, dry-runs, and observability:

- **Simulate mode** — `trigger()` walks the listener list as usual but
  does not actually call the listeners.
- **Debug mode** — `trigger()` records a timing breadcrumb per listener
  to an in-memory log you can retrieve.

Both modes are per-process; toggle them with the static setters and
inspect with the matching getters.

> The low-level [`EventEmitter`](EventEmitter.md) class does **not** expose
> simulate or debug; both features live one level up in `Event`.

## Simulate mode

When simulate is `true`, `Events::trigger()`:

1. Still resolves the listener list for the event name.
2. **Skips** every `call_user_func_array()` call — listeners are never
   invoked.
3. Treats every listener as if it returned `true`, so the chain never
   short-circuits.
4. Returns `true` from `trigger()`.

That last point is important: simulate mode **cannot** observe veto
returns (`return false`), because no listener actually runs.

```php
use InitPHP\Events\Events;

$emails = 0;
Events::on('mailout', function () use (&$emails) {
    $emails++;
    mail(/* … real send … */);
});

Events::setSimulate(true);

Events::trigger('mailout'); // returns true; $emails stays 0
Events::trigger('mailout'); // same

Events::setSimulate(false);

Events::trigger('mailout'); // now actually sends — $emails == 1
```

### When to enable it

- **Tests** that should exercise the registration logic without
  triggering real side effects.
- **Dry-run commands** (`--simulate` style CLIs) where users want to see
  which hooks *would* fire.
- **Debug surfaces** ("preview what saving this would trigger") in admin
  panels.

### Common pitfall

Simulate is **global** state on the singleton facade. Forgetting to
`setSimulate(false)` after a test will silently disable every following
`trigger()` in the same process. In test suites, the cleanest fix is
`Events::reset()` in `setUp()` / `tearDown()` — that drops the
singleton entirely, including the simulate/debug flags *and* every
registered listener:

```php
protected function setUp(): void
{
    \InitPHP\Events\Events::reset();
}

protected function tearDown(): void
{
    \InitPHP\Events\Events::reset();
}
```

If you prefer to keep the listeners around between tests but reset
just the flags, the explicit setters still work:

```php
protected function tearDown(): void
{
    \InitPHP\Events\Events::setSimulate(false);
    \InitPHP\Events\Events::setDebugMode(false);
    \InitPHP\Events\Events::clearDebug();
}
```

## Debug mode

When debug is `true`, every iteration of `Events::trigger()` appends one
record to an internal array:

```php
[
    'start' => float,   // microtime(true) before the listener
    'end'   => float,   // microtime(true) after the listener
    'event' => string,  // the (case-preserving) name passed to trigger()
]
```

You retrieve the records with `Events::getDebug()`. The array
accumulates across every `trigger()` call until the process ends or
you call `Events::clearDebug()`.

```php
use InitPHP\Events\Events;

Events::setDebugMode(true);

Events::on('boot', function () { usleep(1_500); });
Events::on('boot', function () { usleep(  400); });
Events::on('warm', function () { usleep(  200); });

Events::trigger('boot');
Events::trigger('warm');

print_r(Events::getDebug());
/*
Array
(
    [0] => Array ( [start] => 1716567121.4521  [end] => 1716567121.4538  [event] => boot )
    [1] => Array ( [start] => 1716567121.4538  [end] => 1716567121.4542  [event] => boot )
    [2] => Array ( [start] => 1716567121.4542  [end] => 1716567121.4544  [event] => warm )
)
*/
```

### Each listener gets its own row

The number of debug records equals the number of listener invocations.
A single `trigger('boot')` with two listeners produces **two** records,
both with `event => 'boot'`. The duration of an individual listener is
`end - start`; the duration of the entire `trigger()` call is the sum of
its rows (or, more precisely, the span from the first listener's `start`
to the last listener's `end`).

### Debug + simulate

When both modes are on, you still get one debug record per listener,
with `start` and `end` essentially equal (the call itself was skipped):

```php
Events::setSimulate(true);
Events::setDebugMode(true);
Events::trigger('boot');

// debug rows are emitted, but end - start is near-zero
```

This is useful when you want to inspect which listeners *would* run
without actually executing them.

### Trimming the buffer

`getDebug()` returns a copy of the live array. To reset it between
phases, call `Events::clearDebug()`:

```php
Events::setDebugMode(true);
// … run phase 1 …
$phase1 = Events::getDebug();
Events::clearDebug();

// … run phase 2 …
$phase2 = Events::getDebug();
```

`clearDebug()` returns the shared `Event` instance, so it is chainable.
In a long-running worker you can also call `Events::reset()` at the
boundary of each job to drop the singleton entirely — that wipes the
debug log along with every registered listener.

If you need finer-grained control, build your own profiling listener on
top of the [`EventEmitter`](EventEmitter.md) — `microtime(true)` plus an
array is the entire mechanism.

### Observing in tests

```php
public function test_trigger_records_a_debug_row(): void
{
    \InitPHP\Events\Events::setDebugMode(true);
    \InitPHP\Events\Events::on('audit', fn() => null);

    \InitPHP\Events\Events::trigger('audit');

    $debug = \InitPHP\Events\Events::getDebug();
    self::assertNotEmpty($debug);
    self::assertSame('audit', end($debug)['event']);
}
```

Remember to disable both modes in `tearDown`.

## Putting them together

A common pattern in CLI dry-run mode:

```php
$dry = in_array('--dry-run', $argv, true);

\InitPHP\Events\Events::setSimulate($dry);
\InitPHP\Events\Events::setDebugMode($dry);

// … register listeners, run the pipeline …
\InitPHP\Events\Events::trigger('export', $payload);

if ($dry) {
    echo "Would have run these listeners:\n";
    foreach (\InitPHP\Events\Events::getDebug() as $i => $row) {
        printf("  #%d  %s\n", $i + 1, $row['event']);
    }
}
```

## See also

- [`Events` facade](Events-Facade.md) — the static API that exposes both
  modes.
- [API Reference](API-Reference.md) — exact signatures and return types.
- [Recipes](Recipes.md) — practical patterns built on simulate/debug.
