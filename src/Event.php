<?php
/**
 * Event.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.2
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Events;

use function microtime;
use function call_user_func_array;
use function is_bool;
use function is_string;

use InitPHP\EventEmitter\EventEmitter;

final class Event
{
    const PRIORITY_LOW = 200;
    const PRIORITY_NORMAL = 100;
    const PRIORITY_HIGH = 10;

    /** @var EventEmitter */
    protected $emitter;

    protected $debug = [];

    /** @var bool */
    protected $simulate = false;

    /** @var bool */
    protected $debugMode = false;

    public function __construct()
    {
        $this->emitter = new EventEmitter();
    }

    public function __destruct()
    {
        unset($this->emitter, $this->simulate, $this->debug, $this->debugMode);
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
        return isset($this->simulate) ? $this->simulate : false;
    }

    /**
     * @param bool $simulate
     * @return $this
     */
    public function setSimulate($simulate = false)
    {
        if(!is_bool($simulate)){
            throw new \InvalidArgumentException('$simulate must be a boolean.');
        }
        $this->simulate = (bool)$simulate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDebugMode()
    {
        return isset($this->debugMode) ? $this->debugMode : false;
    }

    /**
     * @param bool $debugMode
     * @return $this
     */
    public function setDebugMode($debugMode = false)
    {
        if(!is_bool($debugMode)){
            throw new \InvalidArgumentException('$debugMode must be a boolean.');
        }
        $this->debugMode = $debugMode;
        return $this;
    }

    /**
     * @return array
     */
    public function getDebug()
    {
        if(!isset($this->debug)){
            $this->debug = [];
        }
        return $this->debug;
    }

    /**
     * Eventlerin çalıştırılacağı/kanca atılacak bölgeyi tanımlar.
     *
     * @param string $name
     * @param ...$arguments
     * @return bool
     */
    public function trigger($name, ...$arguments)
    {
        if(!is_string($name)){
            throw new \InvalidArgumentException('$name must be a string.');
        }
        $events = $this->emitter->listeners($name);
        foreach ($events as $event) {
            $start = $this->debugMode ? microtime(true) : 0;
            $res = ($this->simulate === FALSE) ? call_user_func_array($event, $arguments) : true;
            if ($this->debugMode) {
                $this->debug[] = [
                    'start'     => $start,
                    'end'       => microtime(true),
                    'event'     => $name,
                ];
            }
            if ($res === FALSE) {
                return false;
            }
        }
        return true;
    }

    /**
     * Bellirtilen bölgede çalıştırılacak işlemi tanımlar.
     *
     * @param string $name
     * @param callable $callback
     * @param int $priority
     * @return void
     */
    public function on($name, $callback, $priority = self::PRIORITY_LOW)
    {
        $this->emitter->on($name, $callback, $priority);
    }

}
