# `Events` Facade

`InitPHP\Events\Events` is the high-level, static entry point. It is a
thin forwarder over a single shared
[`Event`](API-Reference.md#class-initphpeventsevent) instance held in a
private static property, so calls from anywhere in your codebase land in
the same listener registry.

```php
use InitPHP\Events\Events;

Events::on('app.boot', function () { /* … */ });
Events::trigger('app.boot');
```

## When to use this API

Reach for the `Events` facade when:

- You want a **global, app-wide** hook registry, in the spirit of
  WordPress `do_action` / `add_action`.
- You need to **stop a chain** by returning `false` from a listener.
- You want **variadic arguments** at the call site (`Events::trigger($n, $a, $b, $c)`)
  rather than passing them as an array.
- You want the optional **simulate** and **debug** modes — see
  [Simulate & Debug Mode](Simulate-and-Debug.md).

If you prefer dependency-injecting an event bus, or you want a plain
object you can build per-request, use the
[low-level `EventEmitter`](EventEmitter.md) instead. (`once()`, `off()`,
and `removeAllListeners()` exist on both APIs now, so they are not a
reason to switch.)

## The full surface

```php
namespace InitPHP\Events;

class Events
{
    const PRIORITY_HIGH   = 10;
    const PRIORITY_NORMAL = 100;
    const PRIORITY_LOW    = 200;

    // Listener registration / removal — forwarded to the shared Event instance.
    public static function on(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): Event;
    public static function once(string $name, callable $callback, int $priority = self::PRIORITY_NORMAL): Event;
    public static function off(string $name, callable $callback): Event;
    public static function removeAllListeners(?string $name = null): Event;

    // Dispatch.
    public static function trigger(string $name, ...$arguments): bool;

    // Simulate / debug.
    public static function setSimulate(bool $simulate = false): Event;
    public static function getSimulate(): bool;
    public static function setDebugMode(bool $debugMode = false): Event;
    public static function getDebugMode(): bool;
    public static function getDebug(): array;
    public static function clearDebug(): Event;

    // Backing emitter — for callers that need the low-level surface.
    public static function getEmitter(): EventEmitter;

    // Singleton lifecycle (test hooks, long-running workers).
    public static function getInstance(): Event;
    public static function setInstance(Event $event): void;
    public static function reset(): void;
}
```

Every method except the lifecycle trio (`getInstance` / `setInstance` /
`reset`) is forwarded to the same singleton `Event` object via
`__callStatic`. The facade also defines an instance `__call`, so
`(new Events)->on(...)` works identically and lands on the same shared
instance — but in practice everyone uses the static form.

## `Events::on(string $name, callable $callback, int $priority = PRIORITY_NORMAL)`

Registers a listener for the given event name.

```php
Events::on('user.registered', function (array $user) {
    error_log('new user: ' . $user['email']);
});
```

- `$name` is **case-insensitive** — it is lowercased internally, so
  `'user.registered'` and `'User.Registered'` reference the same event.
- `$callback` can be **any PHP callable**: a closure, a `'function_name'`
  string, a `[$object, 'method']` pair, an `[ClassName::class, 'method']`
  static pair, or an invokable object. See
  [Listeners & Priorities](Listeners-and-Priorities.md#what-counts-as-a-listener).
- `$priority` is an integer. Lower numeric value runs first. The
  constants `PRIORITY_HIGH` (10), `PRIORITY_NORMAL` (100, default), and
  `PRIORITY_LOW` (200) are exposed as semantic shorthands.
- **Returns the shared `Event` instance.** That makes the call chainable
  (`Events::on(...)->on(...)->trigger(...)`).

> **Ordering.** Listeners are dispatched in ascending numeric priority
> order; within the same priority, in registration order (FIFO). The
> full contract lives at [Listeners & Priorities](Listeners-and-Priorities.md).
> Priorities were silently ignored before 2.1.0 (listeners ran in
> registration order); this is fixed in 2.1.0 and is a behaviour change —
> see the [Migration Guide](Migration-Guide.md).

## `Events::once(string $name, callable $callback, int $priority = PRIORITY_NORMAL)`

Same as `on()`, but the listener is automatically removed after the
**next** `trigger()` of that event. Returns the shared `Event` instance.

```php
Events::once('app.ready', function () {
    Container::warmup();   // runs at most once even if app.ready fires twice
});
```

The one-shot contract is "fire at most once" — it is honoured even if
the chain is stopped by a `false` return earlier in the priority queue,
or if a listener throws. The cleanup runs in a `try/finally` block.

## `Events::off(string $name, callable $callback)`

Removes a specific listener previously registered with `on()` or
`once()`. Listener identity is strict (`===`) — keep a reference to the
exact callable you registered.

```php
$cb = function () { /* … */ };

Events::on('e', $cb);
Events::off('e', $cb);     // gone
```

Returns the shared `Event` instance. No-op (no exception) if the listener
is not registered.

## `Events::removeAllListeners(?string $name = null)`

With an event name, drops every listener (regular and one-shot) for that
event. With no argument, wipes the entire registry.

```php
Events::removeAllListeners('user.login');    // one event
Events::removeAllListeners();                // every event
```

Returns the shared `Event` instance.

## `Events::trigger(string $name, ...$arguments): bool`

Invokes every registered listener for `$name`, in priority order,
passing the variadic `$arguments` straight through to each callable.

```php
Events::trigger('user.registered', $user, 'web');
// is equivalent to, for each listener $L (in priority order):
//   $L($user, 'web');
```

The return value is:

- `true` if all listeners ran (or there were none registered).
- `false` as soon as **any** listener returns `false` — remaining
  listeners are then skipped.

```php
Events::on('save', function () { return false; });          // veto
Events::on('save', function () { echo "never runs\n"; });

$ok = Events::trigger('save');
var_dump($ok); // bool(false)
```

Any other return value (`null`, `true`, strings, objects, …) is treated
as "continue". See [Stopping Propagation](Stopping-Propagation.md) for
patterns that build on this contract.

If `$name` is not a string, `Events::trigger` throws
`\InvalidArgumentException`.

## `Events::setSimulate(bool $simulate)` / `getSimulate()`

Toggle **simulate mode**. When `true`, `trigger()` still resolves the
listener list (so debug records are still produced if debug mode is on),
but it does **not** invoke any callback — every listener is treated as
if it returned `true`. Use this in tests, dry-runs, or "what would
happen" debug pages.

```php
Events::setSimulate(true);
Events::on('mailout', function () { /* sends real email */ });
Events::trigger('mailout'); // returns true; no email sent
Events::setSimulate(false);
```

Full discussion: [Simulate & Debug Mode](Simulate-and-Debug.md).

## `Events::setDebugMode(bool)` / `getDebugMode()` / `getDebug()` / `clearDebug()`

Toggle **debug mode**. When `true`, every `trigger()` call appends one
record per invoked listener to an internal log, retrievable via
`Events::getDebug()`. `clearDebug()` empties the log (returns the shared
`Event` instance for chaining).

```php
Events::setDebugMode(true);

Events::on('boot', function () { usleep(1500); });
Events::trigger('boot');

print_r(Events::getDebug());
/*
Array
(
    [0] => Array
        (
            [start] => 1716567121.4521
            [end]   => 1716567121.4538
            [event] => boot
        )
)
*/

Events::clearDebug();   // empty the log without disabling debug mode
```

Full discussion: [Simulate & Debug Mode](Simulate-and-Debug.md).

## `Events::getEmitter(): EventEmitter`

Returns the underlying `EventEmitter` instance the facade is forwarding
to. Useful when you need the low-level `emit()` (no short-circuit) or
`clearOnceListeners()` on the same registry the facade is using.

```php
$listenerCount = count(Events::getEmitter()->listeners('boot'));
```

## Singleton lifecycle

The static facade lazily creates a single `Event` instance on first use:

```php
// src/Events.php (paraphrased)
protected static $Instance;

public static function getInstance(): Event
{
    if (!isset(self::$Instance)) {
        self::$Instance = new Event();
    }
    return self::$Instance;
}
```

Three lifecycle methods are public:

### `Events::getInstance(): Event`

Return the shared dispatcher, lazily building it on the first call. Use
when you want to operate on the `Event` directly (e.g. pass it to
something that wants an `Event` parameter).

### `Events::setInstance(Event $event): void`

Replace the shared instance. Useful for **injecting a pre-configured
dispatcher** — for example, one with simulate / debug already on, or a
test double:

```php
$debugDispatcher = (new Event())->setDebugMode(true);
Events::setInstance($debugDispatcher);

doSomethingThatTriggers();

$log = Events::getDebug();   // every trigger() landed in the injected dispatcher
```

### `Events::reset(): void`

Drop the shared instance so the next call rebuilds a fresh one. Call
this in test `setUp()` / `tearDown()` and at the boundary of each
request / job in long-running workers (queue workers, persistent HTTP
servers) — otherwise listeners from one iteration bleed into the next.

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
}
```

## When the facade is the wrong choice

- You need several dispatchers (one per tenant, one per long-running
  worker, one per HTTP request in a long-lived process). The facade serves
  one shared instance, period.
- You test heavily and shared global state hurts. `Events::reset()` and
  `Events::setInstance()` keep tests honest, but the friction is real.
- You are a library author. Do not make your consumers reach into a
  facade — accept an `Event` or an `EventEmitterInterface` in your
  constructor and let the caller decide.

## Own your dispatcher with `Event`

`Event` is the class the facade forwards to, and you can instantiate it
yourself. It has the same surface as the facade (`on`, `once`, `off`,
`removeAllListeners`, `trigger`, simulate and debug controls,
`getEmitter()`), and every setter is chainable:

```php
use InitPHP\Events\Event;

$dispatcher = new Event();

$dispatcher
    ->on('boot', function () { echo "normal\n"; })
    ->on('boot', function () { echo "late\n"; }, Event::PRIORITY_LOW);

$dispatcher->trigger('boot');
```

In a long-running worker, the facade keeps listeners between jobs. Either
call `Events::reset()` at the boundary of each job, or — more robust,
because the boundary is explicit — create a fresh `Event` per job:

```php
while ($job = $queue->reserve()) {
    $events = new \InitPHP\Events\Event();

    JobHandlers::register($events, $job);   // per-job listeners

    handle($job, $events);
}
```

## A complete example

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Events\Events;

Events::setDebugMode(true);

// audit log
Events::on('order.placed', function (array $order) {
    error_log("order placed: {$order['id']}");
});

// loyalty points
Events::on('order.placed', function (array $order) {
    if ($order['total'] >= 100) {
        echo "Awarded loyalty points to {$order['user']}\n";
    }
});

// fraud check — can veto downstream listeners
Events::on('order.placed', function (array $order) {
    return $order['total'] < 10_000; // returning false stops the chain
}, Events::PRIORITY_HIGH);            // runs first thanks to priority

// shipping (skipped if fraud check vetoed above)
Events::on('order.placed', function (array $order) {
    echo "ship to {$order['address']}\n";
}, Events::PRIORITY_LOW);             // runs last

$ok = Events::trigger('order.placed', [
    'id'      => 42,
    'user'    => 'jane',
    'email'   => 'jane@x.test',
    'total'   => 250,
    'address' => 'Somewhere 1',
]);

echo $ok ? "✔ completed\n" : "✘ vetoed\n";
print_r(Events::getDebug());
```

## See also

- [`EventEmitter`](EventEmitter.md) — the same engine, exposed as an
  instantiable object.
- [Listeners & Priorities](Listeners-and-Priorities.md) — how callables
  are resolved and the exact ordering contract.
- [Stopping Propagation](Stopping-Propagation.md) — the full `return false`
  contract.
- [Simulate & Debug Mode](Simulate-and-Debug.md) — testing and
  observability.
- [API Reference](API-Reference.md) — every method, constant, and
  exception.
