<?php
/**
 * Event.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Events;

use const SORT_NUMERIC;

use function strtolower;
use function microtime;
use function call_user_func_array;
use function array_multisort;

final class Event
{

    public const PRIORITY_LOW = 200;
    public const PRIORITY_NORMAL = 100;
    public const PRIORITY_HIGH = 10;

    protected array $events = [], $debug = [];
    protected bool $simulate = false, $debugMode = false;

    public function __construct()
    {
    }

    public function __destruct()
    {
        unset($this->events, $this->simulate, $this->debugMode, $this->debug);
    }

    public function __debugInfo()
    {
        return [
            'simulate'  => $this->simulate,
            'debugMode' => $this->debugMode,
            'debugData' => $this->debug,
        ];
    }

    public function getSimulate(): bool
    {
        return $this->simulate ?? false;
    }

    public function setSimulate(bool $simulate = false): self
    {
        $this->simulate = $simulate;
        return $this;
    }

    public function getDebugMode(): bool
    {
        return $this->debugMode ?? false;
    }

    public function setDebugMode(bool $debugMode = false): self
    {
        $this->debugMode = $debugMode;
        return $this;
    }

    public function getDebug(): array
    {
        return $this->debug ?? [];
    }

    /**
     * Eventlerin çalıştırılacağı/kanca atılacak bölgeyi tanımlar.
     *
     * @param string $name
     * @param ...$arguments
     * @return bool
     */
    public function trigger(string $name, ...$arguments): bool
    {
        $name = strtolower($name);
        $events = $this->events($name);
        foreach ($events as $event) {
            if($this->debugMode){
                $start = microtime(true);
            }
            $res = ($this->simulate === FALSE) ? call_user_func_array($event, $arguments) : true;
            if($this->debugMode){
                $this->debug[] = [
                    'start'     => $start,
                    'end'       => microtime(true),
                    'event'     => $name,
                ];
            }
            if($res === FALSE){
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
    public function on(string $name, callable $callback, int $priority = self::PRIORITY_LOW): void
    {
        $name = strtolower($name);
        if(isset($this->events[$name])){
            $this->events[$name]['sort'] = true;
            $this->events[$name]['priority'] = $priority;
            $this->events[$name]['callback'] = $callback;
            return;
        }
        $this->events[$name] = [
            'sort'      => false,
            'priority'  => [$priority],
            'callback'  => [$callback],
        ];
    }

    private function events(string $name): array
    {
        if(!isset($this->events[$name])){
            return [];
        }
        if($this->events[$name]['sort']){
            array_multisort($this->events[$name]['priority'], SORT_NUMERIC, $this->events[$name]['callback']);
        }
        return $this->events[$name]['callback'];
    }

}
