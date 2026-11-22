# Once-Listeners & Removal

This page covers the operations that control a listener's lifetime:
`once`, `off` (or `removeListener` on the emitter), `removeAllListeners`,
and `clearOnceListeners`.

## `once()` — fire at most one time

```php
$dispatcher->once('tick', function (): void {
    echo "first tick" . PHP_EOL;
});

$dispatcher->trigger('tick');   // "first tick"
$dispatcher->trigger('tick');   // (nothing)
$dispatcher->trigger('tick');   // (nothing)
```

The listener is dropped from the registry after the first `trigger()`
(or `emit()`) of the event. Everything else — priority, FIFO within a
priority, case-insensitive event names, argument unpacking — is identical
to `on()`; see [Listeners & Priorities](Listeners-and-Priorities.md).

### Two non-obvious guarantees

1. **The once-contract survives a short-circuit.** If a listener
   *earlier* in the chain returns `false` and halts dispatch, the
   one-shot listeners that did not get a chance to run are still
   dropped. The contract is "fire at most once", not "fire exactly
   once". `Event::trigger()` implements this with a `try/finally` block
   that calls `EventEmitter::clearOnceListeners()` however the loop
   exits.

   ```php
   $dispatcher
       ->on('halt', function () { return false; }, 10)
       ->once('halt', function () { echo "never runs"; }, 20);

   $dispatcher->trigger('halt');   // halted at priority 10
   $dispatcher->trigger('halt');   // once-listener still gone — does not fire
   ```

2. **The once-contract survives a listener exception.** On `Event`, the same
   `try/finally` cleans the once registry even if a listener throws. On
   `EventEmitter::emit()`, the event's one-shot listeners are removed
   *before* the listeners are invoked, so a throwing listener cannot leave
   them behind either. In both cases the exception still propagates.

### Simulate mode counts as a fire

In [simulate mode](Simulate-and-Debug.md#simulate-mode) `trigger()` does not
invoke listener bodies, but it still drops the event's one-shot listeners
afterwards.

## `off()` — remove a specific listener

```php
$cb = function (): void { /* ... */ };

$dispatcher->on('e', $cb);
$dispatcher->off('e', $cb);
$dispatcher->trigger('e');      // $cb does not fire
```

On `Event` (and therefore `Events`), `off()` forwards to
`EventEmitter::removeListener()`; the shorter name reads naturally as a
counterpart to `on()`. It removes the listener from both the regular and the
one-shot registries, and is a no-op if the listener is not registered.

### Identity, not equality

Listener identity is determined by **strict `array_search`**:

- **Closures** match only by instance. Two closures built from identical
  source code are two different PHP objects, so this removes nothing:

  ```php
  $dispatcher->on('e', function () { echo 'a'; });
  $dispatcher->off('e', function () { echo 'a'; });   // does NOT remove anything
  ```

  The first-class callable syntax (`$service->onTick(...)`) also builds a new
  `Closure` every time it is evaluated. If you need to remove a closure, hold a
  reference to the original.

- **Function names and method pairs** match by value. `'my_function'`,
  `[$object, 'method']` and `[ClassName::class, 'method']` compare equal to
  an equivalent value you build again at the call site:

  ```php
  $dispatcher->on('tick', [$service, 'onTick']);
  $dispatcher->off('tick', [$service, 'onTick']);   // matches — same object, same method
  ```

### One occurrence per priority slot

If you registered the same listener at several priorities, one `off()` call
removes it from each of those priority slots. If you registered it twice at
the *same* priority, one call removes only one of the two:

```php
$dispatcher->on('e', $cb);
$dispatcher->on('e', $cb);
$dispatcher->off('e', $cb);     // $cb is still registered once
```

If you need exact control, register each listener only once.

## `removeAllListeners()` — wipe one event or every event

```php
$dispatcher->removeAllListeners('save.user');   // drops every listener for this event
$dispatcher->removeAllListeners();              // drops every listener for every event
```

This is the right tool for:

- **Tests** that need to reset state between cases. (`Events::reset()` is even
  cleaner for the facade — it drops the whole singleton.)
- **Long-running workers** that need to reset listener state at the start of
  each job, where building a fresh dispatcher per job is not an option.
- **Plugin systems** that unload a plugin and want to scrub every listener it
  registered. (Remembering exactly which listeners you registered is a better
  pattern when feasible — see
  [Recipes › A tiny plugin system](Recipes.md#12-a-tiny-plugin-system).)

`removeAllListeners()` drops both regular and one-shot listeners for the
targeted event(s).

## `clearOnceListeners()` — low-level cleanup of one-shots

```php
$bus = new EventEmitter();

$bus->once('e', $listener);
$bus->clearOnceListeners('e');      // drops $listener without invoking it
$bus->clearOnceListeners();         // drops every one-shot for every event
```

This is an `EventEmitter`-level primitive; it is not exposed on `Event` /
`Events` (reach it through `Events::getEmitter()` if you must). It exists so
that higher-level dispatchers like `Event::trigger()` — which run listeners
themselves and need to handle short-circuit and exception paths — can honour
the once-contract without going through `emit()`. Regular `on()` listeners are
never touched.

If you find yourself reaching for `clearOnceListeners()` from application
code, you are probably either building your own dispatcher on top of
`EventEmitter` (which is exactly what `Event` does), or doing something the
package's higher-level layers already do for you.

## Summary

| Goal | High-level (`Event` / `Events`) | Low-level (`EventEmitter`) |
| --- | --- | --- |
| Register a regular listener | `on()` | `on()` |
| Register a one-shot listener | `once()` | `once()` |
| Remove a specific listener | `off()` | `removeListener()` |
| Remove every listener for an event | `removeAllListeners('e')` | `removeAllListeners('e')` |
| Remove every listener for every event | `removeAllListeners()` | `removeAllListeners()` |
| Drop one-shots without invoking them | (handled internally by `trigger()`) | `clearOnceListeners()` |
| Reset the static facade entirely | `Events::reset()` | — |

## See also

- [`Events` facade](Events-Facade.md) and [`EventEmitter`](EventEmitter.md) —
  the two APIs that expose these controls.
- [Stopping Propagation](Stopping-Propagation.md) — how a `false` return
  interacts with once-listeners.
- [API Reference](API-Reference.md) — exact signatures and exceptions.
