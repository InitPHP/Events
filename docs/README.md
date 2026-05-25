# InitPHP Events — Documentation

This is the long-form documentation for
[`initphp/events`](https://github.com/InitPHP/Events). The package
README covers the same material at a glance; these chapters go into
the details.

If you are new to the package, read in order:

1. [Getting started](01-getting-started.md) — install it, run the
   smallest possible example, learn the three classes you will touch.
2. [The `Events` facade](02-events-facade.md) — the static
   application-wide dispatcher, when to use it, when not to.
3. [Using `EventEmitter` directly](03-event-emitter.md) — the
   low-level primitive: instantiate, `on` / `once` / `emit`, no
   global state.
4. [Priorities and ordering](04-priorities-and-ordering.md) — the
   full contract for what runs when, including how `once()` interacts
   with the priority queue and the case-insensitive event-name rule.
5. [Once-listeners, removal, and cleanup](05-once-and-removal.md) —
   `once`, `off`, `removeAllListeners`, `clearOnceListeners`, and the
   guarantees each one gives you.
6. [Debug and simulate modes](06-debug-and-simulate.md) — opt-in
   instrumentation for dry-runs and timing measurements.
7. [Migrating from `initphp/event-emitter`](07-migration-from-event-emitter.md)
   — the BC alias, the `emit()` bug fix, the v2.0 priority-sort fix,
   what to change and what to leave alone.
8. [Recipes](08-recipes.md) — concrete patterns: plugin systems,
   request lifecycle hooks, WordPress-style action / filter hooks,
   testing strategies.
9. [API reference](09-api-reference.md) — every public method, every
   exception, every constant.

Spotted a gap or something that doesn't match what the code actually
does? Please [open an issue](https://github.com/InitPHP/Events/issues)
— the documentation tries to match the code character for character,
and divergence is a bug we want to hear about.
