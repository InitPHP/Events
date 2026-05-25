<?php

/**
 * EventEmitterTest.php
 *
 * This file is part of InitPHP Events.
 *
 * @license MIT
 */

namespace InitPHP\Events\Tests;

use InitPHP\Events\EventEmitter;
use InitPHP\Events\EventEmitterInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventEmitterTest extends TestCase
{
    /** @var EventEmitter */
    private $emitter;

    protected function setUp(): void
    {
        $this->emitter = new EventEmitter();
    }

    public function test_it_implements_the_event_emitter_interface(): void
    {
        $this->assertInstanceOf(EventEmitterInterface::class, $this->emitter);
    }

    public function test_on_returns_self_for_fluent_chaining(): void
    {
        $returned = $this->emitter->on('evt', function (): void {});

        $this->assertSame($this->emitter, $returned);
    }

    public function test_once_returns_self_for_fluent_chaining(): void
    {
        $returned = $this->emitter->once('evt', function (): void {});

        $this->assertSame($this->emitter, $returned);
    }

    public function test_emit_invokes_each_listener_with_the_given_arguments(): void
    {
        $received = [];

        $this->emitter->on('greet', function ($name, $greeting) use (&$received): void {
            $received[] = $greeting . ' ' . $name;
        });

        $this->emitter->emit('greet', ['World', 'Hello']);

        $this->assertSame(['Hello World'], $received);
    }

    public function test_emit_with_no_listeners_is_a_silent_noop(): void
    {
        $this->emitter->emit('no.subscribers');
        $this->addToAssertionCount(1);
    }

    /**
     * Regression test for the priority bug present in 1.x.
     *
     * The documented contract is: a listener registered with a *numerically
     * lower* priority runs before a listener registered with a higher one,
     * regardless of registration order. Prior to the fix in 2.0,
     * EventEmitter::listeners() only ksort()ed the innermost (already
     * numerically-indexed) listener array, leaving the priority map sorted
     * by insertion order instead — so a listener with priority 99 added
     * *after* a listener with priority 100 would still run second.
     */
    public function test_listeners_run_in_ascending_priority_order_regardless_of_registration_order(): void
    {
        $order = [];

        $this->emitter->on('boot', function () use (&$order): void {
            $order[] = 'priority-100';
        }, 100);

        $this->emitter->on('boot', function () use (&$order): void {
            $order[] = 'priority-99';
        }, 99);

        $this->emitter->on('boot', function () use (&$order): void {
            $order[] = 'priority-10';
        }, 10);

        $this->emitter->emit('boot');

        $this->assertSame(
            ['priority-10', 'priority-99', 'priority-100'],
            $order,
            'Listeners must execute in ascending priority order regardless of the order in which they were registered.'
        );
    }

    public function test_listeners_at_the_same_priority_run_in_registration_order_fifo(): void
    {
        $order = [];

        $this->emitter->on('e', function () use (&$order): void { $order[] = 'first'; }, 50);
        $this->emitter->on('e', function () use (&$order): void { $order[] = 'second'; }, 50);
        $this->emitter->on('e', function () use (&$order): void { $order[] = 'third'; }, 50);

        $this->emitter->emit('e');

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function test_once_listeners_are_invoked_only_on_the_first_emit(): void
    {
        $count = 0;

        $this->emitter->once('tick', function () use (&$count): void {
            $count++;
        });

        $this->emitter->emit('tick');
        $this->emitter->emit('tick');
        $this->emitter->emit('tick');

        $this->assertSame(1, $count);
    }

    public function test_event_names_are_compared_case_insensitively(): void
    {
        $hits = 0;
        $this->emitter->on('User.Created', function () use (&$hits): void {
            $hits++;
        });

        $this->emitter->emit('user.created');
        $this->emitter->emit('USER.CREATED');

        $this->assertSame(2, $hits);
    }

    public function test_listeners_returns_all_registered_callbacks_for_an_event(): void
    {
        $a = function (): void {};
        $b = function (): void {};

        $this->emitter->on('e', $a, 50);
        $this->emitter->once('e', $b, 10);

        $listeners = $this->emitter->listeners('e');

        $this->assertCount(2, $listeners);
        $this->assertSame($b, $listeners[0], 'lower-priority listener should appear first');
        $this->assertSame($a, $listeners[1]);
    }

    public function test_listeners_with_no_argument_returns_listeners_across_all_events(): void
    {
        $this->emitter->on('a', function (): void {});
        $this->emitter->on('b', function (): void {});
        $this->emitter->once('c', function (): void {});

        $this->assertCount(3, $this->emitter->listeners());
    }

    public function test_remove_listener_drops_only_the_targeted_callback(): void
    {
        $kept = function (): void {};
        $removed = function (): void {};

        $this->emitter->on('e', $kept, 10);
        $this->emitter->on('e', $removed, 10);

        $this->emitter->removeListener('e', $removed);

        $listeners = $this->emitter->listeners('e');
        $this->assertCount(1, $listeners);
        $this->assertSame($kept, $listeners[0]);
    }

    public function test_remove_all_listeners_for_a_single_event_clears_only_that_event(): void
    {
        $this->emitter->on('a', function (): void {});
        $this->emitter->on('b', function (): void {});

        $this->emitter->removeAllListeners('a');

        $this->assertSame([], $this->emitter->listeners('a'));
        $this->assertCount(1, $this->emitter->listeners('b'));
    }

    public function test_remove_all_listeners_with_no_argument_clears_every_event(): void
    {
        $this->emitter->on('a', function (): void {});
        $this->emitter->once('b', function (): void {});

        $this->emitter->removeAllListeners();

        $this->assertSame([], $this->emitter->listeners());
    }

    public function test_on_rejects_non_string_event_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->on(123, function (): void {});
    }

    public function test_on_rejects_non_callable_listeners(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->on('e', 'this-is-not-callable-anywhere');
    }

    public function test_on_rejects_non_integer_priority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->on('e', function (): void {}, '100');
    }

    public function test_emit_rejects_non_string_event_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->emit(123);
    }

    public function test_emit_rejects_non_array_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->emit('e', 'not-an-array');
    }

    public function test_remove_listener_rejects_non_string_event(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->removeListener(42, function (): void {});
    }

    public function test_remove_listener_rejects_non_callable_listener(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->removeListener('e', 'definitely-not-a-callable-anywhere');
    }

    public function test_remove_listener_is_a_silent_noop_when_the_listener_is_not_registered(): void
    {
        $registered = function (): void {};
        $stranger = function (): void {};

        // Hit both branches: removeListener walks BOTH the regular and
        // once registries and, in each one, skips priority buckets
        // where the listener is not found.
        $this->emitter->on('e', $registered, 10);
        $this->emitter->once('e', $registered, 20);

        $this->emitter->removeListener('e', $stranger);

        // The actually-registered listener must still be there.
        $this->assertCount(2, $this->emitter->listeners('e'));
    }

    public function test_remove_all_listeners_rejects_non_string_non_null_event(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->removeAllListeners(42);
    }

    public function test_listeners_rejects_non_string_non_null_event(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->listeners(42);
    }

    public function test_clear_once_listeners_for_a_specific_event_drops_only_that_events_once_listeners(): void
    {
        $aFires = 0;
        $bFires = 0;

        $this->emitter->once('a', function () use (&$aFires): void { $aFires++; });
        $this->emitter->once('b', function () use (&$bFires): void { $bFires++; });

        $this->emitter->clearOnceListeners('a');

        $this->emitter->emit('a');
        $this->emitter->emit('b');

        $this->assertSame(0, $aFires, 'once-listener for "a" must have been dropped without firing.');
        $this->assertSame(1, $bFires, 'once-listener for "b" must still fire.');
    }

    public function test_clear_once_listeners_with_no_argument_drops_every_one_shot_listener(): void
    {
        $fired = 0;

        $this->emitter->once('a', function () use (&$fired): void { $fired++; });
        $this->emitter->once('b', function () use (&$fired): void { $fired++; });
        // Regular listener must NOT be touched.
        $this->emitter->on('c', function () use (&$fired): void { $fired++; });

        $this->emitter->clearOnceListeners();

        $this->emitter->emit('a');
        $this->emitter->emit('b');
        $this->emitter->emit('c');

        $this->assertSame(1, $fired, 'Only the regular listener for "c" must have fired.');
    }

    public function test_clear_once_listeners_rejects_non_string_non_null_event(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->emitter->clearOnceListeners(42);
    }

    public function test_clear_once_listeners_for_an_unknown_event_is_a_silent_noop(): void
    {
        $this->emitter->clearOnceListeners('never.registered');
        $this->addToAssertionCount(1);
    }
}
