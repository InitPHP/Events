<?php

/**
 * Events.php
 *
 * This file is part of InitPHP Events.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Events;

/**
 * Static facade over a single shared Event dispatcher instance.
 *
 * Convenient for application-wide hooks where carrying a dispatcher
 * around is overkill. Internally it lazily builds one Event instance
 * and forwards every static call to it. Use {@see Events::reset()} in
 * tests (or whenever you need a clean slate) and {@see Events::setInstance()}
 * to inject a pre-configured dispatcher.
 *
 * @mixin Event
 *
 * @method static bool trigger(string $name, ...$arguments)
 * @method static Event on(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL)
 * @method static Event once(string $name, callable $callback, int $priority = Event::PRIORITY_NORMAL)
 * @method static Event off(string $name, callable $callback)
 * @method static Event removeAllListeners(string|null $name = null)
 * @method static bool getSimulate()
 * @method static Event setSimulate(bool $simulate = false)
 * @method static bool getDebugMode()
 * @method static Event setDebugMode(bool $debugMode = false)
 * @method static array getDebug()
 * @method static Event clearDebug()
 * @method static EventEmitter getEmitter()
 */
class Events
{
    const PRIORITY_LOW = Event::PRIORITY_LOW;
    const PRIORITY_NORMAL = Event::PRIORITY_NORMAL;
    const PRIORITY_HIGH = Event::PRIORITY_HIGH;

    /** @var Event|null */
    protected static $Instance;

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    /**
     * Returns the shared Event dispatcher instance, lazily building one
     * the first time it is requested.
     *
     * @return Event
     */
    public static function getInstance()
    {
        if (!isset(self::$Instance)) {
            self::$Instance = new Event();
        }
        return self::$Instance;
    }

    /**
     * Replaces the shared instance. Mainly useful in tests, or when an
     * application wants to inject a pre-configured Event dispatcher.
     *
     * @param Event $event
     * @return void
     */
    public static function setInstance(Event $event)
    {
        self::$Instance = $event;
    }

    /**
     * Drops the shared instance so the next facade call rebuilds a
     * fresh one. Tests should call this in setUp() / tearDown() to
     * keep state from bleeding across them.
     *
     * @return void
     */
    public static function reset()
    {
        self::$Instance = null;
    }
}
