# API Reference

Complete reference for every public class, interface, method, and
constant exported by `initphp/events`. Companion narrative pages are
linked where useful.

> Signatures are shown with their documented types. The source keeps untyped
> declarations (for example `on($event, $listener, $priority = 100)`) and
> enforces the types at runtime with `\InvalidArgumentException`.

- Namespace: `InitPHP\Events\*`
- Composer package: [`initphp/events`](https://packagist.org/packages/initphp/events)
- Source tree: [`src/`](https://github.com/InitPHP/Events/tree/main/src)

## Class: `InitPHP\Events\Events`

The static facade. Defined in [`src/Events.php`](https://github.com/InitPHP/Events/blob/main/src/Events.php).
Detailed narrative: [`Events` facade](Events-Facade.md).

```php
class Events
{
    const PRIORITY_HIGH   = Event::PRIORITY_HIGH;   // 10
    const PRIORITY_NORMAL = Event::PRIORITY_NORMAL; // 100
    const PRIORITY_LOW    = Event::PRIORITY_LOW;    // 200
}
```

Most calls are forwarded via `__callStatic` (and a mirrored `__call`)
to a lazily-constructed singleton [`Event`](#class-initphpeventsevent)
instance. Three lifecycle methods are defined on the facade itself:
`getInstance`, `setInstance`, and `reset`.

### Constants

| Name | Value | Purpose |
| --- | --- | --- |
| `PRIORITY_HIGH` | `10` | Semantic shorthand for "run early". |
| `PRIORITY_NORMAL` | `100` | Default for both `Events::on()` and `EventEmitter::on()`. |
| `PRIORITY_LOW` | `200` | Semantic shorthand for "run late". |

> **Ordering.** See [Listeners & Priorities](Listeners-and-Priorities.md) —
> listeners are dispatched in ascending numeric priority order, with
> FIFO inside each priority bucket.

### Static methods (forwarded to `Event`)

```php
public static function on(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL): Event
public static function once(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL): Event
public static function off(string $name, callable $callback): Event
public static function removeAllListeners(?string $name = null): Event
public static function trigger(string $name, ...$arguments): bool

public static function setSimulate(bool $simulate = false): Event
public static function getSimulate(): bool

public static function setDebugMode(bool $debugMode = false): Event
public static function getDebugMode(): bool
public static function getDebug(): array
public static function clearDebug(): Event

public static function getEmitter(): EventEmitter
```

Each is documented under [`Class: Event`](#class-initphpeventsevent)
below — `Events` simply exposes them statically.

### Static methods (defined on the facade itself)

```php
public static function getInstance(): Event
```

Returns the shared `Event` instance, lazily building one on first call.

```php
public static function setInstance(Event $event): void
```

Replaces the shared instance. Useful for injecting a pre-configured
dispatcher (e.g. one with simulate/debug already enabled, or a test
double).

```php
public static function reset(): void
```

Drops the shared instance so the next facade call rebuilds a fresh
one. Call this in test setUp/tearDown and at the boundary of each
request/job in long-running workers (queue workers, persistent HTTP
servers).

## Class: `InitPHP\Events\Event`

The implementation behind the [`Events`](#class-initphpeventsevents)
facade. You can instantiate it directly if you want a dispatcher you
own (e.g. one per request in a long-running worker, or a test double).
Defined in [`src/Event.php`](https://github.com/InitPHP/Events/blob/main/src/Event.php).

```php
final class Event
{
    const PRIORITY_HIGH   = 10;
    const PRIORITY_NORMAL = 100;
    const PRIORITY_LOW    = 200;
}
```

Internally, an `Event` owns a private `EventEmitter` instance and
delegates listener storage to it. The simulate/debug flags live on
`Event` itself.

### Constructor

```php
public function __construct()
```

Instantiates a fresh internal `EventEmitter`. Takes no arguments.

### `on(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): self`

Registers a regular listener. Forwards to `EventEmitter::on()`. Throws
`\InvalidArgumentException` via the underlying emitter if `$name` is
not a string, `$callback` is not callable, or `$priority` is not an
integer.

Returns `$this` (chainable).

### `once(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): self`

Registers a one-shot listener. Forwards to `EventEmitter::once()`.
Dropped from the registry after the **next** `trigger()` of `$name` —
even if the chain was stopped by a `false` return, or a listener
threw (the cleanup runs in a `try/finally` block).

Returns `$this`. Same exception contract as `on()`.

### `off(string $name, callable $callback): self`

Removes a specific listener (regular or one-shot) for `$name`.
Forwards to `EventEmitter::removeListener()`. Listener identity is
strict `array_search` — same callable instance, not a
textually-equivalent rebuild.

Returns `$this`. Throws `\InvalidArgumentException` for invalid types.

### `removeAllListeners(?string $name = null): self`

Drops every listener (regular and one-shot) for `$name`, or every
listener for every event when `$name` is `null`. Forwards to
`EventEmitter::removeAllListeners()`.

Returns `$this`. Throws `\InvalidArgumentException` if `$name` is
neither a string nor `null`.

### `trigger(string $name, ...$arguments): bool`

Walks the listener list for `$name` (in priority order) and invokes
each one with the variadic `$arguments` unpacked positionally.

- Returns `true` if all listeners ran (or none were registered).
- Returns `false` as soon as any listener returns strict `false`; the
  remaining listeners are skipped.
- Records one row to the debug log per listener invocation when
  [debug mode](Simulate-and-Debug.md#debug-mode) is on.
- Does not invoke listeners when [simulate mode](Simulate-and-Debug.md#simulate-mode)
  is on, but still records debug rows.
- One-shot listeners for `$name` are cleared at the end of the call
  regardless of whether the chain halted or a listener threw — the
  cleanup runs in a `try/finally` block.
- Throws `\InvalidArgumentException` if `$name` is not a string.

### `setSimulate(bool $simulate = false): self` / `getSimulate(): bool`

Toggle and read the simulate flag. Throws `\InvalidArgumentException`
if a non-boolean is passed. See
[Simulate & Debug Mode](Simulate-and-Debug.md#simulate-mode).

### `setDebugMode(bool $debugMode = false): self` / `getDebugMode(): bool`

Toggle and read the debug flag. Throws `\InvalidArgumentException` if
a non-boolean is passed. See
[Simulate & Debug Mode](Simulate-and-Debug.md#debug-mode).

### `getDebug(): array`

Returns the in-memory debug log — a list of rows of the form
`['start' => float, 'end' => float, 'event' => string]`. One row per
listener invocation while debug mode was on. Returns a copy; mutating
it does not affect the underlying log.

### `clearDebug(): self`

Empties the debug log without disabling debug mode. Returns `$this`.

### `getEmitter(): EventEmitter`

Returns the underlying `EventEmitter` instance — useful when callers
need the low-level `emit()` (no short-circuit on `false`) or
`clearOnceListeners()` on the same registry.

### `__debugInfo(): array`

Hook for `var_dump()`. Returns:

```php
[
    'simulate'  => bool,
    'debugMode' => bool,
    'debugData' => array,
]
```

The internal `EventEmitter` and its listener registry are deliberately
not exposed in the debug payload — call `getEmitter()->listeners()`
explicitly if you want to inspect them.

## Class: `InitPHP\Events\EventEmitter`

The low-level emitter. Defined in
[`src/EventEmitter.php`](https://github.com/InitPHP/Events/blob/main/src/EventEmitter.php).
Implements [`EventEmitterInterface`](#interface-initphpeventseventemitterinterface).
Detailed narrative: [`EventEmitter`](EventEmitter.md).

```php
class EventEmitter implements EventEmitterInterface
{
    // see methods below
}
```

### `on(string $event, callable $listener, int $priority = 100): self`

Registers a normal listener. Returns `$this` (chainable). Throws
`\InvalidArgumentException` on type mismatch.

### `once(string $event, callable $listener, int $priority = 100): self`

Registers a one-shot listener. After the first `emit($event)`, all
`once` listeners for that event are removed as a group. Returns
`$this`. Same validation as `on()`.

### `emit(string $event, array $arguments = []): void`

Invokes every listener (both `on` and `once`) registered for `$event`,
in **ascending priority order** (FIFO within a priority bucket).
Arguments are unpacked positionally by `call_user_func_array`. Throws
`\InvalidArgumentException` if `$event` is not a string or
`$arguments` is not an array.

One-shot listeners for `$event` are removed just before the listeners are
invoked, so they are gone even if a listener throws. Dispatchers built on top
of `listeners()` that run the listeners themselves (as `Event::trigger()`
does) should call `clearOnceListeners($event)` in a `try/finally` block to
keep the same guarantee.

Returns `void`. Does **not** short-circuit on listeners returning
`false` — use the [`Events` facade](Events-Facade.md) if you need that
contract.

### `removeListener(string $event, callable $listener): void`

Removes a specific listener from both the `on` and `once` queues for
`$event`. Matching uses strict equality (`===`): two distinct closure
objects with identical bodies are not equal (this includes closures built
with the first-class callable syntax), while function-name strings and
`[$object, 'method']` pairs match by value. One occurrence is removed per
priority slot. Throws on type mismatch. No-op if the listener is not found.

### `removeAllListeners(?string $event = null): void`

With an event name, clears every listener (both queues) for that
event. With `null`, clears the entire registry — every event, every
listener. Throws `\InvalidArgumentException` if `$event` is provided
but is not a string.

### `clearOnceListeners(?string $event = null): void`

Drops one-shot listeners **without invoking them**. With an event
name, clears only that event's once-listeners; with `null`, clears
every one-shot across every event. Regular `on()` listeners are not
touched.

Mostly used internally by higher-level dispatchers (the
[`Events`](Events-Facade.md) facade's `trigger()` uses it in a
`try/finally` block to honour the once-contract on short-circuit or
exception). Throws `\InvalidArgumentException` if `$event` is provided
but is not a string.

### `listeners(?string $event = null): array`

Returns the current listener snapshot, **sorted by priority** (lower
numeric value first; FIFO within a priority bucket), already merged
across both the `on` and `once` registries.

- `listeners('user.login')` — flat list of listeners for that event.
- `listeners()` — flat list of every listener across every event.

The returned array is a copy; mutating it does not affect the
registry. Iteration order matches the order `emit()` would invoke the
listeners.

## Interface: `InitPHP\Events\EventEmitterInterface`

Defined in [`src/EventEmitterInterface.php`](https://github.com/InitPHP/Events/blob/main/src/EventEmitterInterface.php).
Implemented by [`EventEmitter`](#class-initphpeventseventemitter).

```php
interface EventEmitterInterface
{
    public function on($event, $listener, $priority = 100);
    public function once($event, $listener, $priority = 100);

    public function emit($event, $arguments = []);

    public function removeListener($event, $listener);
    public function removeAllListeners($event = null);
    public function clearOnceListeners($event = null);

    public function listeners($event = null);
}
```

> The interface keeps untyped signatures (no scalar type hints, no return
> types), as in earlier releases. The concrete `EventEmitter` enforces the
> type contracts at runtime via internal guards.

Use this type-hint in code that should accept either the bundled
`EventEmitter` or your own implementation:

```php
final class OrderService
{
    public function __construct(EventEmitterInterface $bus) { /* … */ }
}
```

> `clearOnceListeners()` was added in 2.1.0. If you ship your own
> implementation of this interface, you must add this method — see
> [Migration Guide](Migration-Guide.md).

## Backwards-compatibility aliases

Defined in [`src/aliases.php`](https://github.com/InitPHP/Events/blob/main/src/aliases.php),
autoloaded via Composer's `files` directive. They map the legacy
`InitPHP\EventEmitter\*` namespace onto the new canonical classes:

```php
class_alias(
    \InitPHP\Events\EventEmitter::class,
    'InitPHP\\EventEmitter\\EventEmitter'
);
class_alias(
    \InitPHP\Events\EventEmitterInterface::class,
    'InitPHP\\EventEmitter\\EventEmitterInterface'
);
```

Both aliases are guarded by `class_exists($name, false)` /
`interface_exists($name, false)` to avoid duplicate-declaration fatals
if a stale copy of the deprecated `initphp/event-emitter` package
somehow still ships alongside.

Treat the aliases as a transition aid; see [Migration Guide](Migration-Guide.md).

## Exceptions

The library throws **only** built-in PHP exception types — there are
no package-specific exception classes.

| Thrown by | Type | When |
| --- | --- | --- |
| `Event::trigger()` / `Events::trigger()` | `\InvalidArgumentException` | `$name` is not a string. |
| `Event::on()` / `Event::once()` (and the facade equivalents) | `\InvalidArgumentException` | `$name` not a string, `$callback` not callable, or `$priority` not an integer. |
| `Event::off()` / `Events::off()` | `\InvalidArgumentException` | `$name` not a string or `$callback` not callable. |
| `Event::removeAllListeners()` / facade | `\InvalidArgumentException` | `$name` is neither a string nor `null`. |
| `Event::setSimulate()` / `setDebugMode()` (and the facade equivalents) | `\InvalidArgumentException` | The flag value is not a boolean. |
| `EventEmitter::on()` / `once()` / `removeListener()` | `\InvalidArgumentException` | `$event` not a string, `$listener` not callable, or `$priority` not an integer. |
| `EventEmitter::emit()` | `\InvalidArgumentException` | `$event` not a string, or `$arguments` not an array. |
| `EventEmitter::removeAllListeners()` / `listeners()` / `clearOnceListeners()` | `\InvalidArgumentException` | `$event` is provided but is not a string. |

Listeners themselves can throw anything; the package does not catch
listener exceptions — they propagate out of `trigger()` / `emit()` to
the caller. `Event::trigger()` runs its one-shot cleanup in a
`try/finally`, so a throwing listener still leaves the once-registry
in a clean state. `EventEmitter::emit()` removes the one-shot listeners
before invoking the listeners, so it leaves the registry clean as well.

## See also

- [Home](Home.md) — wiki entry point.
- [`Events` facade](Events-Facade.md) — narrative reference for the
  static API.
- [`EventEmitter`](EventEmitter.md) — narrative reference for the
  object-oriented API.
- [Listeners & Priorities](Listeners-and-Priorities.md) — what a listener
  can be and how ordering works.
- [Stopping Propagation](Stopping-Propagation.md) — the `return false`
  contract on `Events::trigger()`.
- [Simulate & Debug Mode](Simulate-and-Debug.md) — testing and timing.
- [Once-Listeners & Removal](Once-and-Removal.md) — the lifecycle controls
  in one place.
- [Migration Guide](Migration-Guide.md) — what changed in 2.x and what
  to do about it.
