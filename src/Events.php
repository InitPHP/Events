<?php
/**
 * Events.php
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

/**
 * @mixin Event
 * @method static bool trigger(string $name, ...$arguments)
 * @method static void on(string $name, callable $callback, int $priority = Event::PRIORITY_LOW)
 * @method static bool getSimulate()
 * @method static Event setSimulate(bool $simulate = false)
 * @method static bool getDebugMode()
 * @method static Event setDebugMode(bool $debugMode = false)
 * @method static array getDebug()
 */
class Events
{

    const PRIORITY_LOW = Event::PRIORITY_LOW;
    const PRIORITY_NORMAL = Event::PRIORITY_NORMAL;
    const PRIORITY_HIGH = Event::PRIORITY_HIGH;

    /** @var Event */
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
     * @return Event
     */
    protected static function getInstance()
    {
        if(!isset(self::$Instance)){
            self::$Instance = new Event();
        }
        return self::$Instance;
    }

}
