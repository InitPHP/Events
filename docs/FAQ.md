# FAQ

Short answers to common questions. The longer-form material lives in
the linked reference pages.

## What's the difference between `Events` and `EventEmitter`?

`Events` is a **static facade** over a single shared `Event` instance;
`EventEmitter` is the **instantiable, dependency-injectable** sibling.

| | `Events` facade | `EventEmitter` |
| --- | --- | --- |
| Registry | one global, shared | one per instance |
| `on()` returns | the shared `Event` (chainable) | `$this` (chainable) |
| `once()` available | ✅ | ✅ |
| Stops chain on `false` return | ✅ | — |
| Arguments | varargs | array |
| Simulate / debug mode | ✅ | — |
| Removal API (`off` / `removeAllListeners`) | ✅ | ✅ |
| Reset between tests/jobs | `Events::reset()` | new instance |

See [`Events` facade](Events-Facade.md) vs [`EventEmitter`](EventEmitter.md)
for the full comparison.

## Which one should I use?

- Use **`Events`** when you want WordPress-style global hooks,
  ergonomic varargs at the call site, and the optional simulate /
  debug instrumentation.
- Use **`EventEmitter`** when you want a plain object you can build
  per-request, dependency-inject, or hand to a service — without
  reaching for global state.

`once()`, `off()`, and `removeAllListeners()` exist on both APIs, so
they are not a reason to pick one over the other.

You can use both side-by-side — they do not share state.

## Do priorities actually re-order listeners?

**Yes.** Listeners with a lower numeric priority fire first; within
the same priority bucket, registration order (FIFO) wins. The named
constants are aliases for integers:

- `PRIORITY_HIGH = 10` — runs first
- `PRIORITY_NORMAL = 100` — default
- `PRIORITY_LOW = 200` — runs last

```php
Events::on('e', fn() => print("late\n"),  Events::PRIORITY_LOW);
Events::on('e', fn() => print("first\n"), Events::PRIORITY_HIGH);
Events::trigger('e');
// → first
// → late
```

> Releases before 2.1.0 had a bug that silently ignored `$priority` and
> dispatched in registration order. That is fixed in 2.1.0 — see
> [Listeners & Priorities](Listeners-and-Priorities.md) and the
> [Migration Guide](Migration-Guide.md).

## Are event names case-sensitive?

No. Both APIs lowercase the event name before storing or looking it up,
so `'User.Registered'`, `'user.registered'`, and `'USER.REGISTERED'`
reference the same event. Choose one convention and stick with it.

## How do I pass multiple arguments?

```php
// Events facade — varargs
Events::trigger('order.placed', $order, 'web', 2);

// EventEmitter — array
$bus->emit('order.placed', [$order, 'web', 2]);
```

Listeners always receive the arguments unpacked positionally:

```php
function (array $order, string $source, int $version) { /* … */ }
```

## How do I stop the chain?

Return strict `false` from a listener attached via the
[`Events` facade](Events-Facade.md); `trigger()` will return `false` and
skip the remaining listeners. `EventEmitter::emit()` does **not**
short-circuit on `false` — see [Stopping Propagation](Stopping-Propagation.md)
for the patterns that work there.

## Can a listener mutate the event payload?

Yes, if the payload is an **object**. Objects are passed by handle in PHP, so a
mutation inside the listener is visible to the caller. An `ArrayObject` gives
array-style access:

```php
Events::on('user.before_save', function (ArrayObject $user) {
    $user['email'] = strtolower($user['email']);
});

$user = new ArrayObject(['email' => 'JANE@example.com']);
Events::trigger('user.before_save', $user);

echo $user['email']; // jane@example.com
```

A plain array **cannot** be mutated through `Events::trigger()`. The variadic
`...$arguments` are collected by value, so a listener that declares
`array &$user` receives a copy and PHP raises a warning (`Argument #1 ($user)
must be passed by reference, value given`). The low-level
`EventEmitter::emit()` can pass references if you put one in the arguments
array: `$bus->emit('e', [&$value])`.

## Can the same callable be registered twice?

Yes. It will fire twice. Re-registering does not deduplicate.
`EventEmitter::removeListener()` removes one occurrence per priority slot, so
two registrations at the same priority need two calls — see
[Once-Listeners & Removal](Once-and-Removal.md#off--remove-a-specific-listener).

## Does this package implement a PSR?

No. The codebase predates and does not target
[PSR-14 (Event Dispatcher)](https://www.php-fig.org/psr/psr-14/). If you
need PSR-14 specifically, build a thin adapter over `EventEmitter` (a
listener provider + dispatcher pair is ~30 lines).

## Is it thread-safe?

PHP's standard model has no shared-memory threads in user code, so
"thread-safe" is not meaningful here. The library is not safe to use
from multiple parallel coroutines that share an `Event`/`EventEmitter`
instance unless your runtime serialises access.

## What PHP versions are supported?

Version 3.0.0 and later require **PHP 8.2+**, and CI runs the full PHPUnit
suite on PHP 8.2, 8.3, 8.4 and 8.5. Releases up to and including 2.1.0 declared
`php >=5.6`; if you are on an older PHP, lock to `^2.1` (see
[Installation](Installation.md#composer-constraint-examples)).

## Is this still maintained?

Yes — `initphp/events` is the active package. The split
[`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter)
repository is **deprecated**; its contents now ship inside this
package. See [Migration Guide](Migration-Guide.md).

## Where do I report a bug?

[github.com/InitPHP/Events/issues](https://github.com/InitPHP/Events/issues).
Include a minimal reproduction, your PHP version, and the package
version (`composer show initphp/events`).

## Where do I ask a usage question?

Use [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions).
Bug reports go in Issues; design questions, "how do I do X", and
broader conversation go in Discussions.

## Is there a way to list all registered events?

Yes. On the low-level emitter directly, or on the facade via
`Events::getEmitter()`:

```php
$bus->listeners();                                 // every listener across every event
$bus->listeners('user.login');                     // just that event

Events::getEmitter()->listeners('user.login');     // same, via the facade
```

The returned array is already merged across both the regular and
one-shot registries and sorted in dispatch order (priority + FIFO).

## See also

- [Home](Home.md)
- [`Events` facade](Events-Facade.md)
- [`EventEmitter`](EventEmitter.md)
- [Recipes](Recipes.md)
- [Troubleshooting](Troubleshooting.md)
