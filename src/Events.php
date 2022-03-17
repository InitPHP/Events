<?php
/**
 * Events.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Events;

/**
 * @method static bool trigger(string $name, ...$arguments)
 * @method static void on(string $name, callable $callback, int $priority = Event::PRIORITY_LOW)
 *
 * @method static bool getSimulate()
 * @method static Event setSimulate(bool $simulate = false)
 * @method static bool getDebugMode()
 * @method static Event setDebugMode(bool $debugMode = false)
 * @method static array getDebug()
 */
class Events
{

    public const PRIORITY_LOW = Event::PRIORITY_LOW;
    public const PRIORITY_NORMAL = Event::PRIORITY_NORMAL;
    public const PRIORITY_HIGH = Event::PRIORITY_HIGH;

    protected static Event $Instance;

    public function __construct()
    {
        self::getInstance();
    }

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    protected static function getInstance(): Event
    {
        if(!isset(self::$Instance)){
            self::$Instance = new Event();
        }
        return self::$Instance;
    }

}
