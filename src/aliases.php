<?php
/**
 * aliases.php
 *
 * Backwards-compatibility shim for users migrating from the deprecated
 * `initphp/event-emitter` package. Aliases the old namespace
 * `InitPHP\EventEmitter\*` to the canonical classes that now live in this
 * package.
 *
 * Existing code using `use InitPHP\EventEmitter\EventEmitter;` continues to
 * work unchanged after switching to `initphp/events:^2.0`. The class_exists
 * guard with autoload disabled prevents fatal "cannot declare class" errors
 * if the legacy package is somehow still loaded alongside this one.
 *
 * @see https://github.com/InitPHP/Events#migrating-from-initphpevent-emitter
 */

if (!class_exists(\InitPHP\EventEmitter\EventEmitter::class, false)) {
    class_alias(
        \InitPHP\Events\EventEmitter::class,
        'InitPHP\\EventEmitter\\EventEmitter'
    );
}

if (!interface_exists(\InitPHP\EventEmitter\EventEmitterInterface::class, false)) {
    class_alias(
        \InitPHP\Events\EventEmitterInterface::class,
        'InitPHP\\EventEmitter\\EventEmitterInterface'
    );
}
