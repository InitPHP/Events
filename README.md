# InitPHP Events

A small, dependency-free event / hook library for PHP. Ships both a
high-level dispatcher with WordPress-style `do_action`-like semantics
(stop the chain when a listener returns `false`, simulate mode, debug
log) and a plain low-level `EventEmitter` you can instantiate and pass
around like any other object.

[![CI](https://github.com/InitPHP/Events/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/Events/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/initphp/events/v)](https://packagist.org/packages/initphp/events)
[![Total Downloads](https://poser.pugx.org/initphp/events/downloads)](https://packagist.org/packages/initphp/events)
[![License](https://poser.pugx.org/initphp/events/license)](https://packagist.org/packages/initphp/events)
[![PHP Version Require](https://poser.pugx.org/initphp/events/require/php)](https://packagist.org/packages/initphp/events)

> **Heads-up — v2.0 fixes a long-standing priority-ordering bug.** In
> 1.x, listeners ran in the order they were registered, *not* in the
> order their priorities asked for. v2.0 makes priority honour its
> name. If your 1.x code happened to register listeners in ascending
> priority order, the visible behaviour does not change; if it didn't,
> please re-read [Priorities and ordering](#priorities-and-ordering)
> and the [v2.0 changelog](./CHANGELOG.md).

## At a glance

- **`Events`** — a static facade backed by a lazily-built singleton.
  Convenient for application-wide hooks where threading a dispatcher
  through every layer is overkill.
- **`Event`** — the high-level dispatcher itself. Adds priority-ordered
  dispatch with a "return `false` stops the chain" contract, plus
  optional *simulate* and *debug* modes. Use this directly when you
  want a dispatcher you own.
- **`EventEmitter`** — the low-level primitive. Plain `on` / `once` /
  `emit` / `removeListener`. No simulate, no debug, no short-circuit.
  Use this when you need a dispatcher with the smallest possible
  surface — or when you are migrating from the deprecated
  [`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter)
  package (the legacy `\InitPHP\EventEmitter\*` class names still work
  via a BC alias).

Everything is in a single PSR-4 namespace (`InitPHP\Events\`) and has
zero runtime dependencies.

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | `>= 5.6` (runtime); PHP `>= 7.3` to run the test suite (PHPUnit 9.6) |
| Extensions | none |
| Composer dependencies | none |

## Installation

```bash
composer require initphp/events
```

If you are coming from `initphp/event-emitter`, replace your dependency
— `initphp/events` declares a Composer `replace` for it and ships a
class alias so the old fully-qualified names still resolve. See
[Migration](#migrating-from-initphpevent-emitter) for details.

## Quick start

```php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\Events;

Events::on('helloTrigger', function (): void {
    echo 'Hello World' . PHP_EOL;
}, 100);

Events::on('helloTrigger', function (): void {
    echo 'Hi, World' . PHP_EOL;
}, 99);

Events::trigger('helloTrigger');
```

Output:

```
Hi, World
Hello World
```

The listener registered with priority **99** runs before the one with
priority **100** — lower numeric value means *runs first*. The three
named constants `Events::PRIORITY_HIGH` (10), `Events::PRIORITY_NORMAL`
(100), and `Events::PRIORITY_LOW` (200) exist for readability.

### Passing arguments to listeners

```php
Events::on('greet', function (string $name, string $me): void {
    echo "Hi {$name}. I am {$me}." . PHP_EOL;
}, 99);

Events::trigger('greet', 'World', 'John');
// Hi World. I am John.
```

### Stopping the chain

A listener that returns boolean `false` halts the chain — subsequent
listeners are not invoked and `trigger()` itself returns `false`. This
is the same convention as WordPress's `apply_filters` short-circuit:

```php
Events::on('save', function ($payload) {
    if (!isValid($payload)) {
        return false;          // stops the chain
    }
}, 10);

Events::on('save', function ($payload): void {
    persist($payload);         // never runs if validation returned false
}, 20);

if (!Events::trigger('save', $payload)) {
    // at least one listener vetoed the operation
}
```

## Working with `Event` directly

If you prefer a dispatcher you instantiate and pass around (easier to
test, easier to scope, no global state), use `Event`:

```php
use InitPHP\Events\Event;

$dispatcher = new Event();

$dispatcher
    ->on('user.registered', function ($user): void {
        sendWelcomeEmail($user);
    })
    ->on('user.registered', function ($user): void {
        trackSignup($user);
    }, Event::PRIORITY_LOW);    // run last

$dispatcher->trigger('user.registered', $user);
```

`Event` and `Events` expose the same surface — `Events::on(...)` simply
forwards to a shared `Event` instance.

## Working with the low-level `EventEmitter`

For libraries that want the smallest possible surface, the underlying
emitter is also public:

```php
use InitPHP\Events\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('hello', function (string $name): void {
    echo "Hello {$name}!" . PHP_EOL;
}, 99);

$emitter->once('hello', function (string $name): void {
    echo "Hi {$name}, this only fires once." . PHP_EOL;
}, 10);

$emitter->emit('hello', ['World']);
$emitter->emit('hello', ['World']);
```

Differences from `Event`:

- `emit()` returns `void` (no short-circuit on `false`).
- Listener arguments come in as a single array, not as varargs.
- No simulate or debug mode.
- No singleton.

`EventEmitter` is what `Event` is built on. The same instance is
reachable via `$dispatcher->getEmitter()` if you ever need both
high-level dispatch and low-level emit on the same listener registry.

## Priorities and ordering

```
PRIORITY_HIGH   = 10    ← runs first
PRIORITY_NORMAL = 100
PRIORITY_LOW    = 200   ← runs last
```

Rules:

1. **Lower numeric priority runs first.** The names are about
   *importance* (high-priority work runs early); the numbers are about
   *position*.
2. Within the same priority, listeners run in **registration order
   (FIFO)**.
3. Priority is global per-event — once-listeners and regular listeners
   are merged into a single priority-sorted queue when an event fires.
4. Event names are matched **case-insensitively**: `on('User.Created')`
   and `emit('user.created')` see the same listener.

See [docs/04-priorities-and-ordering.md](docs/04-priorities-and-ordering.md)
for the full ordering contract, including how `once()` interacts with
the priority queue.

## One-shot listeners, removal, and cleanup

```php
$dispatcher->once('boot', $listener);          // fires at most once
$dispatcher->off('boot', $listener);           // removes a specific listener
$dispatcher->removeAllListeners('boot');       // wipes one event
$dispatcher->removeAllListeners();             // wipes every event
```

The `once()` contract is honoured even when the chain is stopped by a
`false` return — a one-shot listener that runs (or that *would have*
run, but for a short-circuit earlier in the queue) is dropped after
the trigger completes. See
[docs/05-once-and-removal.md](docs/05-once-and-removal.md).

## Simulate mode and debug log

`Event` (and therefore `Events`) carry two opt-in instrumentation
modes:

```php
$dispatcher = new InitPHP\Events\Event();
$dispatcher->setSimulate(true);   // listeners are not invoked; trigger() still returns true
$dispatcher->setDebugMode(true);  // each trigger() appends {start, end, event} to the log

$dispatcher->trigger('checkout.complete', $order);

$dispatcher->getDebug();          // [['start' => ..., 'end' => ..., 'event' => 'checkout.complete']]
$dispatcher->clearDebug();        // empty the log
```

See [docs/06-debug-and-simulate.md](docs/06-debug-and-simulate.md).

## Public API

| Class | Purpose |
| --- | --- |
| `InitPHP\Events\Events` | Static facade; thin shim over a shared `Event` instance. |
| `InitPHP\Events\Event` | High-level dispatcher (priority, short-circuit, simulate, debug). |
| `InitPHP\Events\EventEmitter` | Low-level primitive (`on` / `once` / `emit` / `removeListener` / `clearOnceListeners`). |
| `InitPHP\Events\EventEmitterInterface` | Contract that `EventEmitter` implements. |

| Method | Class | Purpose |
| --- | --- | --- |
| `trigger(string $name, ...$arguments): bool` | `Event` · `Events` | Dispatch with priority + short-circuit semantics. |
| `on(string, callable, int $priority = PRIORITY_NORMAL): self` | `Event` · `Events` · `EventEmitter` | Register a listener. |
| `once(string, callable, int $priority = PRIORITY_NORMAL): self` | `Event` · `Events` · `EventEmitter` | Register a one-shot listener. |
| `off(string, callable): self` | `Event` · `Events` | Remove a specific listener (alias for `removeListener`). |
| `removeListener(string, callable): void` | `EventEmitter` | Remove a specific listener. |
| `removeAllListeners(?string = null): self/void` | `Event` · `Events` · `EventEmitter` | Wipe one event, or every event. |
| `emit(string, array $args = []): void` | `EventEmitter` | Low-level dispatch (no short-circuit). |
| `clearOnceListeners(?string = null): void` | `EventEmitter` | Drop one-shot listeners without invoking them. |
| `listeners(?string = null): array` | `EventEmitter` | Inspect the current listener registry. |
| `setSimulate(bool): self` / `getSimulate(): bool` | `Event` · `Events` | Toggle / inspect simulate mode. |
| `setDebugMode(bool): self` / `getDebugMode(): bool` | `Event` · `Events` | Toggle / inspect debug mode. |
| `getDebug(): array` / `clearDebug(): self` | `Event` · `Events` | Read / clear the debug log. |
| `getEmitter(): EventEmitter` | `Event` · `Events` | Access the backing emitter. |
| `getInstance(): Event` | `Events` | Return the shared dispatcher. |
| `setInstance(Event): void` | `Events` | Inject a pre-configured dispatcher. |
| `reset(): void` | `Events` | Drop the shared instance (tests, lifecycle resets). |

Full signatures and exception behaviour: [docs/09-api-reference.md](docs/09-api-reference.md).

## Migrating from `initphp/event-emitter`

The standalone `initphp/event-emitter` package has been merged into
this one and is now deprecated. If your code currently uses
`\InitPHP\EventEmitter\EventEmitter`, **no source changes are
required** — this package ships a backwards-compatibility alias:

```diff
- "initphp/event-emitter": "^1.0",
+ "initphp/events": "^2.0"
```

Composer will not install both packages side-by-side because
`initphp/events:^2.0` declares a `replace` for `initphp/event-emitter`.

When you next touch the code, prefer the new canonical namespace:

```php
// Before
use InitPHP\EventEmitter\EventEmitter;

// After
use InitPHP\Events\EventEmitter;
```

The alias is intended as a transition aid and may be removed in a
future major release. **One important behaviour change** that comes
with v2.0: in 1.x, `EventEmitter::emit()` had a bug where the entire
listeners array was passed to `call_user_func_array` instead of each
individual listener, so emitted events silently fired no listeners at
all. That is fixed in 2.0 — if you had quietly broken `emit()` calls
in 1.x, they will now actually run their listeners.

See [docs/07-migration-from-event-emitter.md](docs/07-migration-from-event-emitter.md)
for the full migration checklist.

## Documentation

The full guide lives under [`docs/`](./docs/README.md):

1. [Getting started](docs/01-getting-started.md)
2. [The `Events` facade](docs/02-events-facade.md)
3. [Using `EventEmitter` directly](docs/03-event-emitter.md)
4. [Priorities and ordering](docs/04-priorities-and-ordering.md)
5. [Once-listeners, removal, and cleanup](docs/05-once-and-removal.md)
6. [Debug and simulate modes](docs/06-debug-and-simulate.md)
7. [Migrating from `initphp/event-emitter`](docs/07-migration-from-event-emitter.md)
8. [Recipes (plugin systems, request lifecycle, WordPress-style hooks)](docs/08-recipes.md)
9. [API reference](docs/09-api-reference.md)

## Development

```bash
composer install
composer test         # PHPUnit
```

CI runs the test suite across PHP 7.3 – 8.4 and also lints the source
on PHP 5.6 / 7.0 / 7.1 / 7.2 so the `php >= 5.6` library contract stays
honest.

## Contributing & Security

- [Contributing guidelines](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md)
- [Code of Conduct](https://github.com/InitPHP/.github/blob/main/CODE_OF_CONDUCT.md)
- [Security policy](https://github.com/InitPHP/.github/blob/main/SECURITY.md)

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Released under the [MIT License](./LICENSE).
