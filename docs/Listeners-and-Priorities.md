# Listeners & Priorities

This page covers the mechanics shared by both APIs: what counts as a
listener, how event names are normalised, and the exact contract for
the `$priority` parameter.

## What counts as a listener

Any value PHP recognises as a `callable` is a valid listener. Both
`Events::on()` and `EventEmitter::on()`/`once()` validate this with
`is_callable()` and throw `\InvalidArgumentException` otherwise.

```php
use InitPHP\Events\Events;

// 1. Closure (most common)
Events::on('e', function ($x) { /* … */ });

// 2. Arrow function (PHP 7.4+)
Events::on('e', fn ($x) => doSomething($x));

// 3. Named function as a string
function my_listener($x) { /* … */ }
Events::on('e', 'my_listener');

// 4. Instance method
class Mailer {
    public function send($user) { /* … */ }
}
$mailer = new Mailer();
Events::on('user.registered', [$mailer, 'send']);

// 5. Static method
class Audit {
    public static function record($x) { /* … */ }
}
Events::on('e', [Audit::class, 'record']);
// or: Events::on('e', 'Audit::record');

// 6. Invokable object
class Webhook {
    public function __invoke($event) { /* … */ }
}
Events::on('order.placed', new Webhook());
```

Listeners can accept **any number of parameters**. The arguments passed
to `trigger()` / `emit()` are forwarded positionally via
`call_user_func_array`, so the listener simply lists them:

```php
Events::on('order.placed', function (array $order, string $source, int $version) {
    /* … */
});

Events::trigger('order.placed', $order, 'web', 2);
```

If your listener declares fewer parameters than were supplied, PHP
silently ignores the extras. If it declares **more**, you will get a
`TypeError` (PHP 8+) or a warning + `null` for missing scalars.

## Return values

| Return from listener | `Events::trigger()` reaction | `EventEmitter::emit()` reaction |
| --- | --- | --- |
| `false` (strict) | **Stops** the chain; `trigger()` returns `false` | Ignored — the next listener still runs |
| Anything else (`null`, `true`, string, object, …) | Continue to the next listener | Continue to the next listener |

`EventEmitter::emit()` does not look at return values; it returns
`void`. If you need veto semantics, use the [`Events` facade](Events-Facade.md)
or read [Stopping Propagation](Stopping-Propagation.md).

## Event names are case-insensitive

Both APIs **lowercase** event names before they touch the internal
registry. These all reference the same event:

```php
Events::on('User.Registered', $a);
Events::on('user.registered', $b);
Events::on('USER.REGISTERED', $c);

Events::trigger('user.registered'); // fires $a, $b, $c
```

Choose one convention and stick with it for readability. Dot-separated
lowercase (`namespace.action`) is common; underscored (`user_registered`)
or kebab (`user-registered`) work just as well — pick whatever your
codebase already does.

Empty string `''` is technically a valid name (though not recommended).
`null`, integers, or other non-strings throw `\InvalidArgumentException`.

## Ordering — the contract

There are two rules, in this order:

1. **Listeners with a lower numeric priority fire first**, regardless
   of when they were registered.
2. **Within the same priority bucket, listeners fire in registration
   order (FIFO).**

The three convenience constants exist for readability — the names map
*importance* onto *position*:

```php
Event::PRIORITY_HIGH    = 10    // most important → runs first
Event::PRIORITY_NORMAL  = 100   // default
Event::PRIORITY_LOW     = 200   // least important → runs last
```

`Events::PRIORITY_*` are aliases of the `Event::PRIORITY_*` constants
with the same integer values. `EventEmitter::on()`'s default priority
is the integer `100` (= `PRIORITY_NORMAL`) — the constants are not
re-exposed on `EventEmitter` but the value matches.

### Worked example

```php
use InitPHP\Events\Event;
use InitPHP\Events\Events;

Events::on('boot', fn () => print("low\n"),    Event::PRIORITY_LOW);     // 200
Events::on('boot', fn () => print("high-a\n"), Event::PRIORITY_HIGH);    // 10
Events::on('boot', fn () => print("normal\n"));                          // 100, default
Events::on('boot', fn () => print("high-b\n"), Event::PRIORITY_HIGH);    // 10

Events::trigger('boot');
```

Output:

```text
high-a
high-b
normal
low
```

- `high-a` runs before `high-b` because rule 2 (FIFO inside a priority)
  preserves their registration order.
- `normal` runs after both `high-*` because its numeric priority is
  larger (rule 1).
- `low` runs last for the same reason.

### How `once()` interacts with priority

`once()` listeners are stored in a separate internal registry but the
dispatcher merges them into the same priority-sorted queue at dispatch
time. So a `once` registered at `PRIORITY_HIGH` runs before a regular
listener at `PRIORITY_NORMAL`, and is dropped after firing (or after
the chain halts — the once-contract is "fire at most once").

```php
use InitPHP\Events\Event;

$dispatcher = new Event();

$dispatcher
    ->on('e',   function () { echo "reg-100\n"; }, 100)
    ->once('e', function () { echo "once-50\n"; }, 50)
    ->on('e',   function () { echo "reg-200\n"; }, 200);

$dispatcher->trigger('e');   // once-50, reg-100, reg-200
$dispatcher->trigger('e');   // reg-100, reg-200
```

See [Once-Listeners & Removal](Once-and-Removal.md) for the full once-contract.

### Pre-2.1 caveat

Releases before 2.1.0 (the 1.x line and 2.0.0) had a bug:
`EventEmitter::listeners()` applied `ksort()` to the wrong array level, so
listeners ran in **registration order** regardless of their `$priority`. The
bug is fixed in 2.1.0; the ordering described above is the contract from now
on.

If your code happened to register listeners in ascending priority
order (the obvious style), the visible behaviour does not change in
2.1.0. If you registered them in some other order, you'll see the order
flip. Full details in the [Migration Guide](Migration-Guide.md#step-2--listeners-now-run-in-priority-order-210).

## Duplicate listeners

The same callable can be registered multiple times — it will fire as
many times as it was attached. Re-registering does not de-duplicate:

```php
$cb = function () { echo "tick\n"; };

Events::on('clock', $cb);
Events::on('clock', $cb);
Events::trigger('clock');
// → tick
//   tick
```

`Events::off()` / `EventEmitter::removeListener()` removes **one**
occurrence of that callable from **each priority slot** in which it was
registered. If the same callable was registered twice at the *same*
priority, a single call removes only one of the two — see
[Once-Listeners & Removal](Once-and-Removal.md#off--remove-a-specific-listener).

## See also

- [Stopping Propagation](Stopping-Propagation.md) — the `return false`
  contract on `Events::trigger()`.
- [`Events` facade](Events-Facade.md) and [`EventEmitter`](EventEmitter.md) —
  the two APIs that expose this behaviour.
- [API Reference](API-Reference.md) — exact method signatures and
  exceptions.
- [Migration Guide](Migration-Guide.md) — what changed between 1.x and 2.x.
