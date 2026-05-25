# Once-listeners, removal, and cleanup

This chapter covers the four operations that let you control a
listener's lifetime: `once`, `off` (or `removeListener` on the
emitter), `removeAllListeners`, and `clearOnceListeners`.

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
(or `emit()`) of the event.

### Two non-obvious guarantees

1. **The once-contract survives a short-circuit.** If a listener
   *earlier* in the chain returns `false` and halts dispatch, the
   one-shot listeners that did not get a chance to run are still
   dropped. The contract is "fire at most once", not "fire exactly
   once". This is implemented via a `try/finally` block in
   `Event::trigger()` that calls
   `EventEmitter::clearOnceListeners()` regardless of how the loop
   exited.

   ```php
   $dispatcher
       ->on('halt', function () { return false; }, 10)
       ->once('halt', function () { echo "never runs"; }, 20);

   $dispatcher->trigger('halt');   // halted at priority 10
   $dispatcher->trigger('halt');   // once-listener still gone — does not fire
   ```

2. **The once-contract survives a listener exception.** Same
   mechanism — `try/finally` ensures the once registry is cleaned up
   even if an earlier listener throws. The exception still
   propagates; you just don't end up with a dangling once-listener.

### `once()` vs `on()` semantics aside from "how many times"

Everything else — priority, FIFO within priority, case-insensitive
event names, argument unpacking — is identical to `on()`. See
[chapter 4](04-priorities-and-ordering.md).

## `off()` — remove a specific listener (high-level)

```php
$cb = function (): void { /* ... */ };

$dispatcher->on('e', $cb);
$dispatcher->off('e', $cb);
$dispatcher->trigger('e');      // $cb does not fire
```

On `Event` (and therefore `Events`), `off()` is a thin alias that
forwards to `EventEmitter::removeListener()`. The shorter name reads
naturally as a counterpart to `on()`.

### Identity, not equality

Listener identity is determined by **strict `array_search`** — the
same callable instance, not a textually-equivalent rebuild:

```php
$dispatcher->on('e', function () { echo 'a'; });
$dispatcher->off('e', function () { echo 'a'; });   // does NOT remove anything
```

Two `Closure`s built from identical source code are two different
PHP objects. If you need to remove a closure, hold a reference to
the original.

For methods, both `[$obj, 'method']` arrays and first-class callable
syntax (`$obj->method(...)`) produce a new callable each time you
write the expression. Build it once, pass the same value to `on()`
and `off()`:

```php
$listener = [$service, 'onTick'];

$dispatcher->on('tick', $listener);
$dispatcher->off('tick', $listener);   // matches — same array
```

### Removes from every priority slot

If you registered the same listener at multiple priorities (rare but
possible), one `off()` call removes *all* of them. This is rarely the
shape callers want; if you need per-priority removal, register the
listener only once.

## `removeAllListeners()` — wipe one event or every event

```php
$dispatcher->removeAllListeners('save.user');   // drops every listener for this event
$dispatcher->removeAllListeners();              // drops every listener for every event
```

This is the right tool for:

- **Tests** that need to reset state between cases. (Though
  `Events::reset()` is even cleaner for the facade — it drops the
  whole singleton.)
- **Long-running workers** that need to reset listener state at the
  start of each job, where building a fresh dispatcher per job is
  not an option.
- **Plugin systems** that unload a plugin and want to scrub every
  listener it registered. (Though "remember exactly which listeners
  I registered" is a better pattern when feasible.)

`removeAllListeners()` drops both regular and one-shot listeners for
the targeted event(s).

## `clearOnceListeners()` — low-level cleanup of one-shots

```php
$bus = new EventEmitter();

$bus->once('e', $listener);
$bus->clearOnceListeners('e');      // drops $listener without invoking it
$bus->clearOnceListeners();         // drops every one-shot for every event
```

This is an `EventEmitter`-level primitive (not exposed on `Event` /
`Events`, because applications rarely need it). It exists so that
higher-level dispatchers like `Event::trigger()` — which run
listeners themselves and need to handle short-circuit / exception
paths — can honour the once-contract without going through `emit()`.

If you find yourself reaching for `clearOnceListeners()` from
application code, you are probably either:

- Building your own dispatcher on top of `EventEmitter` (totally
  fine, that is exactly what `Event` does), or
- Doing something the package's higher-level layers already do for
  you. Double-check before adding the call.

## Summary table

| Goal | High-level (`Event` / `Events`) | Low-level (`EventEmitter`) |
| --- | --- | --- |
| Register a regular listener | `on()` | `on()` |
| Register a one-shot listener | `once()` | `once()` |
| Remove a specific listener | `off()` | `removeListener()` |
| Remove every listener for an event | `removeAllListeners('e')` | `removeAllListeners('e')` |
| Remove every listener for every event | `removeAllListeners()` | `removeAllListeners()` |
| Drop one-shots without invoking them | (handled internally by `trigger()`) | `clearOnceListeners()` |
| Reset the static facade entirely | `Events::reset()` | — |

## Next

- [Chapter 6 — Debug and simulate modes](06-debug-and-simulate.md)
- [Chapter 8 — Recipes](08-recipes.md) — plugin systems, request
  lifecycle hooks, WordPress-style hooks.
