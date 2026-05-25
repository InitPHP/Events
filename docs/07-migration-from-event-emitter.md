# Migrating from `initphp/event-emitter`

The standalone
[`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter)
package was merged into `initphp/events` and is now deprecated.

This chapter is a checklist for the migration:

1. Swap the Composer dependency.
2. Decide whether to rely on the BC alias or move to the canonical
   namespace.
3. Re-read your `emit()` and priority assumptions — both had bugs in
   1.x that are fixed in 2.0.

## 1. Swap the dependency

```diff
- "initphp/event-emitter": "^1.0",
+ "initphp/events": "^2.0"
```

```bash
composer update
```

Composer will not install both packages side-by-side because
`initphp/events:^2.0` declares a `replace` for
`initphp/event-emitter`. If you don't see `initphp/event-emitter`
disappear from `vendor/`, you have something else (a transitive
dependency, an explicit `require-dev`) holding it in place — search
your lockfile.

## 2. Decide on the namespace

The package ships a backwards-compatibility alias for the legacy
fully-qualified names:

```php
// src/aliases.php in initphp/events (composer files autoload):
class_alias(
    \InitPHP\Events\EventEmitter::class,
    'InitPHP\\EventEmitter\\EventEmitter'
);
class_alias(
    \InitPHP\Events\EventEmitterInterface::class,
    'InitPHP\\EventEmitter\\EventEmitterInterface'
);
```

So **no source change is required**:

```php
// Existing 1.x code — still works in 2.0:
use InitPHP\EventEmitter\EventEmitter;

$bus = new EventEmitter();
$bus->on('e', $listener);
$bus->emit('e', [$payload]);
```

When you next touch the code, prefer the new canonical names:

```php
// Recommended for new / touched code:
use InitPHP\Events\EventEmitter;
```

The alias is a transition aid. It may be removed in a future major
version, so do not put it off forever — but you do not need a
big-bang rename either.

## 3. Re-read your `emit()` and priority assumptions

Two long-standing 1.x bugs are fixed in 2.0. Both are *silent*
behaviour changes: nothing about your code is wrong; the dispatcher
just does what it was always supposed to do.

### Fix 1 — `emit()` actually invokes listeners

The 1.x `EventEmitter::emit()` had a bug where the **entire
listeners array** (not each individual listener) was passed to
`call_user_func_array()`. The result was that emitted events
silently fired no listeners at all.

```php
// 1.x reality:
$bus->emit('e', [$payload]);
// → call_user_func_array($listeners, [$payload])
// → "Array to callable" warning, nothing fires.

// 2.x:
$bus->emit('e', [$payload]);
// → for each $listener: call_user_func_array($listener, [$payload])
// → listeners actually fire.
```

If any of your 1.x `emit()` calls had no observable effect, they
*will* now have observable effects. Audit:

- Listeners that mutate state — they now actually mutate.
- Listeners that send notifications / log / call external services
  — they now actually do.
- Tests that asserted "this listener wasn't called" — they may now
  fail. The 1.x behaviour was the bug; the test was passing for the
  wrong reason.

### Fix 2 — listeners run in priority order

The 1.x `EventEmitter::listeners()` had a bug where `ksort()` was
applied to the wrong array level: the inner per-priority listener
list (which is already numerically indexed `[0, 1, 2, ...]`) instead
of the outer priority map. The effect was that listeners ran in
**registration order**, not in priority order.

```php
// 1.x reality:
$bus->on('boot', $a, 100);   // registered first → ran first
$bus->on('boot', $b, 10);    // registered second → ran second
$bus->emit('boot');
// Output: a, then b

// 2.x:
$bus->on('boot', $a, 100);
$bus->on('boot', $b, 10);
$bus->emit('boot');
// Output: b, then a (priority 10 < 100)
```

If your 1.x code happened to register listeners in ascending
priority order (the obvious style), the visible behaviour does not
change in 2.0. If you registered them in some other order, you'll
see the order flip.

See [chapter 4](04-priorities-and-ordering.md) for the full
ordering contract you can now rely on.

## 4. Things that did *not* change

- The argument-shape of `emit()` is unchanged: it still takes
  `(string $event, array $arguments = [])`. Each listener is invoked
  with the array unpacked.
- The fluent return of `on()` / `once()` is unchanged (they return
  the emitter).
- Event-name case-folding is unchanged (`strtolower` on the way in
  and out).
- `removeListener()` still returns `void`, not `$this`. (The
  high-level `Event::off()` returns `$this`, but the underlying
  emitter does not — that part of the interface is unchanged.)

## 5. New surface available in 2.0

If you choose to take advantage of it after migrating:

- **`Events::reset()`** and **`Events::setInstance(Event)`** —
  finally usable test hooks for the static facade.
- **`Event::once()` / `Event::off()` / `Event::removeAllListeners()`**
  — the high-level dispatcher now exposes the same lifecycle
  controls that the low-level emitter has had all along.
- **`EventEmitter::clearOnceListeners()`** — drop one-shot listeners
  without invoking them. New on the interface; you only need to
  worry about this if you ship your own `EventEmitterInterface`
  implementation.

Full list in the [v2.0 changelog](../CHANGELOG.md).

## Quick checklist

- [ ] Updated `composer.json` to depend on `initphp/events:^2.0`.
- [ ] Ran `composer update` and confirmed the old
      `initphp/event-emitter` is gone from `vendor/`.
- [ ] Searched the codebase for `\InitPHP\EventEmitter\` and decided
      whether to rename now or leave the alias in place for later.
- [ ] Looked at every `EventEmitter::emit()` call and confirmed the
      listeners actually firing is the desired behaviour (1.x had
      them silently no-op).
- [ ] Looked at every `on()` registration that did not follow
      ascending-priority order and confirmed the priority-sorted
      execution is the desired behaviour.
- [ ] If you ship your own `EventEmitterInterface` implementation,
      added a `clearOnceListeners()` method to it.

## Next

- [Chapter 4 — Priorities and ordering](04-priorities-and-ordering.md)
- [Chapter 8 — Recipes](08-recipes.md)
- [Chapter 9 — API reference](09-api-reference.md)
