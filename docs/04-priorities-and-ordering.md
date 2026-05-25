# Priorities and ordering

This chapter is the contract: read it once and you know exactly what
will happen when `trigger()` (or `emit()`) runs.

## The three rules

1. **Lower numeric priority runs first.** Priority `10` fires before
   priority `100`, which fires before priority `200`. The names of
   the three convenience constants reflect *importance*, not
   *position*:

   ```php
   Event::PRIORITY_HIGH    = 10    // most important → runs first
   Event::PRIORITY_NORMAL  = 100   // default
   Event::PRIORITY_LOW     = 200   // least important → runs last
   ```

2. **Within the same priority, listeners run in registration order
   (FIFO).** Add listener A then listener B at priority 50, and A
   runs before B.

3. **Registration order does not matter across priorities.** Add a
   priority-200 listener first and a priority-10 listener second:
   the priority-10 listener still runs first.

That's the whole contract.

## Concrete example

```php
$dispatcher = new Event();

$dispatcher
    ->on('boot', function () { echo 'low'    . PHP_EOL; }, Event::PRIORITY_LOW)
    ->on('boot', function () { echo 'high-a' . PHP_EOL; }, Event::PRIORITY_HIGH)
    ->on('boot', function () { echo 'normal' . PHP_EOL; })          // default = NORMAL
    ->on('boot', function () { echo 'high-b' . PHP_EOL; }, Event::PRIORITY_HIGH);

$dispatcher->trigger('boot');
```

Output:

```
high-a
high-b
normal
low
```

- `high-a` runs before `high-b` because rule 2 (FIFO inside a
  priority) keeps their registration order.
- `normal` runs after both `high-*` because it has a larger numeric
  priority (rule 1).
- `low` runs last because it has the largest numeric priority.

## How `once()` interacts with priorities

`once()` listeners are stored in a *separate* internal registry from
regular `on()` listeners, but `trigger()` / `emit()` merge them into a
single, priority-sorted queue at dispatch time. So:

```php
$dispatcher
    ->on('e',   function () { echo 'reg-100'  . PHP_EOL; }, 100)
    ->once('e', function () { echo 'once-50'  . PHP_EOL; }, 50)
    ->on('e',   function () { echo 'reg-200'  . PHP_EOL; }, 200);

$dispatcher->trigger('e');
$dispatcher->trigger('e');
```

First trigger:

```
once-50
reg-100
reg-200
```

Second trigger:

```
reg-100
reg-200
```

The `once-50` listener is dropped after the first trigger, even
though it had the lowest priority and ran *before* the regular
listeners. The once-contract is "fire at most once", regardless of
priority.

## How the priority sort changed in v2.0

In 1.x, `EventEmitter::listeners()` had a long-standing bug: it
applied `ksort()` to the wrong array level (the inner per-priority
listener list, which is already numerically indexed, instead of the
outer priority map). The visible effect was that listeners ran in
*registration order*, not priority order. v2.0 fixes that — see the
[v2.0 changelog](../CHANGELOG.md) and
[chapter 7](07-migration-from-event-emitter.md) if you are upgrading
and want the full background.

If your 1.x code happened to register listeners in ascending priority
order (which is the obvious style), the visible behaviour does not
change in v2.0. If you registered them in some other order and were
relying on the broken behaviour, you'll see a different invocation
order now.

## The "return false stops the chain" rule

`Event::trigger()` (and therefore `Events::trigger()`) has one more
ordering contract on top of the priority sort:

> If any listener returns boolean `false`, the chain is *halted*.
> Subsequent listeners are not invoked, and `trigger()` itself
> returns `false`.

This is the same convention as WordPress's `apply_filters`. Note that
the listener has to return the literal `false`, not a falsy value —
returning `null` (the implicit return of every `function () { ... }`
that does not say otherwise) or `0` does *not* halt the chain.

```php
$dispatcher
    ->on('chain', function () { return 'continue'; })
    ->on('chain', function () { return false; })      // halts here
    ->on('chain', function () { echo 'never runs'; });

$result = $dispatcher->trigger('chain');
// $result === false
// the third listener never executed
```

`EventEmitter::emit()` does **not** have this contract — it returns
`void` and ignores return values. If you want short-circuit
semantics, use `Event::trigger()` (or call `listeners()` and loop
yourself).

## Case-insensitive event names

Event names are folded to lower-case before storage and lookup:

```php
$dispatcher->on('User.Created', $listener);
$dispatcher->trigger('user.created', ...);   // listener fires
$dispatcher->trigger('USER.CREATED', ...);   // listener fires too
```

Pick one casing convention in your codebase (lower-case is the
obvious choice) so reading the source still tells you which event
is which.

## Removing listeners and priorities

A few non-obvious cases:

- `removeAllListeners('e')` drops both regular *and* one-shot
  listeners for `'e'`.
- `removeListener('e', $cb)` removes every occurrence of `$cb`
  across every priority slot in which it was registered (rare, but
  it can bite you if you registered the same callback at multiple
  priorities deliberately).
- `clearOnceListeners('e')` drops one-shot listeners for `'e'`
  *without invoking them*. This is mostly an internal helper used by
  `Event::trigger()` to honour the once-contract when the chain
  short-circuits — most application code will never call it
  directly.

See [chapter 5](05-once-and-removal.md) for the full removal contract.

## Next

- [Chapter 5 — Once-listeners, removal, and cleanup](05-once-and-removal.md)
- [Chapter 6 — Debug and simulate modes](06-debug-and-simulate.md)
- [Chapter 9 — API reference](09-api-reference.md)
