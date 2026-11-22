# Troubleshooting

Most issues reduce to one of a few recurring shapes. Match your symptom
to the section below.

## "My listener never fires"

### 1. Are you registering on the same registry?

The [`Events` facade](Events-Facade.md) and `new EventEmitter()` are
**separate** registries. A listener added with `Events::on()` will not
fire when you call `(new EventEmitter())->emit()`, and vice versa.

```php
// ❌ different registries
Events::on('e', $listener);
(new EventEmitter())->emit('e'); // listener does not run

// ✅ same registry
$bus = new EventEmitter();
$bus->on('e', $listener);
$bus->emit('e');
```

### 2. Are the event names matched?

Names are **case-insensitive** but **whitespace-sensitive**. The
following pairs all match:

```php
Events::on('User.Registered', …);
Events::trigger('user.registered');     // ✅ matches
Events::trigger('USER.REGISTERED');     // ✅ matches
```

But these do not:

```php
Events::on('user.registered', …);
Events::trigger('user.registered ');    // ❌ trailing space
Events::trigger('user_registered');     // ❌ different separator
```

### 3. Are you registering *after* you trigger?

```php
Events::trigger('boot');                // fires zero listeners
Events::on('boot', $bootHandler);       // too late
```

In long-lived processes (workers, daemons) this is obvious, but it
trips up scripts that build the listener list lazily. Register first,
trigger second.

### 4. Is simulate mode on?

`Events::setSimulate(true)` makes `trigger()` walk the list **without**
calling any listener. If a previous test or CLI flag left it on,
nothing visible happens.

```php
var_dump(Events::getSimulate()); // expect false
```

See [Simulate & Debug Mode](Simulate-and-Debug.md).

### 5. Did an earlier listener veto the chain?

`Events::trigger()` stops the moment any listener returns strict
`false`. Check the return value:

```php
$ok = Events::trigger('save');
if ($ok === false) {
    echo "A listener vetoed the chain.\n";
}
```

Toggle debug mode briefly to inspect which listeners actually executed:

```php
Events::setDebugMode(true);
Events::trigger('save');
print_r(Events::getDebug());
```

See [Stopping Propagation](Stopping-Propagation.md).

## "I get `\InvalidArgumentException`"

The package throws this whenever an argument fails a basic type or
shape check:

| Message contains | Cause |
| --- | --- |
| `$event must be a string` | You passed `null`, an int, or an object to `on()`/`once()`/`emit()`/`trigger()`. |
| `$listener must be a callable` | The second argument to `on()`/`once()`/`removeListener()` was not a callable. Most often a typo in a function name or an `[$obj, 'method']` pair where the method does not exist. |
| `$priority must be an integer` | The third argument was a string like `'100'` or a float. |
| `$arguments must be an array` | The second argument to `EventEmitter::emit()` was not an array. (`Events::trigger` uses varargs, so this only affects the low-level API.) |
| `$simulate must be a boolean` | Passed a non-bool to `Events::setSimulate()`. |
| `$debugMode must be a boolean` | Passed a non-bool to `Events::setDebugMode()`. |

Fix the call site; there is no global toggle to weaken these checks.

## "`emit()` fires listeners that were silent before the upgrade"

If you upgraded from `initphp/event-emitter` tag `1.0` and now your
`emit()` calls fire listeners that previously seemed silent, that is
**expected**: tag `1.0` of the standalone package shipped with a bug
where the entire listeners array (rather than each listener) was passed
to `call_user_func_array`, so listeners never ran. The bug was fixed in
`initphp/event-emitter` `1.0.1` and is not present in any `initphp/events`
release.

If your old code relied on listeners *not* firing, audit those code
paths before deploying. See [Migration Guide](Migration-Guide.md).

## "Listeners run in the wrong order despite my `$priority`"

If you are on `initphp/events` 2.1.0 or later, this should not happen — the
dispatcher honours priority order (lower numeric value runs first;
within a priority bucket, registration order). Check:

1. **Are you actually on 2.1.0 or later?** `composer show initphp/events`.
   The priority bug was in every earlier release (1.x and 2.0.0).
2. **Are your priorities really different?** Listeners that share a
   priority run in registration order; if both are `PRIORITY_NORMAL`,
   the one registered first wins.
3. **Is the listener you're staring at attached to a different event
   name (case-folded differently)?** See the "event names matched"
   section above.

If you are still on a release before 2.1.0 and seeing registration-order
behaviour, that *was* the bug. Upgrade to 2.1.0 or later; see
[Migration Guide](Migration-Guide.md).

Full ordering contract: [Listeners & Priorities](Listeners-and-Priorities.md).

## "I can't remove an anonymous listener"

`EventEmitter::removeListener()` uses `===` to identify the listener
you registered. Two separate closures, even with identical bodies, are
not equal:

```php
$bus->on('e', function () { /* … */ });
// later
$bus->removeListener('e', function () { /* … */ }); // ❌ no-op
```

Keep a reference:

```php
$listener = function () { /* … */ };
$bus->on('e', $listener);
// later
$bus->removeListener('e', $listener); // ✅ works
```

Or use a named callable form (string function name, `[$obj, 'method']`,
or an invokable object) — those are easier to pass back later.

## "`Events::getDebug()` returns a huge array in a long-running worker"

Debug mode accumulates one row per listener invocation. Either:

1. Call `Events::clearDebug()` at the boundary of each job/request to
   empty the log without disabling debug mode.
2. Call `Events::reset()` at the same boundary to drop the singleton
   entirely (clears the debug log, the simulate / debug flags, and
   every registered listener — re-register on the next iteration).
3. Disable debug mode (`Events::setDebugMode(false)`) when you no
   longer need it. Subsequent triggers stop adding rows; existing rows
   stay until you `clearDebug()`/`reset()` or the process exits.
4. Use the [`EventEmitter`](EventEmitter.md) and roll a tiny profiler of
   your own — `microtime(true)` plus an array — with whatever
   retention policy you need.

See [Simulate & Debug Mode › Debug mode](Simulate-and-Debug.md#debug-mode).

## "PSR-4 / `class not found` errors"

Make sure you ran `composer dump-autoload` after installing, and that
your script is loading `vendor/autoload.php`. The package exports two
roots:

- `InitPHP\Events\*` (canonical PSR-4 → `src/`)
- `InitPHP\EventEmitter\*` (alias, loaded via `src/aliases.php`)

The alias is wired up by Composer's `files` autoload, so it is
available the instant `vendor/autoload.php` is included. If you are
seeing `InitPHP\EventEmitter\EventEmitter not found`, your autoloader is
probably not loading `aliases.php` — re-run `composer dump-autoload`.

## "I want to reset everything between tests"

Call `Events::reset()` in `setUp()` (and/or `tearDown()`). It drops the
shared singleton entirely — listeners, simulate / debug flags, and the
debug log all go with it; the next facade call rebuilds a fresh
`Event` instance.

```php
final class MyTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        Events::reset();
    }

    protected function tearDown(): void
    {
        Events::reset();
    }
}
```

Other tools in the same family:

- `Events::setInstance($preconfigured)` — inject a dispatcher with
  flags already set, instead of toggling them at the start of every
  test.
- `Events::removeAllListeners()` — drop listeners without dropping the
  flags/debug log.
- `Events::clearDebug()` — empty the debug log only.

If you would rather avoid the static facade entirely in tests,
instantiate `new Event()` (or `new EventEmitter()`) per test — neither
keeps any global state.

## Still stuck?

Open an issue with a minimal reproduction:

- [github.com/InitPHP/Events/issues](https://github.com/InitPHP/Events/issues)
- Include PHP version, `initphp/events` version, and a short snippet
  that demonstrates the unexpected behaviour.
