# API reference

Every public symbol in the package, by class.

## `InitPHP\Events\Events` (static facade)

Forwards every static call to a shared `Event` instance via
`__callStatic`. See [chapter 2](02-events-facade.md) for context.

### Constants

```php
const PRIORITY_LOW    = 200;
const PRIORITY_NORMAL = 100;
const PRIORITY_HIGH   = 10;
```

These mirror `Event::PRIORITY_*`. Lower numeric value runs first.

### Lifecycle

```php
public static function getInstance(): Event
```

Return the shared `Event` instance, building one on first call.

```php
public static function setInstance(Event $event): void
```

Replace the shared instance. Useful for injecting a pre-configured
dispatcher (e.g. with simulate or debug already enabled) before
the rest of your code touches the facade.

```php
public static function reset(): void
```

Drop the shared instance so the next facade call rebuilds a fresh
one. Call this in test setUp/tearDown and at the boundary of each
request / job in long-running workers.

### Forwarded methods

Every method that exists on `Event` is reachable through `Events`
via `__callStatic`. The most common ones are listed here for
discoverability, but the source of truth is `Event` (next section).

```php
public static function trigger(string $name, ...$arguments): bool
public static function on(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL): Event
public static function once(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL): Event
public static function off(string $name, callable $callback): Event
public static function removeAllListeners(?string $name = null): Event
public static function setSimulate(bool $simulate = false): Event
public static function getSimulate(): bool
public static function setDebugMode(bool $debugMode = false): Event
public static function getDebugMode(): bool
public static function getDebug(): array
public static function clearDebug(): Event
public static function getEmitter(): EventEmitter
```

---

## `InitPHP\Events\Event` (high-level dispatcher)

Built on top of `EventEmitter`. Adds priority-ordered dispatch with
"return `false` stops the chain" semantics, plus opt-in simulate
and debug modes.

### Constants

```php
const PRIORITY_LOW    = 200;
const PRIORITY_NORMAL = 100;
const PRIORITY_HIGH   = 10;
```

### Constructor

```php
public function __construct()
```

Builds an `EventEmitter` internally. No arguments.

### Listener registration

```php
public function on(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): self
```

Register a regular listener. Returns `$this` for chaining.

- **Throws** `\InvalidArgumentException` if `$name` is not a string,
  `$callback` is not callable, or `$priority` is not an integer.
- The underlying `EventEmitter` folds `$name` to lower-case.

```php
public function once(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): self
```

Register a one-shot listener — dropped after the next `trigger()`
of `$name`. Same exception contract as `on()`.

```php
public function off(string $name, callable $callback): self
```

Remove a specific listener (regular or one-shot) for `$name`.
Forwards to `EventEmitter::removeListener()`. Listener identity is
strict `array_search` — same callable instance, not a
textually-equivalent rebuild.

```php
public function removeAllListeners(?string $name = null): self
```

Drop every listener for `$name`, or every listener for every event
when `$name` is null. Throws `\InvalidArgumentException` if `$name`
is neither a string nor null.

### Dispatch

```php
public function trigger(string $name, ...$arguments): bool
```

Dispatch `$name` — invoke every registered listener in ascending
priority order, forwarding `...$arguments` to each via
`call_user_func_array`.

Returns:

- `true` if every listener ran to completion without returning
  `false`,
- `false` if any listener returned boolean `false` (the chain is
  halted at that point — subsequent listeners are not invoked).

One-shot listeners are dropped after the dispatch, regardless of
whether the chain was halted or a listener threw. The cleanup is in
a `try/finally` block.

Throws `\InvalidArgumentException` if `$name` is not a string.

### Simulate mode

```php
public function setSimulate(bool $simulate = false): self
public function getSimulate(): bool
```

When `true`, `trigger()` walks the priority queue but does not
invoke the listeners — and always returns `true`. Setter throws
`\InvalidArgumentException` if `$simulate` is not a boolean.

### Debug mode

```php
public function setDebugMode(bool $debugMode = false): self
public function getDebugMode(): bool
public function getDebug(): array
public function clearDebug(): self
```

When `setDebugMode(true)`, every listener invocation appends an
entry to an internal log:

```php
['start' => float, 'end' => float, 'event' => string]
```

`start` is captured via `microtime(true)` *before* the listener
runs; `end` is captured *after* it returns (or throws — though the
throw propagates).

`getDebug()` returns the log as a plain array.
`clearDebug()` empties the log and returns `$this`.

The log is not capped automatically — long-running workers with
debug mode on should call `clearDebug()` periodically.

### Backing emitter

```php
public function getEmitter(): EventEmitter
```

Returns the underlying `EventEmitter`. Useful when you need both
the high-level `trigger()` and the low-level `emit()` /
`clearOnceListeners()` on the same listener registry.

### Magic

```php
public function __debugInfo(): array
```

Returns `['simulate' => bool, 'debugMode' => bool, 'debugData' => array]`
— a `var_dump`-friendly snapshot. Does not include the raw listener
registry; reach into `getEmitter()->listeners()` if you need that.

---

## `InitPHP\Events\EventEmitter` (low-level primitive)

Plain `on` / `once` / `emit` / `removeListener` event emitter.
Implements `EventEmitterInterface`. No simulate, no debug, no
short-circuit on `false`.

### Public methods

```php
public function on(string $event, callable $listener, int $priority = 100): self
public function once(string $event, callable $listener, int $priority = 100): self
```

Register a listener. `on()` is permanent; `once()` is one-shot
(dropped after the next `emit()` or `clearOnceListeners()`).

Both throw `\InvalidArgumentException` for type-incorrect arguments
(non-string event, non-callable listener, non-int priority).
Both return `$this`.

```php
public function emit(string $event, array $arguments = []): void
```

Dispatch `$event`. Listeners are invoked in ascending priority
order; within a priority, FIFO by registration order. `$arguments`
is unpacked and forwarded to each listener via `call_user_func_array`.

Once-listeners for `$event` are dropped *after* the dispatch.

Throws `\InvalidArgumentException` if `$event` is not a string or
`$arguments` is not an array.

```php
public function removeListener(string $event, callable $listener): void
```

Remove every occurrence of `$listener` for `$event`, across both
the regular and one-shot registries and across every priority slot.

Throws `\InvalidArgumentException` for non-string event / non-callable
listener.

```php
public function removeAllListeners(?string $event = null): void
```

Wipe one event's listeners (both regular and one-shot), or every
listener for every event when `$event` is null.

```php
public function clearOnceListeners(?string $event = null): void
```

Drop one-shot listeners *without invoking them*. Used internally by
higher-level dispatchers (`Event::trigger()`) that run listeners
themselves and need to honour the once-contract.

```php
public function listeners(?string $event = null): array
```

Return the listeners for `$event` (or for every event, if null),
already merged across both registries and sorted by priority.
Useful for diagnostics.

### Default priority

The integer literal `100` — equivalent to `Event::PRIORITY_NORMAL`.
The named constants live on `Event`, not on `EventEmitter`.

---

## `InitPHP\Events\EventEmitterInterface`

The contract `EventEmitter` implements. If you ship your own
implementation, your class must define:

```php
public function on($event, $listener, $priority = 100);
public function once($event, $listener, $priority = 100);
public function removeListener($event, $listener);
public function removeAllListeners($event = null);
public function listeners($event = null);
public function emit($event, $arguments = []);
public function clearOnceListeners($event = null);
```

`clearOnceListeners()` was added in 2.0 — it is a BC break for
anyone shipping their own implementation.

---

## Backwards-compatibility aliases

`src/aliases.php` (autoloaded by Composer via the `files` autoload
entry) registers:

```php
class_alias('InitPHP\\Events\\EventEmitter',          'InitPHP\\EventEmitter\\EventEmitter');
class_alias('InitPHP\\Events\\EventEmitterInterface', 'InitPHP\\EventEmitter\\EventEmitterInterface');
```

So code written against the deprecated `initphp/event-emitter`
package keeps working unchanged. See
[chapter 7](07-migration-from-event-emitter.md).

---

## Exceptions

Every type-validation error in the package raises
`\InvalidArgumentException`. The package does not define its own
exception types.

| Method | Raises `\InvalidArgumentException` when |
| --- | --- |
| `Event::trigger()` | `$name` is not a string. |
| `Event::on()` / `Event::once()` | `$name` is not a string, `$callback` is not callable, or `$priority` is not an integer. |
| `Event::off()` | `$name` is not a string or `$callback` is not callable. |
| `Event::removeAllListeners()` | `$name` is neither a string nor null. |
| `Event::setSimulate()` | `$simulate` is not a boolean. |
| `Event::setDebugMode()` | `$debugMode` is not a boolean. |
| `EventEmitter::on()` / `EventEmitter::once()` | Non-string event, non-callable listener, or non-int priority. |
| `EventEmitter::emit()` | Non-string event, or `$arguments` is not an array. |
| `EventEmitter::removeListener()` | Non-string event or non-callable listener. |
| `EventEmitter::removeAllListeners()` / `clearOnceListeners()` | `$event` is neither a string nor null. |
| `EventEmitter::listeners()` | `$event` is neither a string nor null. |

The package does not throw any other exception type on its own —
exceptions propagated *from listeners* of course pass through
untouched. (`trigger()` still cleans up once-listeners on the way
out via `try/finally`.)
