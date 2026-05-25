# Getting started

## Install

```bash
composer require initphp/events
```

The package has zero runtime dependencies and supports PHP `>= 5.6`.

If you are migrating from
[`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter),
just swap the dependency — `initphp/events` declares a Composer
`replace` for it and provides a BC alias for the legacy
`\InitPHP\EventEmitter\*` class names. See
[chapter 7](07-migration-from-event-emitter.md).

## The three classes you will touch

| Class | Use when… |
| --- | --- |
| `InitPHP\Events\Events` | You want application-wide hooks and the convenience of a static facade outweighs the cost of shared global state. |
| `InitPHP\Events\Event` | You want a dispatcher you instantiate, own, and pass around. Easier to scope and to test. |
| `InitPHP\Events\EventEmitter` | You want the smallest possible primitive — plain `on` / `once` / `emit`, no simulate, no debug, no short-circuit on `false`. Library authors usually want this. |

`Events` is a thin shim that forwards every static call to a single
lazily-built `Event` instance. `Event`, in turn, is built on top of
`EventEmitter`. So the three classes are layers, not alternatives —
pick the highest one that fits and you get everything below it for
free.

## Smallest possible example

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

Two things to notice here:

1. **Lower numeric priority runs first.** The listener registered with
   priority `99` runs before the one with priority `100`. The
   chapter on [Priorities and ordering](04-priorities-and-ordering.md)
   explains why and how to use the named constants (`PRIORITY_HIGH`,
   `PRIORITY_NORMAL`, `PRIORITY_LOW`).
2. **Listeners receive whatever you pass to `trigger()`.** The
   signature is `trigger(string $name, ...$arguments)` — the
   `$arguments` are forwarded to each listener via
   `call_user_func_array`.

```php
Events::on('greet', function (string $name, string $me): void {
    echo "Hi {$name}. I am {$me}." . PHP_EOL;
}, 99);

Events::trigger('greet', 'World', 'John');
// Hi World. I am John.
```

## Owning your own dispatcher

If you would rather not lean on the static facade — for example,
because you want one dispatcher per request, or because a hard-to-test
global is a smell — use `Event` directly:

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

`Event` and `Events` expose the same surface. The decision is purely
about scope and testability, not about features. See
[chapter 2](02-events-facade.md) for a side-by-side discussion.

## What's next

- [Chapter 2 — The `Events` facade](02-events-facade.md) covers the
  static API in full, including the new `reset()` and
  `setInstance()` test hooks.
- [Chapter 3 — Using `EventEmitter` directly](03-event-emitter.md)
  covers the low-level primitive — useful when you do not want or
  need short-circuit / simulate / debug.
- [Chapter 4 — Priorities and ordering](04-priorities-and-ordering.md)
  is the contract you should read before writing code that depends on
  *when* listeners fire.
