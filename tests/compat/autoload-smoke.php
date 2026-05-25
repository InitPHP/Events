<?php

/**
 * Composer-free autoload smoke test for the `lowest-php-syntax` CI job.
 *
 * Runs on PHP 5.6 / 7.0 / 7.1 / 7.2 — versions where Composer 2.x will
 * not install at all (its minimum is PHP 7.2.5), so we cannot lean on
 * `vendor/autoload.php`. This script registers a tiny PSR-4 autoloader
 * by hand, requires the BC-alias bootstrap file, exercises the smallest
 * possible happy path on `EventEmitter`, and verifies the legacy
 * `InitPHP\EventEmitter\*` class alias resolves.
 *
 * It exists as a real file (rather than a `php -r '...'` inline script
 * in the workflow YAML) so `__DIR__` resolves to this file's directory
 * on every supported PHP version — `php -r` left `__DIR__` undefined on
 * PHP 5.6 / 7.0 / 7.1 / 7.2, which broke the autoloader and the BC
 * alias check.
 *
 * Run from anywhere:
 *     php tests/compat/autoload-smoke.php
 *
 * Exit code is 0 on success, non-zero on the first failed assertion.
 */

$repoRoot = dirname(dirname(__DIR__));   // tests/compat → tests → repo root
$srcDir = $repoRoot . '/src';

spl_autoload_register(function ($class) use ($srcDir) {
    $prefix = 'InitPHP\\Events\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $srcDir . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require $srcDir . '/aliases.php';

// Happy-path on the canonical namespace.
$emitter = new InitPHP\Events\EventEmitter();
$hit = false;
$emitter->on('ping', function () use (&$hit) {
    $hit = true;
});
$emitter->emit('ping');

if (!$hit) {
    fwrite(STDERR, "smoke test failed: listener was not invoked\n");
    exit(1);
}

// Backwards-compatibility alias is registered and usable.
if (!class_exists('InitPHP\\EventEmitter\\EventEmitter')) {
    fwrite(STDERR, "smoke test failed: BC alias \\InitPHP\\EventEmitter\\EventEmitter is not registered\n");
    exit(1);
}

if (!interface_exists('InitPHP\\EventEmitter\\EventEmitterInterface')) {
    fwrite(STDERR, "smoke test failed: BC alias \\InitPHP\\EventEmitter\\EventEmitterInterface is not registered\n");
    exit(1);
}

// Instance built through the legacy class name must behave like the
// canonical one.
$legacy = 'InitPHP\\EventEmitter\\EventEmitter';
$legacyInstance = new $legacy();
if (!($legacyInstance instanceof InitPHP\Events\EventEmitter)) {
    fwrite(STDERR, "smoke test failed: legacy alias does not resolve to the canonical class\n");
    exit(1);
}

echo "smoke ok\n";
