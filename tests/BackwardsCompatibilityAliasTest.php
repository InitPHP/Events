<?php

/**
 * BackwardsCompatibilityAliasTest.php
 *
 * This file is part of InitPHP Events.
 *
 * Verifies that the BC shim in src/aliases.php keeps the legacy
 * \InitPHP\EventEmitter\* fully-qualified class names usable, so that
 * users migrating from the deprecated initphp/event-emitter package do
 * not have to touch their source code.
 *
 * @license MIT
 */

namespace InitPHP\Events\Tests;

use InitPHP\Events\EventEmitter;
use InitPHP\Events\EventEmitterInterface;
use PHPUnit\Framework\TestCase;

final class BackwardsCompatibilityAliasTest extends TestCase
{
    public function test_legacy_event_emitter_class_alias_is_registered(): void
    {
        $this->assertTrue(
            class_exists('InitPHP\\EventEmitter\\EventEmitter'),
            'The legacy class \\InitPHP\\EventEmitter\\EventEmitter must remain available via the BC alias.'
        );
    }

    public function test_legacy_event_emitter_interface_alias_is_registered(): void
    {
        $this->assertTrue(
            interface_exists('InitPHP\\EventEmitter\\EventEmitterInterface'),
            'The legacy interface \\InitPHP\\EventEmitter\\EventEmitterInterface must remain available via the BC alias.'
        );
    }

    public function test_legacy_class_resolves_to_the_canonical_implementation(): void
    {
        $legacy = 'InitPHP\\EventEmitter\\EventEmitter';
        $instance = new $legacy();

        $this->assertInstanceOf(EventEmitter::class, $instance);
        $this->assertInstanceOf(EventEmitterInterface::class, $instance);
    }

    public function test_instance_created_via_legacy_name_behaves_identically(): void
    {
        $legacy = 'InitPHP\\EventEmitter\\EventEmitter';
        /** @var EventEmitter $emitter */
        $emitter = new $legacy();

        $received = null;
        $emitter->on('msg', function ($payload) use (&$received): void {
            $received = $payload;
        });

        $emitter->emit('msg', ['ok']);

        $this->assertSame('ok', $received);
    }
}
