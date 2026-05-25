# Changelog

All notable changes to `initphp/events` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Breaking changes

- **`EventEmitter` now honours priority.** Listeners are dispatched in
  ascending numeric priority order regardless of the order in which
  they were registered. In 1.x they ran in registration order; the
  `ksort()` in `EventEmitter::listeners()` was applied to the wrong
  array level and effectively did nothing. Code that registered
  listeners in ascending priority order (the obvious style) sees no
  visible change. Code that relied on the old "registration order
  wins" behaviour now sees a different invocation order — that
  reliance was almost certainly unintentional, but it is a
  user-visible behaviour change.
- **`Event::on()` default priority changed** from
  `Event::PRIORITY_LOW` (200) to `Event::PRIORITY_NORMAL` (100).
  Calls that omitted the third argument now run earlier relative to
  listeners registered with an explicit `PRIORITY_LOW`. Pass an
  explicit priority to restore the old positioning.
- **`EventEmitterInterface` gained `clearOnceListeners(?string)`.**
  Anyone shipping their own implementation of this interface must add
  the method. The bundled `EventEmitter` of course implements it.
- **PHPUnit 9.6 requires PHP `>= 7.3`** for the dev environment.
  Runtime `require` is unchanged (`php: >=5.6`); CI lints the source
  on PHP 5.6 / 7.0 / 7.1 / 7.2 so the runtime contract is verified,
  but the unit-test suite only runs on PHP 7.3 and newer.

### Added

- **`Event::once($name, $callback, $priority)`** — register a one-shot
  listener via the high-level dispatcher (previously only available
  on `EventEmitter`).
- **`Event::off($name, $callback)`** — remove a specific listener
  (alias-style; forwards to `EventEmitter::removeListener()`).
- **`Event::removeAllListeners(?string $name)`** — wipe one event, or
  every event when called with no arguments.
- **`Event::clearDebug()`** — empty the debug log without dropping the
  dispatcher.
- **`Event::getEmitter()`** — expose the underlying `EventEmitter` for
  callers that need both high-level dispatch and low-level emit on the
  same listener registry.
- **`Events::reset()`** — drop the shared singleton so the next facade
  call rebuilds a fresh one. Intended for test setUp/tearDown and
  long-running processes that need a clean slate.
- **`Events::setInstance(Event $event)`** — inject a pre-configured
  dispatcher, e.g. one with simulate or debug already toggled on.
- **`Events::getInstance()` is now public** (was `protected`).
- **`EventEmitter::clearOnceListeners(?string $event)`** — drop
  one-shot listeners without invoking them. Used by the high-level
  `Event::trigger()` loop to keep the once-contract intact when the
  chain is stopped by a `false` return.

### Fixed

- **Once-listeners registered through `Event` (or `Events`) now fire
  at most once.** In 1.x, `Event::trigger()` pulled the listener list
  via `EventEmitter::listeners()` (which includes one-shot listeners)
  but never cleaned them up, so they fired on every trigger. This is
  now handled in a `try/finally` block, so the once contract is
  honoured even when a listener throws or when the chain is halted by
  a `false` return.
- **Typo / Turkish-only docblock on `Event::trigger()`** — replaced
  with English documentation consistent with the rest of the
  ecosystem.
- **`Event.php` license header** — was a different format and pointed
  at a non-existent license URL; aligned with the rest of the
  package.

### Internal / housekeeping

- Removed the empty `Event::__destruct()` and the defensive `isset()`
  checks it forced on the getters; properties are always initialised
  by the constructor.
- Removed redundant `(bool)` casts after the matching `is_bool()`
  guards in `setSimulate()` / `setDebugMode()`.
- Replaced `::class` arguments to `class_exists()` / `interface_exists()`
  in `src/aliases.php` with plain string literals — functionally
  equivalent, but removes the compile-time constant dependency.
- 59 unit tests, 99 assertions covering the priority contract,
  short-circuit semantics, simulate / debug modes, once + removal,
  fluent API, exception paths, the static facade lifecycle, and the
  backwards-compatibility alias for `\InitPHP\EventEmitter\*`.
- New CI workflow (`.github/workflows/ci.yml`):
  - PHP 7.3 / 7.4 / 8.0 / 8.1 / 8.2 / 8.3 / 8.4 — `composer install`
    + `phpunit`.
  - PHP 5.6 / 7.0 / 7.1 / 7.2 — `php -l` on every source file and a
    Composer-free autoload smoke test, to keep the
    `composer.json: php >= 5.6` contract honest.
- `composer.json` now declares `autoload-dev` for the test suite,
  `keywords`, `support` URLs, a `scripts.test` entry, and
  `config.sort-packages`. Runtime `require` is unchanged.

## [1.0.2]

### Added

- Bundled the low-level `EventEmitter` primitive previously distributed
  as the separate
  [`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter)
  package, which is now deprecated. A class alias keeps the legacy
  `\InitPHP\EventEmitter\*` fully-qualified names working.
- Minimum PHP requirement set to 5.6.

### Notes

- The standalone `initphp/event-emitter` package was retired; this
  package declares a Composer `replace` for it.

## [Earlier]

Pre-1.0.2 history was not maintained in a `CHANGELOG.md`. Refer to the
Git log for individual fix commits (`git log src/Event.php`,
`git log src/Events.php`).

[Unreleased]: https://github.com/InitPHP/Events/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/InitPHP/Events/releases/tag/v1.0.2
