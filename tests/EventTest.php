<?php

/**
 * EventTest.php
 *
 * This file is part of InitPHP Events.
 *
 * @license MIT
 */

namespace InitPHP\Events\Tests;

use InitPHP\Events\Event;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    /** @var Event */
    private $event;

    protected function setUp(): void
    {
        $this->event = new Event();
    }

    public function test_priority_constants_match_documented_ordering(): void
    {
        $this->assertLessThan(Event::PRIORITY_NORMAL, Event::PRIORITY_HIGH);
        $this->assertLessThan(Event::PRIORITY_LOW, Event::PRIORITY_NORMAL);
    }

    public function test_trigger_runs_listeners_in_priority_order(): void
    {
        $order = [];

        $this->event->on('boot', function () use (&$order): void {
            $order[] = 'low';
        }, Event::PRIORITY_LOW);

        $this->event->on('boot', function () use (&$order): void {
            $order[] = 'high';
        }, Event::PRIORITY_HIGH);

        $this->event->on('boot', function () use (&$order): void {
            $order[] = 'normal';
        }, Event::PRIORITY_NORMAL);

        $this->event->trigger('boot');

        $this->assertSame(['high', 'normal', 'low'], $order);
    }

    public function test_trigger_forwards_variadic_arguments_to_listeners(): void
    {
        $captured = null;

        $this->event->on('greet', function ($name, $greeting) use (&$captured): void {
            $captured = $greeting . ' ' . $name;
        });

        $this->event->trigger('greet', 'World', 'Hello');

        $this->assertSame('Hello World', $captured);
    }

    public function test_trigger_returns_true_when_no_listener_short_circuits(): void
    {
        $this->event->on('e', function () { return true; });
        $this->event->on('e', function () { /* implicit null */ });

        $this->assertTrue($this->event->trigger('e'));
    }

    public function test_trigger_returns_false_and_stops_chain_when_a_listener_returns_false(): void
    {
        $invocations = [];

        $this->event->on('chain', function () use (&$invocations) {
            $invocations[] = 'first';
            return true;
        }, 10);

        $this->event->on('chain', function () use (&$invocations) {
            $invocations[] = 'second';
            return false;
        }, 20);

        $this->event->on('chain', function () use (&$invocations) {
            $invocations[] = 'third';
            return true;
        }, 30);

        $this->assertFalse($this->event->trigger('chain'));
        $this->assertSame(['first', 'second'], $invocations);
    }

    public function test_simulate_mode_skips_listener_invocation_and_returns_true(): void
    {
        $called = false;

        $this->event->setSimulate(true);
        $this->event->on('e', function () use (&$called) {
            $called = true;
            return false;
        });

        $this->assertTrue($this->event->trigger('e'));
        $this->assertFalse($called, 'Listener must not be called in simulate mode.');
    }

    public function test_simulate_mode_round_trips_through_getter(): void
    {
        $this->assertFalse($this->event->getSimulate());

        $this->event->setSimulate(true);
        $this->assertTrue($this->event->getSimulate());

        $this->event->setSimulate(false);
        $this->assertFalse($this->event->getSimulate());
    }

    public function test_set_simulate_rejects_non_boolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event->setSimulate('yes');
    }

    public function test_debug_mode_records_each_trigger_invocation(): void
    {
        $this->event->setDebugMode(true);
        $this->event->on('measured', function (): void {});

        $this->event->trigger('measured');
        $this->event->trigger('measured');

        $debug = $this->event->getDebug();
        $this->assertCount(2, $debug);

        foreach ($debug as $entry) {
            $this->assertSame('measured', $entry['event']);
            $this->assertArrayHasKey('start', $entry);
            $this->assertArrayHasKey('end', $entry);
            $this->assertGreaterThanOrEqual($entry['start'], $entry['end']);
        }
    }

    public function test_debug_mode_disabled_by_default_collects_nothing(): void
    {
        $this->event->on('quiet', function (): void {});
        $this->event->trigger('quiet');

        $this->assertSame([], $this->event->getDebug());
    }

    public function test_debug_mode_round_trips_through_getter(): void
    {
        $this->assertFalse($this->event->getDebugMode());

        $this->event->setDebugMode(true);
        $this->assertTrue($this->event->getDebugMode());
    }

    public function test_set_debug_mode_rejects_non_boolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event->setDebugMode(1);
    }

    public function test_trigger_rejects_non_string_event_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->event->trigger(42);
    }

    public function test_set_simulate_and_set_debug_mode_are_fluent(): void
    {
        $this->assertSame($this->event, $this->event->setSimulate(false));
        $this->assertSame($this->event, $this->event->setDebugMode(false));
    }

    public function test_on_off_once_remove_all_listeners_are_fluent(): void
    {
        $cb = function (): void {};

        $this->assertSame($this->event, $this->event->on('e', $cb));
        $this->assertSame($this->event, $this->event->once('e', $cb));
        $this->assertSame($this->event, $this->event->off('e', $cb));
        $this->assertSame($this->event, $this->event->removeAllListeners('e'));
        $this->assertSame($this->event, $this->event->removeAllListeners());
    }

    public function test_default_priority_is_normal(): void
    {
        $order = [];

        $this->event->on('boot', function () use (&$order): void { $order[] = 'default'; });
        $this->event->on('boot', function () use (&$order): void { $order[] = 'high'; }, Event::PRIORITY_HIGH);
        $this->event->on('boot', function () use (&$order): void { $order[] = 'low'; }, Event::PRIORITY_LOW);

        $this->event->trigger('boot');

        $this->assertSame(['high', 'default', 'low'], $order);
    }

    /**
     * Regression test for the once-leak bug present in 1.x: Event::trigger()
     * fetched listeners via emitter->listeners() (which includes one-shot
     * listeners) but never cleaned them up, so once() listeners registered
     * via Event ended up firing on every trigger.
     */
    public function test_once_listener_is_dropped_after_first_trigger(): void
    {
        $calls = 0;

        $this->event->once('tick', function () use (&$calls): void {
            $calls++;
        });

        $this->event->trigger('tick');
        $this->event->trigger('tick');
        $this->event->trigger('tick');

        $this->assertSame(1, $calls);
    }

    public function test_once_contract_is_honoured_even_when_chain_is_stopped_by_false(): void
    {
        $onceCalls = 0;

        $this->event->once('halt', function () use (&$onceCalls) {
            $onceCalls++;
            return false;
        }, Event::PRIORITY_HIGH);

        $this->event->trigger('halt');
        $this->event->trigger('halt');

        $this->assertSame(1, $onceCalls, 'once() listener must not be re-armed when the chain is halted.');
    }

    public function test_off_removes_a_specific_listener(): void
    {
        $hits = [];
        $a = function () use (&$hits): void { $hits[] = 'a'; };
        $b = function () use (&$hits): void { $hits[] = 'b'; };

        $this->event->on('e', $a)->on('e', $b)->off('e', $a)->trigger('e');

        $this->assertSame(['b'], $hits);
    }

    public function test_remove_all_listeners_for_event_clears_that_event_only(): void
    {
        $aHits = 0;
        $bHits = 0;

        $this->event
            ->on('a', function () use (&$aHits): void { $aHits++; })
            ->on('b', function () use (&$bHits): void { $bHits++; });

        $this->event->removeAllListeners('a');

        $this->event->trigger('a');
        $this->event->trigger('b');

        $this->assertSame(0, $aHits);
        $this->assertSame(1, $bHits);
    }

    public function test_clear_debug_empties_the_collected_log(): void
    {
        $this->event->setDebugMode(true);
        $this->event->on('e', function (): void {});
        $this->event->trigger('e');

        $this->assertNotEmpty($this->event->getDebug());

        $this->assertSame($this->event, $this->event->clearDebug());
        $this->assertSame([], $this->event->getDebug());
    }

    public function test_get_emitter_returns_the_backing_event_emitter(): void
    {
        $this->assertInstanceOf(\InitPHP\Events\EventEmitter::class, $this->event->getEmitter());
    }

    public function test_debug_info_exposes_simulate_debug_and_collected_data(): void
    {
        $this->event->setSimulate(true)->setDebugMode(true);

        $info = (new \ReflectionMethod($this->event, '__debugInfo'))->invoke($this->event);

        $this->assertArrayHasKey('simulate', $info);
        $this->assertArrayHasKey('debugMode', $info);
        $this->assertArrayHasKey('debugData', $info);
        $this->assertTrue($info['simulate']);
        $this->assertTrue($info['debugMode']);
    }
}
