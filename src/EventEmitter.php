<?php

/**
 * EventEmitter.php
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

use function array_keys;
use function array_merge;
use function array_search;
use function array_unique;
use function call_user_func_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function ksort;
use function strtolower;

class EventEmitter implements EventEmitterInterface
{
    /**
     * Map of <event-name, <priority, list<callable>>>. Event names are
     * always stored lower-cased so lookups are case-insensitive.
     *
     * @var array<string, array<int, list<callable>>>
     */
    protected $listeners = [];

    /** @var array<string, array<int, list<callable>>> */
    protected $onceListeners = [];

    /**
     * @inheritDoc
     */
    public function on($event, $listener, $priority = 100)
    {
        $this->addListener('listeners', $event, $listener, $priority);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function once($event, $listener, $priority = 100)
    {
        $this->addListener('onceListeners', $event, $listener, $priority);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeListener($event, $listener)
    {
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event must be a string.');
        }
        if (!is_callable($listener)) {
            throw new InvalidArgumentException('$listener must be a callable.');
        }

        $event = strtolower($event);

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $key => $value) {
                if (($index = array_search($listener, $value, true)) === false) {
                    continue;
                }
                unset($this->listeners[$event][$key][$index]);
                if (empty($this->listeners[$event][$key])) {
                    unset($this->listeners[$event][$key]);
                }
            }
        }

        if (isset($this->onceListeners[$event])) {
            foreach ($this->onceListeners[$event] as $key => $value) {
                if (($index = array_search($listener, $value, true)) === false) {
                    continue;
                }
                unset($this->onceListeners[$event][$key][$index]);
                if (empty($this->onceListeners[$event][$key])) {
                    unset($this->onceListeners[$event][$key]);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function removeAllListeners($event = null)
    {
        if ($event === null) {
            $this->listeners = [];
            $this->onceListeners = [];
            return;
        }
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event must be a string or null.');
        }
        $event = strtolower($event);
        if (isset($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
        if (isset($this->onceListeners[$event])) {
            unset($this->onceListeners[$event]);
        }
    }

    /**
     * @inheritDoc
     *
     * @return list<callable>
     */
    public function listeners($event = null)
    {
        $events = [];
        if ($event === null) {
            $eventNames = array_unique(array_merge(array_keys($this->listeners), array_keys($this->onceListeners)));
        } else {
            if (!is_string($event)) {
                throw new InvalidArgumentException('$event must be a string or null.');
            }
            $eventNames = [$event];
        }
        foreach ($eventNames as $eventName) {
            $key = strtolower($eventName);

            // Merge regular and once listeners by priority, preserving
            // insertion order (FIFO) inside each priority bucket.
            $byPriority = [];
            if (isset($this->listeners[$key])) {
                foreach ($this->listeners[$key] as $priority => $bucket) {
                    foreach ($bucket as $listener) {
                        $byPriority[$priority][] = $listener;
                    }
                }
            }
            if (isset($this->onceListeners[$key])) {
                foreach ($this->onceListeners[$key] as $priority => $bucket) {
                    foreach ($bucket as $listener) {
                        $byPriority[$priority][] = $listener;
                    }
                }
            }

            // Lower numeric priority must fire first (PRIORITY_HIGH = 10,
            // PRIORITY_LOW = 200), independent of registration order.
            ksort($byPriority);

            foreach ($byPriority as $bucket) {
                foreach ($bucket as $listener) {
                    $events[] = $listener;
                }
            }
        }
        return $events;
    }

    /**
     * @inheritDoc
     *
     * @param array<int|string, mixed> $arguments
     */
    public function emit($event, $arguments = [])
    {
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event must be a string.');
        }
        if (!is_array($arguments)) {
            throw new InvalidArgumentException('$arguments must be an array.');
        }

        $listeners = $this->listeners($event);

        $event = strtolower($event);
        if (isset($this->onceListeners[$event])) {
            unset($this->onceListeners[$event]);
        }

        if (!empty($listeners)) {
            foreach ($listeners as $listener) {
                call_user_func_array($listener, $arguments);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function clearOnceListeners($event = null)
    {
        if ($event === null) {
            $this->onceListeners = [];
            return;
        }
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event must be a string or null.');
        }
        $key = strtolower($event);
        if (isset($this->onceListeners[$key])) {
            unset($this->onceListeners[$key]);
        }
    }

    /**
     * @param 'listeners'|'onceListeners' $property
     * @param string $event
     * @param callable $listener
     * @param int $priority
     * @return void
     * @throws InvalidArgumentException
     */
    private function addListener($property, $event, $listener, $priority = 100)
    {
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event must be a string.');
        }
        if (!is_callable($listener)) {
            throw new InvalidArgumentException('$listener must be a callable.');
        }
        if (!is_int($priority)) {
            throw new InvalidArgumentException('$priority must be an integer.');
        }
        $event = strtolower($event);

        if (!isset($this->{$property}[$event])) {
            $this->{$property}[$event] = [];
        }
        if (!isset($this->{$property}[$event][$priority])) {
            $this->{$property}[$event][$priority] = [];
        }
        $this->{$property}[$event][$priority][] = $listener;
    }
}
