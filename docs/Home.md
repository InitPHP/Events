# InitPHP Events — Wiki

Welcome to the official documentation for **`initphp/events`** — a tiny,
zero-dependency event/hook library for PHP 8.2+.

It gives you two complementary APIs over the same core:

| API | Use it when… |
| --- | --- |
| [`Events` facade](Events-Facade.md) | You want a single, global hook point — register listeners from anywhere with `Events::on(...)`, fire them with `Events::trigger(...)`. WordPress-style hooks. |
| [`EventEmitter`](EventEmitter.md) | You want a plain object you can `new`, inject, and pass around — node-style `on`/`once`/`emit`. |

```bash
composer require initphp/events
```

```php
use InitPHP\Events\Events;

Events::on('user.registered', function ($user) {
    mail($user['email'], 'Welcome', 'Thanks for signing up!');
});

Events::trigger('user.registered', ['email' => 'jane@example.com']);
```

That is the whole programming model. The rest of the wiki documents the
details — argument passing, propagation control, once-listeners, simulate &
debug modes, and the migration path from the deprecated
[`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter) package.

## Start here

- **New to the package?** Read [Installation](Installation.md), then
  [Quick Start](Quick-Start.md).
- **Picking an API?** Compare the [`Events` facade](Events-Facade.md) and the
  [low-level `EventEmitter`](EventEmitter.md).
- **Coming from `initphp/event-emitter`?** See the
  [Migration Guide](Migration-Guide.md).
- **Want the contract?** The complete [API Reference](API-Reference.md) lists
  every method, constant, and exception.

## Navigation

### Getting Started
- [Installation](Installation.md)
- [Quick Start](Quick-Start.md)

### Core APIs
- [`Events` facade](Events-Facade.md)
- [`EventEmitter`](EventEmitter.md)
- [Listeners & Priorities](Listeners-and-Priorities.md)
- [Once-Listeners & Removal](Once-and-Removal.md)
- [Stopping Propagation](Stopping-Propagation.md)
- [Simulate & Debug Mode](Simulate-and-Debug.md)

### Practical
- [Recipes](Recipes.md)
- [Troubleshooting](Troubleshooting.md)
- [FAQ](FAQ.md)

### Reference
- [API Reference](API-Reference.md)
- [Migration Guide](Migration-Guide.md)

## At a glance — `Events` facade vs `EventEmitter`

| Capability                            | `Events` facade | `EventEmitter` |
| ------------------------------------- | :-------------: | :------------: |
| Static / global                       |       ✅        |       —        |
| Instantiable object                   |       —        |       ✅       |
| Register listener (`on`)              |       ✅        |       ✅       |
| One-shot listener (`once`)            |       ✅        |       ✅       |
| Fire (`trigger` / `emit`)             |       ✅        |       ✅       |
| Variadic arguments                    |    ✅ (varargs) | ✅ (array)     |
| Stop propagation by returning `false` |       ✅        |       —        |
| Remove a single listener (`off`)      |       ✅        |       ✅       |
| Remove all listeners                  |       ✅        |       ✅       |
| Priority-ordered dispatch             |       ✅        |       ✅       |
| Simulate mode (skip execution)        |       ✅        |       —        |
| Debug timing                          |       ✅        |       —        |
| Reset singleton between tests/jobs    |       ✅        |       n/a      |
| Implements `EventEmitterInterface`    |       —        |       ✅       |

Pick the facade when you want a single, app-wide hook registry. Pick the
emitter when you want a plain dependency you can hand to a service.

> **What changed in 2.x?** 2.0.0 merged the standalone `initphp/event-emitter`
> package into this one (bundled `EventEmitter`, `EventEmitterInterface`,
> backwards-compatibility aliases). 2.1.0 made priorities actually honoured
> (earlier releases dispatched in registration order), made one-shot listeners
> fire at most once through the facade, and gave the high-level dispatcher
> `once()`, `off()`, `removeAllListeners()`, plus a `reset()` test hook on the
> facade. Full list: [Migration Guide](Migration-Guide.md).

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2
- **Packagist**: [`initphp/events`](https://packagist.org/packages/initphp/events)
- **Source**: [github.com/InitPHP/Events](https://github.com/InitPHP/Events)
- **Issues**: [github.com/InitPHP/Events/issues](https://github.com/InitPHP/Events/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)
- **Replaces**: [`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter) (deprecated, see [Migration Guide](Migration-Guide.md))

If something in this wiki is unclear, ambiguous, or wrong, please
[open an issue](https://github.com/InitPHP/Events/issues/new) — documentation
fixes are reviewed eagerly.
