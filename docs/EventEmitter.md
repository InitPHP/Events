# `EventEmitter`

`InitPHP\Events\EventEmitter` is the low-level, instantiable side of the
package. It implements
[`EventEmitterInterface`](API-Reference.md#interface-initphpeventseventemitterinterface) and
exposes a Node.js-style API: `on`, `once`, `emit`, `removeListener`,
`removeAllListeners`, `listeners`, `clearOnceListeners`.

```php
use InitPHP\Events\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('hello', function ($name) {
    echo "Hello {$name}!\n";
});

$emitter->emit('hello', ['World']);  // → Hello World!
```

## When to use this API

Reach for `EventEmitter` when:

- You want a **plain object** you can inject and pass around.
- You need **`once()`** — one-shot listeners that auto-detach after firing.
- You need to **remove a specific listener** (`removeListener`) or **flush
  all listeners** (`removeAllListeners`).
- You want **fluent chaining** — `on()`/`once()` return `$this`.

If you would rather call the same registry statically from anywhere, use
the [`Events` facade](Events-Facade.md).

## The full surface

> Types in the signatures below document the contract. The source keeps the
> untyped declarations of earlier releases (for example
> `on($event, $listener, $priority = 100)`) and enforces the types at runtime
> by throwing `\InvalidArgumentException`.

```php
namespace InitPHP\Events;

class EventEmitter implements EventEmitterInterface
{
    public function on(string $event, callable $listener, int $priority = 100): self;
    public function once(string $event, callable $listener, int $priority = 100): self;

    public function emit(string $event, array $arguments = []): void;

    public function removeListener(string $event, callable $listener): void;
    public function removeAllListeners(?string $event = null): void;
    public function clearOnceListeners(?string $event = null): void;

    public function listeners(?string $event = null): array;
}
```

## `on(string $event, callable $listener, int $priority = 100): $this`

Registers a listener for `$event`. Fluent — returns `$this` so you can
chain calls.

```php
$emitter
    ->on('order.placed', $auditLog)
    ->on('order.placed', $sendEmail)
    ->on('order.placed', $awardPoints);
```

- `$event` is **case-insensitive** (lowercased internally).
- `$listener` can be any PHP callable.
- `$priority` is an integer. Lower values run first. The named
  constants live on [`Event`](API-Reference.md#class-initphpeventsevent)
  (`PRIORITY_HIGH = 10`, `PRIORITY_NORMAL = 100`, `PRIORITY_LOW = 200`)
  but you can pass the integers directly. See
  [Listeners & Priorities](Listeners-and-Priorities.md) for the full
  ordering contract.

Throws `\InvalidArgumentException` if `$event` is not a string,
`$listener` is not callable, or `$priority` is not an integer.

## `once(string $event, callable $listener, int $priority = 100): $this`

Registers a **one-shot** listener: it fires the next time the event is
emitted, then is automatically detached.

```php
$emitter->once('payment.captured', function ($amount) {
    echo "first capture: {$amount}\n";
});

$emitter->emit('payment.captured', [100]); // → first capture: 100
$emitter->emit('payment.captured', [200]); // → (silence — once fires once)
```

Same validation as `on()`. The listener is queued alongside regular
listeners and is fired in the same iteration as them.

> **Detachment semantic.** All `once` listeners for an event name are
> cleared **as a group** on the first `emit($name)`, regardless of how
> many you registered. If you `once` two listeners for the same event,
> both fire on the first emit, then both are gone. `emit()` removes them
> *before* it invokes the listeners, so they are gone even if a listener
> throws (the exception still propagates out of `emit`).

## `emit(string $event, array $arguments = []): void`

Invokes every listener registered for `$event` (both `on` and `once`),
in ascending priority order, passing `$arguments` straight through.

```php
$emitter->on('greet', function ($name, $greeting) {
    echo "{$greeting}, {$name}!\n";
});

$emitter->emit('greet', ['World', 'Hello']);
// → Hello, World!
```

- **Arguments are passed as an array**, not as varargs. The array is
  unpacked positionally by `call_user_func_array`, so the listener
  signature simply lists the arguments in order.
- **Listeners fire in priority order.** Lower numeric priority runs
  first; within the same priority, registration order (FIFO). See
  [Listeners & Priorities](Listeners-and-Priorities.md).
- **Return value is `void`.** Unlike [`Events::trigger()`](Events-Facade.md),
  `EventEmitter::emit()` ignores whatever listeners return — there is no
  short-circuit on `false`.
- **One-shot listeners are dropped as dispatch starts.** `emit()` collects
  the listeners, removes the event's one-shot listeners, and only then
  invokes them. If you need to drop one-shots without firing them at all,
  call [`clearOnceListeners()`](#clearoncelistenersstring-event--null-void).
- If `$event` is not a string or `$arguments` is not an array,
  `\InvalidArgumentException` is thrown.

> **Heads-up for upgraders.** Tag `1.0` of the standalone
> `initphp/event-emitter` package had a bug in `emit()` that silently
> ran *no* listeners; `1.0.1` already fixed it, and so does every
> `initphp/events` release. If you are upgrading from `1.0` and had `emit()`
> calls that quietly never invoked anything, they will now actually fire —
> see [Migration Guide](Migration-Guide.md).

## `removeListener(string $event, callable $listener): void`

Detaches the **exact** listener instance previously registered for that
event (across both `on` and `once` queues). Strict comparison (`===`) is
used, so you must hand back the same callable you registered. Closures
are compared by instance; `'function_name'` strings and
`[$object, 'method']` / `[ClassName::class, 'method']` pairs are compared
by value, so rebuilding those at the call site still matches.

```php
$auditLog = function ($order) { /* … */ };

$emitter->on('order.placed', $auditLog);

// …later…
$emitter->removeListener('order.placed', $auditLog);
```

If the listener is not found, the call is a no-op. Throws on invalid
argument types.

> **Closures are identity-compared.** Two anonymous functions with the
> same body are *not* equal — you must keep a reference to the closure
> you registered if you ever intend to remove it. The same goes for the
> first-class callable syntax (`$obj->method(...)`), which builds a new
> `Closure` every time. Plain function names (`'my_func'`) and
> `[$obj, 'method']` pairs are easier to pass around. See
> [Once-Listeners & Removal](Once-and-Removal.md) for the full removal contract.

## `removeAllListeners(?string $event = null): void`

With an event name, removes every listener (both `on` and `once`) for
that one event. With `null`, **wipes the entire registry**.

```php
$emitter->removeAllListeners('order.placed');  // only this event
$emitter->removeAllListeners();                // everything
```

Useful between test cases, or when you tear down a subsystem and want to
ensure no stale handlers survive.

## `clearOnceListeners(?string $event = null): void`

Drops one-shot listeners **without invoking them**. With an event name,
clears only that event's once-listeners; with `null`, clears every
one-shot listener across every event. Regular `on()` listeners are
never touched.

```php
$emitter->once('boot', $listener);
$emitter->clearOnceListeners('boot');     // dropped without firing
```

This is mostly used internally by higher-level dispatchers (the
[`Events`](Events-Facade.md) facade's `trigger()` uses it in a `try/finally`
block to honour the once-contract even when the chain is halted by a
`false` return). Application code rarely needs it directly.

Throws `\InvalidArgumentException` if `$event` is provided but is not
a string.

## `listeners(?string $event = null): array`

Returns the listeners currently registered for `$event`, already merged
across both the regular and one-shot registries and **sorted by
priority** (lower numeric value first; FIFO within a priority bucket).

- `listeners('user.registered')` → listeners for that one event.
- `listeners()` (no argument) → listeners for **every** registered
  event, concatenated into one array.

```php
foreach ($emitter->listeners('user.registered') as $callable) {
    // each $callable is exactly the value you registered;
    // iteration order matches the order emit() would invoke them.
}
```

The returned array is a snapshot; mutating it does not affect the
registry.

## Argument passing — quick reference

```php
// EventEmitter — arguments as an array
$emitter->emit('user.registered', [$user, 'web']);

// Events facade — arguments as varargs
Events::trigger('user.registered', $user, 'web');
```

Listeners always receive the **unpacked positional arguments**, so the
listener signature is identical in either case:

```php
function (array $user, string $source) { /* … */ }
```

## A complete example

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\EventEmitter;

$bus = new EventEmitter();

$logger = function (array $order) {
    echo "log: order {$order['id']}\n";
};

$bus
    ->on('order.placed', $logger)
    ->on('order.placed', function (array $order) {
        echo "mail: {$order['email']}\n";
    })
    ->once('order.placed', function (array $order) {
        echo "first-ever order welcomed: {$order['email']}\n";
    });

$bus->emit('order.placed', [['id' => 1, 'email' => 'a@x']]);
$bus->emit('order.placed', [['id' => 2, 'email' => 'b@x']]);

// Drop the audit logger for the third call.
$bus->removeListener('order.placed', $logger);

$bus->emit('order.placed', [['id' => 3, 'email' => 'c@x']]);
```

Output:

```text
log: order 1
mail: a@x
first-ever order welcomed: a@x
log: order 2
mail: b@x
mail: c@x
```

## See also

- [`Events` facade](Events-Facade.md) — the static, app-wide flavour of the
  same engine.
- [Listeners & Priorities](Listeners-and-Priorities.md) — what counts as a
  listener and how ordering really works.
- [Migration Guide](Migration-Guide.md) — coming from
  `initphp/event-emitter`.
- [API Reference](API-Reference.md) — the full method matrix.
