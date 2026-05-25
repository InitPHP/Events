# Debug and simulate modes

`Event` (and therefore `Events`) carries two opt-in instrumentation
modes that are not part of the low-level `EventEmitter`:

- **Simulate mode** — `trigger()` does not actually invoke the
  listeners, but still walks the priority queue and still returns
  `true`. Useful for dry-runs and "what would happen if I triggered
  this?" diagnostics.
- **Debug mode** — every `trigger()` invocation appends a record to
  an internal log. Useful for timing measurements, tracing dispatch
  order, or asserting in tests that a particular event was triggered.

Both modes are off by default and can be flipped independently. The
two modes compose: with both on, the dispatcher walks the queue,
skips listener invocation, and still records each event in the debug
log.

## Simulate mode

```php
$dispatcher = new Event();

$dispatcher->setSimulate(true);
$dispatcher->on('e', function () {
    echo "called!" . PHP_EOL;   // never prints while simulate is on
    return false;               // even this is ignored
});

$result = $dispatcher->trigger('e');
// nothing prints, $result === true
```

Key properties:

- `trigger()` always returns `true` in simulate mode, regardless of
  what the listeners *would have* returned.
- One-shot listeners registered via `once()` are still dropped after
  the trigger (the once-contract is "fire at most once" — simulate
  mode counts as a "fire").
- The debug log (if debug mode is also on) still records the event.

When you turn simulate mode off, the dispatcher returns to running
listeners normally — no listener registry state is touched by
toggling the flag.

### Common uses

- **Dry-run of destructive operations.** Hook a `dispatch.payment`
  event up to real bank-call listeners, but trigger it under
  `setSimulate(true)` in a development environment so the UI can
  exercise the full path without moving any money.
- **Disabling all hooks temporarily.** Setting simulate is faster
  than calling `removeAllListeners()` because it preserves the
  registry — flip it back off and everything still works.
- **Performance baselines.** Compare the cost of "dispatcher walk
  without listeners" vs "dispatcher walk with listeners" by toggling
  simulate.

## Debug mode

```php
$dispatcher = new Event();
$dispatcher->setDebugMode(true);

$dispatcher->on('a', function (): void { /* ... */ });
$dispatcher->on('b', function (): void { /* ... */ });

$dispatcher->trigger('a');
$dispatcher->trigger('b');
$dispatcher->trigger('a');

$log = $dispatcher->getDebug();
// $log === [
//     ['start' => <float>, 'end' => <float>, 'event' => 'a'],
//     ['start' => <float>, 'end' => <float>, 'event' => 'b'],
//     ['start' => <float>, 'end' => <float>, 'event' => 'a'],
// ];
```

Properties:

- One entry is appended per **listener invocation**, not per
  `trigger()` call. An event with three listeners produces three
  entries (all with the same `event` name).
- `start` is captured with `microtime(true)` *before* the listener
  call.
- `end` is captured with `microtime(true)` *after* the listener
  returns (or throws — see below).
- The log persists for the lifetime of the dispatcher (or until
  `clearDebug()`). It is **not** automatically capped — long-running
  workers with debug mode on should call `clearDebug()` periodically.

### Reading the log

```php
$totalEventCalls = count($log);

$byEvent = [];
foreach ($log as $entry) {
    $byEvent[$entry['event']][] = $entry['end'] - $entry['start'];
}

foreach ($byEvent as $event => $durations) {
    $avg = array_sum($durations) / count($durations);
    echo sprintf("%-30s avg=%.4fs (n=%d)\n", $event, $avg, count($durations));
}
```

### Clearing the log

```php
$dispatcher->clearDebug();    // returns $this, so chainable
```

Useful between test cases, between worker jobs, or whenever the log
has served its purpose and you do not want it eating memory.

## Composing simulate and debug

The two modes are independent. The most useful combination is
"simulate **on**, debug **on**" — that gives you the cost-free
equivalent of "what would happen if I ran this":

```php
$dispatcher
    ->setSimulate(true)
    ->setDebugMode(true);

$dispatcher->trigger('payment.captured', $order);

$dispatcher->getDebug();
// [['start' => ..., 'end' => ..., 'event' => 'payment.captured']]
// — registry was walked, but no listener was actually invoked.
```

In tests this is also a clean way to assert "this code path triggers
event X" without needing to register a sentinel listener:

```php
public function test_checkout_publishes_a_payment_event(): void
{
    $dispatcher = (new Event())->setSimulate(true)->setDebugMode(true);
    Events::setInstance($dispatcher);

    handleCheckout($order);

    $events = array_column($dispatcher->getDebug(), 'event');
    $this->assertContains('payment.captured', $events);
}
```

## Reading dispatcher state via `__debugInfo`

`Event` defines `__debugInfo()` so that `var_dump($dispatcher)`
returns a concise snapshot rather than the raw internal arrays:

```php
var_dump(new Event());

// object(InitPHP\Events\Event)#1 (3) {
//   ["simulate"]=>  bool(false)
//   ["debugMode"]=> bool(false)
//   ["debugData"]=> array(0) { }
// }
```

This is purely a `var_dump` convenience — the listener registry
itself is reachable via `getEmitter()->listeners()` if you need to
introspect it.

## What `EventEmitter` does not have

Neither simulate nor debug mode exists on `EventEmitter`. If you are
working at the low level and want comparable behaviour:

- For dry-runs, walk `$emitter->listeners($event)` manually and skip
  the `call_user_func_array` step.
- For tracing, wrap each registered listener in a logging adapter
  before `on()`'ing it.

If both are something you need often, just step up to `Event` — that
is exactly the gap it fills.

## Next

- [Chapter 7 — Migrating from `initphp/event-emitter`](07-migration-from-event-emitter.md)
- [Chapter 8 — Recipes](08-recipes.md)
