# Migration Guide

This guide covers three migrations:

1. **From `initphp/event-emitter` (any 1.x) → `initphp/events:^2.0`** —
   the standalone package has been merged into this one and is now
   deprecated.
2. **From `initphp/events:^1.0` → `initphp/events:^2.0`** — the same
   high-level `Events` facade, now bundling the low-level `EventEmitter`
   primitive.
3. **From `initphp/events` 2.x → 3.0.0** — the PHP minimum
   moves to 8.2.

In the first two cases the upgrade path is intentionally gentle: your
existing code keeps compiling.

## From 2.x → 3.0.0

| Before | After |
| --- | --- |
| PHP `>=5.6` (2.0.0 and 2.1.0) | **PHP `^8.2`** |

PHP 5.6 through 8.1 are no longer supported, and CI now covers PHP 8.2, 8.3,
8.4 and 8.5. The public API is unchanged — only the PHP version constraint
moved. If you are on an older PHP, lock to `"initphp/events": "^2.1"`. The
project's code style also moved to PER-CS, which affects contributors only.

## Which release changed what

| Release | Notes |
| --- | --- |
| `2.0.0` | Merged the standalone `initphp/event-emitter` package: bundled `EventEmitter` and `EventEmitterInterface`, `replace` declaration, BC aliases. |
| `2.1.0` | Priority order is honoured; `Event::on()` default priority is `PRIORITY_NORMAL`; once-listeners fire at most once through `Event`; new `Event::once()` / `off()` / `removeAllListeners()` / `clearDebug()` / `getEmitter()`; new `Events::reset()` / `setInstance()`, public `Events::getInstance()`; `EventEmitterInterface::clearOnceListeners()`. |
| `3.0.0` | PHP minimum raised to `^8.2`, code style moved to PER-CS; API unchanged (see [From 2.x → 3.0.0](#from-2x--300)). |

The changelog entry for 2.1.0 covers the whole 2.x line, because 2.0.0
shipped without a changelog.

## From `initphp/event-emitter`

### Step 1 — swap the dependency

```diff
  {
      "require": {
-         "initphp/event-emitter": "^1.0"
+         "initphp/events": "^2.0"
      }
  }
```

`initphp/events:^2.0` declares a Composer
[`replace`](https://getcomposer.org/doc/04-schema.md#replace) for
`initphp/event-emitter: *`, so Composer will refuse to install both
side-by-side. No need to manually remove the old package — `composer
update` will handle it.

```bash
composer update initphp/event-emitter initphp/events
```

### Step 2 — your code keeps working (alias shim)

`src/aliases.php` is autoloaded by Composer's `files` directive. It
creates two class aliases:

```php
// effectively:
class_alias(InitPHP\Events\EventEmitter::class, 'InitPHP\\EventEmitter\\EventEmitter');
class_alias(InitPHP\Events\EventEmitterInterface::class, 'InitPHP\\EventEmitter\\EventEmitterInterface');
```

That means existing code like this **does not need to change**:

```php
use InitPHP\EventEmitter\EventEmitter;          // old namespace
use InitPHP\EventEmitter\EventEmitterInterface; // old namespace

$bus = new EventEmitter();
$bus->on('e', $listener);
$bus->emit('e');
```

It resolves to the new classes via the alias. You can ship the upgrade
without touching application code.

### Step 3 — migrate the `use` statements at your leisure

When you next touch each file, switch to the canonical namespace:

```diff
- use InitPHP\EventEmitter\EventEmitter;
+ use InitPHP\Events\EventEmitter;

- use InitPHP\EventEmitter\EventEmitterInterface;
+ use InitPHP\Events\EventEmitterInterface;
```

The alias is intended as a transition aid; treat it as deprecated and
plan to clean up `use` statements before a future major release of this
package removes the shim.

### Behaviour change to verify — `emit()` bug fix (tag `1.0` only)

Tag `1.0` of the standalone `initphp/event-emitter` package shipped with
a bug in `EventEmitter::emit()`: it passed the whole listeners array
(rather than each individual listener) to `call_user_func_array`, so
listeners never actually ran. Tag `1.0.1` already fixed it, and no
`initphp/events` release has the bug.

Practical consequences:

- If you are coming from `initphp/event-emitter` `1.0.1`, nothing changes
  here.
- If you are coming from tag `1.0` and had `emit()` calls that *looked*
  like they worked but never actually invoked your listeners, **they will
  start firing now**.
- Audit every `EventEmitter::emit()` call site before deploying. If you
  see something like "no idea why this listener was registered — must
  have been dead code", run it — there's a chance it was never
  reachable under `1.0`.

### Behaviour change to verify — priority order

The standalone package sorted priorities at the wrong array level, so
listeners ran in registration order. `initphp/events` honours priorities
from 2.1.0 on — see [From `initphp/events:^1.0`](#from-initphpevents10--2x)
below.

### Step 4 — drop the shim eventually

Once your codebase imports only `InitPHP\Events\*`, the aliases in
`src/aliases.php` are unused — but harmless. They will be removed in a
future major release of this package; clean up your `use` statements
before then to avoid a hard break.

## From `initphp/events:^1.0` → `2.x`

`composer require initphp/events:^2.0` is the upgrade (Composer resolves it to
the newest 2.x release that your PHP version allows). The high-level
[`Events`](Events-Facade.md) facade keeps the same method names and the
same call shapes, so most code keeps compiling unchanged. But the
package fixes two long-standing bugs whose visible behaviour shifts,
and the facade's default priority changes — read the BC-break notes
below before deploying. Everything marked **2.1.0** below is absent from
2.0.0; see [Which release changed what](#which-release-changed-what).

### What's new in 2.x

Additive — does not break anything if you ignore it:

- **Bundled low-level [`EventEmitter`](EventEmitter.md)** — previously
  only available as the standalone `initphp/event-emitter` package.
  (2.0.0)
- **`EventEmitterInterface`** is now defined inside this package. (2.0.0)
- **`composer replace`** of `initphp/event-emitter`, so Composer
  refuses to install both packages side-by-side. (2.0.0)
- **Backwards-compatibility aliases** for the old
  `InitPHP\EventEmitter\*` namespace. (2.0.0)
- New high-level surface on `Event` / `Events`: **`once()`**,
  **`off()`**, **`removeAllListeners()`**, **`clearDebug()`**, and
  **`getEmitter()`**. (2.1.0)
- New singleton lifecycle on the facade: **`getInstance()`** (now
  public; was `protected` in 1.x), **`setInstance($event)`**, and
  **`reset()`** — finally usable test hooks. (2.1.0)
- New low-level method: **`EventEmitter::clearOnceListeners(?string)`**
  (also added to `EventEmitterInterface`). (2.1.0)

### Step 1 — `emit()` and the standalone package

`EventEmitter` only entered `initphp/events` in 2.0.0, so this step applies
to code that used the standalone `initphp/event-emitter` package: its tag
`1.0` had a bug where the entire listeners array (rather than each individual
listener) was passed to `call_user_func_array` and listeners silently never
ran. Tag `1.0.1` fixed it, and `initphp/events` has no such bug. See
[the `emit()` bug fix](#behaviour-change-to-verify--emit-bug-fix-tag-10-only)
above. Code that only used the `Events` facade is not affected.

### Step 2 — listeners now run in priority order (2.1.0)

Before 2.1.0, `EventEmitter::listeners()` had a bug: `ksort()` was applied to
the wrong array level (the inner per-priority listener list, which is
already numerically indexed `[0, 1, 2, …]`), instead of the outer
priority map. The effect was that listeners ran in **registration
order**, regardless of their `$priority`.

From 2.1.0, priority order is honoured: lower numeric priority runs first;
within the same priority, FIFO by registration order.

- If your code happened to register listeners in ascending
  priority order (the obvious style), the visible behaviour does not
  change.
- If you registered them in some other order and depended on the
  registration-order behaviour, the invocation order will flip.

This change affects both `Events::trigger()` and `EventEmitter::emit()`
— anything that goes through `EventEmitter::listeners()`. Background:
[Listeners & Priorities](Listeners-and-Priorities.md).

### Step 3 — `Event::on()` default priority changed (2.1.0)

Before 2.1.0, the default for `Event::on()` (and therefore `Events::on()`)
was `PRIORITY_LOW` (`200`). From 2.1.0, the default is `PRIORITY_NORMAL`
(`100`) — matching `EventEmitter::on()` and intuition.

- **No effect** if you always passed an explicit priority.
- **Effect** if you mixed default-priority `on()` calls with explicit
  `PRIORITY_LOW` ones: previously they shared a bucket; from 2.1.0 the
  defaults now run *before* the explicit `PRIORITY_LOW` ones.
- The fix: pass `Event::PRIORITY_LOW` explicitly to restore the old
  positioning, or audit your registrations and decide what you
  actually meant.

### Step 4 — `once()` on the high-level dispatcher now fires at most once (2.1.0)

Before 2.1.0, `Event::trigger()` pulled listeners via
`EventEmitter::listeners()` (which includes one-shot listeners) but
never cleaned them up — so one-shot listeners registered through
`Event` / `Events` (which… 1.x didn't expose, but if you reached into
`Event->getEmitter()` you'd see this) fired on every trigger.

From 2.1.0, `Event::trigger()` cleans up one-shot listeners in a
`try/finally` block — they fire **at most once**, even if the chain
is stopped by a `false` return or a listener throws. The cleanup is
inseparable from `trigger()`.

### Step 5 — `EventEmitterInterface::clearOnceListeners()` is now required (2.1.0)

If you ship your own implementation of `EventEmitterInterface`, you
must add a `clearOnceListeners(?string $event = null): void` method
as of 2.1.0. Reference implementation:

```php
public function clearOnceListeners($event = null)
{
    if ($event === null) {
        // drop every one-shot listener for every event
    } elseif (!is_string($event)) {
        throw new \InvalidArgumentException('$event must be a string or null.');
    } else {
        // drop one-shot listeners only for the named event
    }
}
```

The bundled `EventEmitter` of course implements it.

### Step 6 — upgrade

```bash
composer require initphp/events:^2.0
```

## Compatibility matrix

| Source state | Target | Code changes required |
| --- | --- | --- |
| Only `initphp/events:^1.0` | `initphp/events:^2.0` | Verify priority-ordering and `Event::on()` default-priority changes (steps 2–4 above). |
| Only `initphp/event-emitter:^1.0` | `initphp/events:^2.0` | Use the alias (no source changes). Verify the priority-ordering fix, and the `emit()` fix if you were on tag `1.0`. If you ship a custom `EventEmitterInterface` implementation, add `clearOnceListeners()`. |
| Both packages installed | `initphp/events:^2.0` only | `composer remove initphp/event-emitter` (or rely on `replace` — Composer will reject the combo). |
| Mix of `InitPHP\EventEmitter\*` and `InitPHP\Events\*` `use` statements | Same | Swap `use` statements when you touch each file; both work today. |

## Why the consolidation

The standalone `initphp/event-emitter` was a single-class library that
solved a problem already addressed by `initphp/events`. Maintaining two
packages with nearly identical surface meant two release lines, two
issue trackers, and double the change-log overhead — for a feature set
that fits comfortably in one package. Merging both into
`initphp/events:^2.0` keeps a single canonical source of truth and lets
the two APIs (the [facade](Events-Facade.md) and the
[emitter](EventEmitter.md)) share infrastructure cleanly.

## See also

- [Home](Home.md) — overview of the consolidated package.
- [`EventEmitter`](EventEmitter.md) — the API formerly known as
  `initphp/event-emitter`.
- [Troubleshooting](Troubleshooting.md) — what to do if listeners that
  worked under 1.x stop firing (or start firing).
