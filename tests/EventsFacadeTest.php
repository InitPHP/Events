<?php

/**
 * EventsFacadeTest.php
 *
 * This file is part of InitPHP Events.
 *
 * @license MIT
 */

namespace InitPHP\Events\Tests;

use InitPHP\Events\Event;
use InitPHP\Events\Events;
use PHPUnit\Framework\TestCase;

/**
 * Events is a static facade backed by a lazily-constructed singleton.
 *
 * Because that singleton survives across tests, every test in this class
 * resets it in setUp() / tearDown() via Events::reset() — otherwise
 * listeners registered in one test bleed into the next.
 */
final class EventsFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        Events::reset();
    }

    protected function tearDown(): void
    {
        Events::reset();
    }

    public function test_priority_constants_mirror_the_event_class(): void
    {
        $this->assertSame(Event::PRIORITY_HIGH, Events::PRIORITY_HIGH);
        $this->assertSame(Event::PRIORITY_NORMAL, Events::PRIORITY_NORMAL);
        $this->assertSame(Event::PRIORITY_LOW, Events::PRIORITY_LOW);
    }

    public function test_on_and_trigger_match_the_readme_example(): void
    {
        $output = [];

        Events::on('helloTrigger', function () use (&$output): void {
            $output[] = 'Hello World';
        }, 100);

        Events::on('helloTrigger', function () use (&$output): void {
            $output[] = 'Hi, World';
        }, 99);

        Events::trigger('helloTrigger');

        $this->assertSame(['Hi, World', 'Hello World'], $output);
    }

    public function test_trigger_forwards_arguments_to_listeners(): void
    {
        $received = null;

        Events::on('greet', function ($name, $me) use (&$received): void {
            $received = sprintf('Hi %s. I am %s.', $name, $me);
        }, 99);

        Events::trigger('greet', 'World', 'John');

        $this->assertSame('Hi World. I am John.', $received);
    }

    public function test_trigger_returns_false_when_a_listener_returns_false(): void
    {
        Events::on('halt', function () { return false; });

        $this->assertFalse(Events::trigger('halt'));
    }

    public function test_simulate_round_trips_through_facade(): void
    {
        $this->assertFalse(Events::getSimulate());

        Events::setSimulate(true);
        $this->assertTrue(Events::getSimulate());
    }

    public function test_debug_mode_round_trips_through_facade(): void
    {
        $this->assertFalse(Events::getDebugMode());

        Events::setDebugMode(true);
        $this->assertTrue(Events::getDebugMode());

        Events::on('e', function (): void {});
        Events::trigger('e');

        $this->assertCount(1, Events::getDebug());
    }

    public function test_get_instance_returns_the_same_singleton_on_repeated_calls(): void
    {
        $first = Events::getInstance();
        $second = Events::getInstance();

        $this->assertSame($first, $second);
        $this->assertInstanceOf(Event::class, $first);
    }

    public function test_reset_drops_the_singleton_so_the_next_call_builds_a_fresh_one(): void
    {
        $first = Events::getInstance();
        Events::reset();
        $second = Events::getInstance();

        $this->assertNotSame($first, $second);
    }

    public function test_reset_drops_previously_registered_listeners(): void
    {
        $hits = 0;
        Events::on('e', function () use (&$hits): void { $hits++; });

        Events::reset();
        Events::trigger('e');

        $this->assertSame(0, $hits);
    }

    public function test_set_instance_lets_callers_inject_a_preconfigured_dispatcher(): void
    {
        $injected = (new Event())->setDebugMode(true);
        Events::setInstance($injected);

        $this->assertSame($injected, Events::getInstance());
        $this->assertTrue(Events::getDebugMode());
    }

    public function test_once_through_the_facade_fires_only_once(): void
    {
        $calls = 0;
        Events::once('tick', function () use (&$calls): void { $calls++; });

        Events::trigger('tick');
        Events::trigger('tick');

        $this->assertSame(1, $calls);
    }

    public function test_off_through_the_facade_removes_a_listener(): void
    {
        $hits = 0;
        $cb = function () use (&$hits): void { $hits++; };

        Events::on('e', $cb);
        Events::off('e', $cb);
        Events::trigger('e');

        $this->assertSame(0, $hits);
    }

    public function test_remove_all_listeners_through_the_facade(): void
    {
        Events::on('a', function (): void {});
        Events::on('b', function (): void {});

        Events::removeAllListeners();

        $this->assertSame([], Events::getEmitter()->listeners());
    }

    /**
     * The Events class also defines a non-static __call() that mirrors
     * its __callStatic(). It's there so `(new Events())->on(...)` works
     * (e.g. an instance accidentally created via reflection or
     * dependency-injection). Both magic methods forward to the same
     * shared singleton.
     */
    public function test_instance_call_magic_forwards_to_the_shared_singleton(): void
    {
        $facade = new Events();

        $hits = 0;
        $facade->on('e', function () use (&$hits): void { $hits++; });

        // The listener landed on the shared instance — the static
        // trigger sees it too.
        Events::trigger('e');

        $this->assertSame(1, $hits);
    }
}
