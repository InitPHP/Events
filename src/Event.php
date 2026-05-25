<?php
/**
 * Event.php
 *
 * This file is part of InitPHP Events.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Events;

use InvalidArgumentException;

use function call_user_func_array;
use function is_bool;
use function is_string;
use function microtime;

/**
 * High-level event dispatcher with WordPress-style hook semantics.
 *
 * Wraps an EventEmitter and adds three features that the low-level
 * emitter does not concern itself with:
 *
 *  - Priority-ordered dispatch via {@see Event::trigger()} that *stops*
 *    the chain when a listener returns boolean false (this is the
 *    "hook" behaviour the WordPress action API also exposes).
 *  - Simulate mode: registered listeners are not actually invoked when
 *    trigger() runs. Useful for dry-runs.
 *  - Debug mode: each trigger() invocation appends a record (start, end,
 *    event name) to an internal log, retrievable via getDebug().
 *
 * Listener registration and removal (on/once/off/removeAllListeners)
 * are forwarded to the underlying EventEmitter.
 */
final class Event
{
    const PRIORITY_LOW = 200;
    const PRIORITY_NORMAL = 100;
    const PRIORITY_HIGH = 10;

    /** @var EventEmitter */
    protected $emitter;

    /** @var array */
    protected $debug = [];

    /** @var bool */
    protected $simulate = false;

    /** @var bool */
    protected $debugMode = false;

    public function __construct()
    {
        $this->emitter = new EventEmitter();
    }

    public function __debugInfo()
    {
        return [
            'simulate'  => $this->simulate,
            'debugMode' => $this->debugMode,
            'debugData' => $this->debug,
        ];
    }

    /**
     * @return bool
     */
    public function getSimulate()
    {
        return $this->simulate;
    }

    /**
     * @param bool $simulate
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setSimulate($simulate = false)
    {
        if (!is_bool($simulate)) {
            throw new InvalidArgumentException('$simulate must be a boolean.');
        }
        $this->simulate = $simulate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDebugMode()
    {
        return $this->debugMode;
    }

    /**
     * @param bool $debugMode
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setDebugMode($debugMode = false)
    {
        if (!is_bool($debugMode)) {
            throw new InvalidArgumentException('$debugMode must be a boolean.');
        }
        $this->debugMode = $debugMode;
        return $this;
    }

    /**
     * @return array<int, array{start: float|int, end: float, event: string}>
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Clears any previously collected debug records.
     *
     * @return $this
     */
    public function clearDebug()
    {
        $this->debug = [];
        return $this;
    }

    /**
     * Dispatches an event by invoking every registered listener in
     * ascending priority order.
     *
     * Behaviour notes:
     *  - When a listener returns boolean false the chain is *stopped* and
     *    trigger() itself returns false. Subsequent listeners are not
     *    invoked. This mirrors WordPress's apply_filters short-circuit.
     *  - One-shot listeners registered with {@see Event::once()} are
     *    always discarded after this call, even if the chain was halted
     *    by a false return — the once contract is "fire at most once".
     *  - In simulate mode the listener bodies are not executed and
     *    trigger() always returns true.
     *
     * @param string $name
     * @param mixed  ...$arguments
     * @return bool false if a listener short-circuited the chain, true otherwise.
     * @throws InvalidArgumentException
     */
    public function trigger($name, ...$arguments)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('$name must be a string.');
        }

        $listeners = $this->emitter->listeners($name);

        try {
            foreach ($listeners as $listener) {
                $start = $this->debugMode ? microtime(true) : 0;
                $result = $this->simulate
                    ? true
                    : call_user_func_array($listener, $arguments);

                if ($this->debugMode) {
                    $this->debug[] = [
                        'start' => $start,
                        'end'   => microtime(true),
                        'event' => $name,
                    ];
                }

                if ($result === false) {
                    return false;
                }
            }
        } finally {
            // Honour the once() contract even when the chain is stopped
            // by a false return or a listener exception.
            $this->emitter->clearOnceListeners($name);
        }

        return true;
    }

    /**
     * Registers a listener for the given event.
     *
     * @param string   $name
     * @param callable $callback
     * @param int      $priority Lower numeric value runs first.
     *                           Use the PRIORITY_HIGH / PRIORITY_NORMAL /
     *                           PRIORITY_LOW constants for readability.
     * @return $this
     * @throws InvalidArgumentException
     */
    public function on($name, $callback, $priority = self::PRIORITY_NORMAL)
    {
        $this->emitter->on($name, $callback, $priority);
        return $this;
    }

    /**
     * Registers a one-shot listener that is automatically removed after
     * the next trigger() of the event.
     *
     * @param string   $name
     * @param callable $callback
     * @param int      $priority
     * @return $this
     * @throws InvalidArgumentException
     */
    public function once($name, $callback, $priority = self::PRIORITY_NORMAL)
    {
        $this->emitter->once($name, $callback, $priority);
        return $this;
    }

    /**
     * Removes a previously registered listener (regular or one-shot) for
     * the given event.
     *
     * @param string   $name
     * @param callable $callback
     * @return $this
     * @throws InvalidArgumentException
     */
    public function off($name, $callback)
    {
        $this->emitter->removeListener($name, $callback);
        return $this;
    }

    /**
     * Removes every listener registered for the given event, or every
     * listener for every event when called with no arguments.
     *
     * @param null|string $name
     * @return $this
     * @throws InvalidArgumentException
     */
    public function removeAllListeners($name = null)
    {
        $this->emitter->removeAllListeners($name);
        return $this;
    }

    /**
     * Returns the EventEmitter instance backing this dispatcher, for
     * cases that need the low-level emit() / clearOnceListeners() API.
     *
     * @return EventEmitter
     */
    public function getEmitter()
    {
        return $this->emitter;
    }
}
