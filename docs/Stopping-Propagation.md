# Stopping Propagation

The [`Events` facade](Events-Facade.md) lets any listener veto the rest of
the chain by returning **strict `false`**. This page documents the exact
contract, the patterns it enables, and the (deliberate) lack of an
equivalent on `EventEmitter::emit()`.

## The contract — `Events::trigger()`

`Events::trigger($name, ...$args)` walks its listener list in
**priority order** (lower numeric priority first; FIFO within a
priority bucket — see [Listeners & Priorities](Listeners-and-Priorities.md)).
After each call it inspects the return value:

- If the listener returned `false` (strict comparison, `=== false`),
  iteration **stops immediately** and `trigger()` returns `false`.
- Otherwise the next listener runs.
- If all listeners ran without anyone returning `false`, `trigger()`
  returns `true`. The same `true` is returned when no listeners are
  registered at all.

```php
use InitPHP\Events\Events;

Events::on('publish', function (array $post) {
    return $post['status'] === 'ready'; // false ⇒ veto, true ⇒ continue
});

Events::on('publish', function (array $post) {
    /* indexer */
});

Events::on('publish', function (array $post) {
    /* notifier */
});

if (Events::trigger('publish', $post) === false) {
    http_response_code(409);
    echo "Refused: post not ready.\n";
}
```

The veto is the listener's say in whether the chain continues. There is
no mechanism for the trigger site to override it.

## What counts as "false"

Only **strict** `false`. Any other falsy value lets the chain continue:

| Returned value | Stops the chain? |
| --- | --- |
| `false` | ✅ yes |
| `null` | ❌ no |
| `''` (empty string) | ❌ no |
| `0` / `0.0` | ❌ no |
| `[]` (empty array) | ❌ no |
| anything else | ❌ no |

```php
Events::on('x', fn() => 0);     // continues
Events::on('x', fn() => null);  // continues
Events::on('x', fn() => false); // STOPS
```

Be deliberate: if a listener forgets to `return` and the function falls
off the end (`null`), the chain keeps going. That is almost always what
you want — listeners should not accidentally veto.

## `EventEmitter::emit()` does **not** support this

`EventEmitter::emit()` returns `void` and ignores listener return values.
Every registered listener runs unconditionally, even if an earlier one
returned `false`:

```php
use InitPHP\Events\EventEmitter;

$bus = new EventEmitter();
$bus->on('save', fn() => false);
$bus->on('save', fn() => print("still runs\n"));

$bus->emit('save'); // → still runs
```

If you need veto semantics with the low-level API, use a **shared state
object** that listeners can mutate:

```php
$ctx = (object) ['vetoed' => false];

$bus->on('save', function ($payload, $ctx) {
    if (!$payload['ok']) {
        $ctx->vetoed = true;
    }
});
$bus->on('save', function ($payload, $ctx) {
    if ($ctx->vetoed) {
        return; // skip work
    }
    /* expensive write */
});

$bus->emit('save', [$payload, $ctx]);
if ($ctx->vetoed) {
    /* react */
}
```

Or just use the [`Events` facade](Events-Facade.md), which has the veto
contract built in.

## Patterns

### 1. Validation-style veto

A guard listener inspects the payload and short-circuits if it is
invalid:

```php
Events::on('user.delete', function (int $id) {
    if (isProtected($id)) {
        return false; // never delete protected users
    }
});

Events::on('user.delete', $deleteFn);
Events::on('user.delete', $auditFn);

if (Events::trigger('user.delete', $id) === false) {
    throw new RuntimeException('Deletion blocked.');
}
```

### 2. Cache hit short-circuit

The first listener tries the cache; subsequent listeners (DB read,
deserialisation, …) only run on miss:

```php
$result = null;

Events::on('lookup', function ($key) use (&$result) {
    if ($hit = Cache::get($key)) {
        $result = $hit;
        return false; // skip DB
    }
});

Events::on('lookup', function ($key) use (&$result) {
    $result = Db::find($key);
});

Events::trigger('lookup', 'user:42');
```

### 3. Conditional pipeline step

A pipeline of independent steps where any step may decide the request is
already handled:

```php
Events::on('webhook', fn ($req) => handleStripe($req)   ?: null); // ?: null avoids accidental veto
Events::on('webhook', fn ($req) => handleGithub($req)   ?: null);
Events::on('webhook', fn ($req) => handleDefault($req)  ?: null);
```

Each handler returns truthy/null in the normal case and explicit
`false` when it has consumed the request and wants the pipeline to stop.

## Pitfalls

- **Forgetting strict equality.** The library uses `=== FALSE`. If your
  listener returns `0` or `null` it does **not** veto. This is by
  design.
- **`return` in a closure body.** PHP closures without an explicit
  `return` produce `null` — safe. But if you write
  `fn() => doX()`, the arrow returns whatever `doX()` returned. If
  `doX()` can return `false`, your closure can accidentally veto.
- **No way to "resume".** Once a listener returns `false`, that
  `trigger()` call is done. There is no exception to catch, no buffer
  to drain. If you need to know which listener vetoed, do it from
  inside the listener (log, set a flag, etc.) before returning.
- **`once()` listeners are still cleaned up.** If the chain halts at
  a listener earlier than a queued `once()` listener, the once-listener
  is *still dropped* after the trigger — the contract is "fire at most
  once", not "fire exactly once". `Event::trigger()` does this cleanup
  in a `try/finally` block, so it happens on exceptions too.

## See also

- [`Events` facade](Events-Facade.md#eventstriggerstring-name-arguments-bool)
- [Listeners & Priorities](Listeners-and-Priorities.md#return-values)
- [Recipes](Recipes.md) — more end-to-end patterns.
