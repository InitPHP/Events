# Using `EventEmitter` directly

`InitPHP\Events\EventEmitter` is the low-level primitive on top of
which `Event` (and therefore `Events`) is built. It implements
`InitPHP\Events\EventEmitterInterface` and provides the smallest
possible event-dispatcher surface: `on`, `once`, `emit`,
`removeListener`, `removeAllListeners`, `listeners`,
`clearOnceListeners`.

Reach for `EventEmitter` when:

- You are writing a library and do not want to make your consumers
  depend on the higher-level `Event` (with its simulate / debug
  state).
- You do not need the "return `false` stops the chain" contract.
- You want a plain object you can pass around (no static facade, no
  shared singleton).

If any of those bullet points don't fit, you probably want `Event`
(see [chapter 2](02-events-facade.md)).

## Public surface

```php
namespace InitPHP\Events;

interface EventEmitterInterface
{
    public function on($event, $listener, $priority = 100);
    public function once($event, $listener, $priority = 100);
    public function removeListener($event, $listener);
    public function removeAllListeners($event = null);
    public function listeners($event = null);
    public function emit($event, $arguments = []);
    public function clearOnceListeners($event = null);
}
```

| Method | Returns | Behaviour |
| --- | --- | --- |
| `on($event, $listener, $priority = 100)` | `$this` | Register a regular listener. Repeated `on()` calls for the same listener register it multiple times. |
| `once($event, $listener, $priority = 100)` | `$this` | Register a one-shot listener. Dropped after the next `emit()` (or `clearOnceListeners()`). |
| `emit($event, array $arguments = [])` | `void` | Invoke every registered listener for `$event`, in ascending priority order. `$arguments` are unpacked to each listener via `call_user_func_array`. Once-listeners are dropped after the call. **Return values are ignored.** |
| `removeListener($event, $listener)` | `void` | Remove every occurrence of `$listener` for `$event` (across both the regular and one-shot registries, and across all priorities for which it was registered). |
| `removeAllListeners(?string $event = null)` | `void` | Wipe a single event's listeners, or every listener for every event when `$event` is `null`. |
| `listeners(?string $event = null)` | `array` | Return the listeners for `$event` (or for every event, if `$event` is null), already merged across regular + once registries and sorted by priority. |
| `clearOnceListeners(?string $event = null)` | `void` | Drop one-shot listeners *without invoking them*. Useful for higher-level dispatchers that run listeners themselves but still need to honour the once-contract. |

All methods throw `\InvalidArgumentException` for type-incorrect
arguments (non-string event names, non-callable listeners, non-int
priorities, non-array `emit()` arguments).

## Worked example

```php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\EventEmitter;

$bus = new EventEmitter();

$bus->on('user.created', function (array $user): void {
    audit_log('user created: ' . $user['id']);
}, EventEmitter::PRIORITY_HIGH ?? 10);   // PRIORITY_HIGH lives on Event, not EventEmitter; literal works too.

$bus->once('user.created', function (array $user): void {
    send_welcome_email($user);
}, 100);

$bus->emit('user.created', [['id' => 42, 'email' => 'x@example.com']]);
$bus->emit('user.created', [['id' => 43, 'email' => 'y@example.com']]);
// audit_log fires both times; send_welcome_email fires only for id=42.
```

> The named priority constants (`PRIORITY_HIGH`, `PRIORITY_NORMAL`,
> `PRIORITY_LOW`) live on `Event`, not on `EventEmitter`. The default
> priority on the emitter's `on()` / `once()` is the integer `100`
> (= `PRIORITY_NORMAL`). If you want the names at the low level, use
> `Event::PRIORITY_HIGH` and friends — the integer values match.

## Argument shape: `emit` vs `trigger`

This is the most common pitfall when moving between the two layers:

```php
// EventEmitter: arguments are an *array*, unpacked to each listener.
$bus->emit('e', ['a', 'b']);
// → each listener called as $listener('a', 'b')

// Event / Events: arguments are *variadic*.
$dispatcher->trigger('e', 'a', 'b');
// → each listener called as $listener('a', 'b')
```

Internally `Event::trigger()` builds an arguments array from its
varargs and forwards them to each listener — the listener call shape
is identical, only the dispatcher-side signature differs.

## What `EventEmitter` does *not* do

- **No short-circuit on `false`.** `emit()` is `void`; listener return
  values are discarded. If you want short-circuit semantics, use
  `Event::trigger()` (or call `listeners()` yourself and iterate).
- **No simulate or debug mode.** Those live one layer up.
- **No singleton.** Construct as many emitters as you need.

## Event names are case-insensitive

Event names are lower-cased before being stored or looked up:

```php
$bus->on('User.Created', $listener);
$bus->emit('user.created', [...]);   // listener fires
$bus->emit('USER.CREATED', [...]);   // listener fires
```

This is enforced by `strtolower()` in every method that accepts an
event name. Keep this in mind if you are namespacing events with a
case-sensitive convention — pick lower-case (or some other folded
form) up front to avoid surprises.

## Inspecting the registry

`listeners()` returns the listeners as a plain `array<int, callable>`,
already merged across both the regular and once registries and sorted
by priority. Useful for diagnostics:

```php
foreach ($bus->listeners('user.created') as $listener) {
    var_dump($listener);
}
```

`listeners()` with no argument returns *every* listener across *every*
event, which is mostly useful for "is anything listening at all?"
checks.

## Removing listeners

```php
$cb = function (): void { /* ... */ };

$bus->on('e', $cb);
$bus->removeListener('e', $cb);    // drops just this listener
$bus->removeAllListeners('e');     // drops every listener for 'e'
$bus->removeAllListeners();        // drops every listener for every event
```

Two subtleties to know:

1. **Listener identity uses strict `array_search`** under the hood —
   `Closure`s must be the *same instance*. A textually identical
   `function () { ... }` you constructed twice will not match.
2. **`removeListener` removes from every priority slot** in which the
   listener appears. If you registered the same listener at multiple
   priorities, one call removes all of them. This is rarely the
   shape callers want; if it matters for your use case, register the
   listener only once.

See [chapter 5](05-once-and-removal.md) for the full removal contract.

## Next

- [Chapter 4 — Priorities and ordering](04-priorities-and-ordering.md)
- [Chapter 5 — Once-listeners, removal, and cleanup](05-once-and-removal.md)
- [Chapter 7 — Migrating from `initphp/event-emitter`](07-migration-from-event-emitter.md)
