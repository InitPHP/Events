# Installation

`initphp/events` is distributed exclusively through Composer.

## Requirements

| Requirement | Notes |
| --- | --- |
| PHP `^8.2` | Required by the package (`composer.json`). The library is plain PHP and uses only built-in functions such as `microtime`, `call_user_func_array` and `array_*`. |
| Composer | Any reasonably recent version. |

No PHP extensions, no third-party dependencies, no autoloading magic beyond
PSR-4.

## Install

```bash
composer require initphp/events
```

That single command:

1. Adds the package to your `composer.json`.
2. Registers the PSR-4 namespace `InitPHP\Events\` against `vendor/initphp/events/src/`.
3. Autoloads `src/aliases.php` so legacy `InitPHP\EventEmitter\*` class
   names continue to resolve. See the [Migration Guide](Migration-Guide.md)
   for the full story.

Composer also honours the `replace` declaration in this package — it will
**not** install the deprecated `initphp/event-emitter` alongside it, even if
something in your dependency tree still asks for it.

## Verify the install

A one-line smoke test:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

\InitPHP\Events\Events::on('boot', function () { echo "ok\n"; });
\InitPHP\Events\Events::trigger('boot');
```

```text
$ php smoke.php
ok
```

If you see `ok`, the autoloader resolved the classes and the package is
ready to use. Continue to [Quick Start](Quick-Start.md).

## Upgrading

The library follows [SemVer](https://semver.org/). Major versions can
introduce breaking changes (see the [Migration Guide](Migration-Guide.md) for
the 1.x → 2.x notes and the PHP 8.2 requirement). Minor and patch releases keep
the public surface backwards-compatible.

```bash
composer update initphp/events
```

## Composer constraint examples

```json
{
    "require": {
        "initphp/events": "^3.0"
    }
}
```

If you are on a PHP version older than 8.2, the newest release that supports it
is 2.1.0 (its `composer.json` requires PHP `>=5.6`); 3.0.0 raises the
minimum to PHP 8.2 and is a breaking change (see the
[Changelog](https://github.com/InitPHP/Events/blob/main/CHANGELOG.md)). Lock to
the 2.1 line with:

```json
{
    "require": {
        "initphp/events": "^2.1"
    }
}
```

The 1.x line (`^1.0`) lacks the bundled low-level [`EventEmitter`](EventEmitter.md)
and the priority fixes described in the [Migration Guide](Migration-Guide.md).

## Uninstall

```bash
composer remove initphp/events
```
