# Quick Start

This page takes you from a fresh `composer require` to firing your first
event in under five minutes. If you have not installed yet, start at
[Installation](Installation.md).

## Choose your API

The package ships two APIs that share the same underlying engine. Pick
whichever fits how you want to structure your code.

| You want… | Use… |
| --- | --- |
| One global registry, used statically from anywhere | [`Events` facade](Events-Facade.md) |
| A plain object you can construct, inject, and pass around | [`EventEmitter`](EventEmitter.md) |
| Stop a chain mid-way by returning `false` | [`Events` facade](Events-Facade.md) |
| Simulate / debug instrumentation | [`Events` facade](Events-Facade.md) |
| Plain `emit()` with no short-circuit, no global state | [`EventEmitter`](EventEmitter.md) |

Both APIs support `once()` (one-shot listeners), `off()` /
`removeListener()` (remove a specific listener), and
`removeAllListeners()` (wipe one event or every event). Pick the API
based on the *first three rows* of the table; the lifecycle controls
are on both.

You can use both side-by-side in the same project; they do **not** share a
listener registry, which is by design.

### Three classes, three layers

Behind the two APIs sit three classes. They are layers, not alternatives:
pick the highest one that fits and you get everything below it for free.

| Class | Use when… |
| --- | --- |
| `InitPHP\Events\Events` | You want application-wide hooks and the convenience of a static facade outweighs the cost of shared global state. |
| `InitPHP\Events\Event` | You want a dispatcher you instantiate, own, and pass around — easier to scope and to test. |
| `InitPHP\Events\EventEmitter` | You want the smallest possible primitive: plain `on` / `once` / `emit`, no simulate, no debug, no short-circuit on `false`. Library authors usually want this. |

`Events` forwards every static call to one lazily-built `Event` instance, and
`Event` is built on top of `EventEmitter`.

## Three-line example — `Events` facade

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\Events;

Events::on('app.boot', function () {
    echo "App is up\n";
});

Events::trigger('app.boot');
```

```text
$ php boot.php
App is up
```

That is the entire programming model: register listeners with
`Events::on($name, $callback)`, fire them with `Events::trigger($name, ...$args)`.

## Three-line example — `EventEmitter`

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('hello', function ($name) {
    echo "Hello {$name}!\n";
});

$emitter->emit('hello', ['World']);
```

```text
$ php hello.php
Hello World!
```

Note the argument-passing convention:

- `Events::trigger($name, ...$args)` accepts **variadic** arguments.
- `EventEmitter::emit($name, array $args)` accepts arguments as an **array**.

This difference is intentional — see [Events Facade](Events-Facade.md) and
[EventEmitter](EventEmitter.md) for the rationale.

## Owning your dispatcher with `Event`

If you would rather not lean on the static facade — for example because you
want one dispatcher per request — use `Event` directly. It has the same
surface as the facade, minus the global state:

```php
use InitPHP\Events\Event;

$dispatcher = new Event();

$dispatcher
    ->on('user.registered', function ($user): void {
        sendWelcomeEmail($user);
    })
    ->on('user.registered', function ($user): void {
        trackSignup($user);
    }, Event::PRIORITY_LOW);

$dispatcher->trigger('user.registered', $user);
```

The choice between `Events` and `Event` is about scope and testability, not
features. See [`Events` facade](Events-Facade.md#own-your-dispatcher-with-event).

## Passing arguments

Any number of arguments can flow from the trigger site to every listener:

```php
use InitPHP\Events\Events;

Events::on('user.registered', function (array $user, string $source) {
    echo "User {$user['email']} signed up via {$source}\n";
});

Events::trigger('user.registered', ['email' => 'jane@example.com'], 'web');
```

```text
User jane@example.com signed up via web
```

The same flow via `EventEmitter`:

```php
$emitter->on('user.registered', function (array $user, string $source) { /* … */ });
$emitter->emit('user.registered', [['email' => 'jane@example.com'], 'web']);
```

## Multiple listeners on one event

You can attach as many listeners to a name as you like. By default they
share the same priority (`PRIORITY_NORMAL` = 100) and fire in
**registration order** within that bucket:

```php
use InitPHP\Events\Events;

Events::on('order.placed', function ($order) { echo "log: {$order['id']}\n"; });
Events::on('order.placed', function ($order) { echo "mail: {$order['email']}\n"; });
Events::on('order.placed', function ($order) { echo "queue: {$order['id']}\n"; });

Events::trigger('order.placed', ['id' => 42, 'email' => 'jane@x']);
```

```text
log: 42
mail: jane@x
queue: 42
```

Pass an explicit priority to override the order. **Lower numeric value
runs first**, regardless of when each listener was registered:

```php
Events::on('order.placed', fn () => print("late\n"),  Events::PRIORITY_LOW);   // 200
Events::on('order.placed', fn () => print("first\n"), Events::PRIORITY_HIGH);  // 10
Events::trigger('order.placed', ['id' => 42]);
// → first
// → late
```

Full ordering contract: [Listeners & Priorities](Listeners-and-Priorities.md).

## Stopping the chain

Return `false` from any listener and the remaining listeners for that event
are skipped. `trigger()` returns `false` in that case, otherwise `true`.

```php
Events::on('save', function () { return false; }); // veto
Events::on('save', function () { echo "never runs\n"; });

if (Events::trigger('save') === false) {
    echo "A listener vetoed the operation.\n";
}
```

Details and patterns: [Stopping Propagation](Stopping-Propagation.md).

## One-shot listeners

Both APIs expose `once()` — the listener fires at most once and is
discarded after the next dispatch (even if the chain was stopped by a
`false` return, or a listener threw).

```php
use InitPHP\Events\Events;

Events::once('payment.captured', function ($amount) {
    echo "first capture: {$amount}\n";
});

Events::trigger('payment.captured', 100);  // → "first capture: 100"
Events::trigger('payment.captured', 200);  // → (nothing — once fires once)
```

Or, on the low-level emitter:

```php
use InitPHP\Events\EventEmitter;

$emitter = new EventEmitter();
$emitter->once('payment.captured', function ($amount) { /* … */ });
$emitter->emit('payment.captured', [100]);
$emitter->emit('payment.captured', [200]);
```

## Removing listeners

`off()` (facade / `Event`) and `removeListener()` (low-level) drop a
specific callable; `removeAllListeners(?string)` wipes one event or
every event. Listener identity is strict — keep a reference to the
closure you want to remove. The full lifecycle contract is in
[Once-Listeners & Removal](Once-and-Removal.md).

```php
$audit = function (array $order) { /* … */ };

Events::on('order.placed', $audit);
Events::off('order.placed', $audit);

Events::removeAllListeners('order.placed');   // one event
Events::removeAllListeners();                 // every event
```

## What's next?

- The full surface of the [`Events` facade](Events-Facade.md) — including
  simulate and debug modes.
- The full surface of the [`EventEmitter`](EventEmitter.md) — including
  `once`, `removeListener`, and `removeAllListeners`.
- A grab-bag of real-world patterns: [Recipes](Recipes.md).
- The complete [API Reference](API-Reference.md).
