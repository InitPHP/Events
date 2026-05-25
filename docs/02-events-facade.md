# The `Events` facade

`InitPHP\Events\Events` is a thin static facade over a single,
lazily-built `Event` instance. It exists because most applications
have a small number of cross-cutting hooks — request start, user
registered, payment captured — and threading a dispatcher through
every layer to publish those is more ceremony than the use-case
warrants.

If you do want to thread a dispatcher around (it's the more
testable choice), skip this chapter and read
[Chapter 3](03-event-emitter.md) or use `Event` directly.

## How the facade works

```php
public static function __callStatic($name, $arguments)
{
    return self::getInstance()->{$name}(...$arguments);
}
```

That's the entire body. Every static call goes to the same `Event`
instance, which is created on first use:

```php
public static function getInstance()
{
    if (!isset(self::$Instance)) {
        self::$Instance = new Event();
    }
    return self::$Instance;
}
```

So `Events::on(...)` and `(new Event())->on(...)` behave identically
— the facade just removes the `new Event()` boilerplate from the
caller.

## Public API

| Method | Forwards to | Notes |
| --- | --- | --- |
| `Events::trigger(string $name, ...$args)` | `Event::trigger()` | Returns `bool`. Returns `false` if a listener returned `false`. |
| `Events::on(string, callable, int $priority = PRIORITY_NORMAL)` | `Event::on()` | Returns the `Event` instance (chainable on the dispatcher itself). |
| `Events::once(string, callable, int $priority = PRIORITY_NORMAL)` | `Event::once()` | One-shot listener. |
| `Events::off(string, callable)` | `Event::off()` | Remove a specific listener. |
| `Events::removeAllListeners(?string = null)` | `Event::removeAllListeners()` | Wipe one event, or every event. |
| `Events::setSimulate(bool)` / `Events::getSimulate()` | `Event` getters/setters | Toggle simulate mode. |
| `Events::setDebugMode(bool)` / `Events::getDebugMode()` / `Events::getDebug()` / `Events::clearDebug()` | `Event` debug methods | Opt-in debug log. |
| `Events::getEmitter()` | `Event::getEmitter()` | Access the underlying `EventEmitter`. |
| `Events::getInstance(): Event` | — | Return the shared dispatcher. |
| `Events::setInstance(Event)` | — | Inject a pre-configured dispatcher. |
| `Events::reset()` | — | Drop the shared instance. |

## Constants

```php
Events::PRIORITY_HIGH    // 10   — runs first
Events::PRIORITY_NORMAL  // 100  — default
Events::PRIORITY_LOW     // 200  — runs last
```

These mirror `Event::PRIORITY_*`. Use the names for readability —
nothing else in the package looks at the numeric values.

## Worked example

```php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\Events;

// One-shot bootstrap notification.
Events::once('app.boot', function (): void {
    echo "Booted at " . date('c') . PHP_EOL;
});

// A "filter" listener that can short-circuit.
Events::on('save.user', function (array $user) {
    if ($user['email'] === '') {
        return false;        // halts the chain — subsequent listeners do not run
    }
}, Events::PRIORITY_HIGH);

// A "side effect" listener that runs after validation.
Events::on('save.user', function (array $user): void {
    audit_log($user);
}, Events::PRIORITY_LOW);

Events::trigger('app.boot');

if (Events::trigger('save.user', $user)) {
    persist($user);
} else {
    flash('user validation failed');
}
```

## When `Events` is the right choice

- You publish a small set of well-known hooks for plugins or extensions
  to attach to and the publisher / subscriber are deliberately
  decoupled.
- You want the call site to read as `Events::on(...)` rather than
  `$this->dispatcher->on(...)` to make the cross-cutting nature of
  the hook obvious.
- You only need *one* dispatcher — there is no scenario where two
  parts of the application would want independent listener
  registries.

## When `Events` is the wrong choice

- You have several dispatchers (one per tenant, one per
  long-running worker, one per HTTP request in a long-lived
  process). The facade serves one shared instance, period.
- You test heavily and shared global state hurts. The
  [Testing](#testing-against-the-facade) section below describes how
  to keep tests honest, but the friction is real.
- You're a library author. Don't make your consumers reach into a
  facade — accept an `Event` or `EventEmitterInterface` in your
  constructor and let the caller decide.

## Testing against the facade

`Events::reset()` empties the singleton so the next call rebuilds a
fresh one. Use it in `setUp()` (and ideally `tearDown()` too, to keep
test failures from leaking state into subsequent suites):

```php
final class MyTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        Events::reset();
    }

    protected function tearDown(): void
    {
        Events::reset();
    }

    public function test_something_publishes_an_event(): void
    {
        $captured = null;
        Events::on('e', function ($payload) use (&$captured): void {
            $captured = $payload;
        });

        publishSomething(['k' => 'v']);

        $this->assertSame(['k' => 'v'], $captured);
    }
}
```

`Events::setInstance($event)` lets you inject a pre-configured
dispatcher. Useful when a test needs simulate or debug toggled on
from the very first call:

```php
$debugDispatcher = (new Event())->setDebugMode(true);
Events::setInstance($debugDispatcher);

doSomethingThatTriggers();

$log = Events::getDebug();   // every trigger() landed here
```

## Lifecycle in long-running workers

In long-running processes (queue workers, HTTP servers, scheduled
daemons) the facade keeps state between requests / jobs. That is
usually a bug. Either:

- Call `Events::reset()` at the boundary of each request / job, or
- Stop using the facade and pass an `Event` instance per request /
  job (see [Chapter 1](01-getting-started.md#owning-your-own-dispatcher)).

## Next

- [Chapter 3 — Using `EventEmitter` directly](03-event-emitter.md)
- [Chapter 4 — Priorities and ordering](04-priorities-and-ordering.md)
- [Chapter 6 — Debug and simulate modes](06-debug-and-simulate.md)
