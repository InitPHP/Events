<?php
/**
 * EventEmitterInterface.php
 *
 * This file is part of InitPHP Events.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Events;

interface EventEmitterInterface
{

    /**
     * @param string $event
     * @param callable $listener
     * @param int $priority
     * @return EventEmitterInterface
     */
    public function on($event, $listener, $priority = 100);

    /**
     * @param string $event
     * @param callable $listener
     * @param int $priority
     * @return EventEmitterInterface
     * @throws \InvalidArgumentException
     */
    public function once($event, $listener, $priority = 100);

    /**
     * @param string $event
     * @param callable $listener
     * @return void
     * @throws \InvalidArgumentException
     */
    public function removeListener($event, $listener);

    /**
     * @param null|string $event
     * @return void
     * @throws \InvalidArgumentException <p>If $event is not string or null.</p>
     */
    public function removeAllListeners($event = null);

    /**
     * @param null|string $event
     * @return array
     * @throws \InvalidArgumentException <p>If $event is not string or null.</p>
     */
    public function listeners($event = null);

    /**
     * @param string $event
     * @param array $arguments
     * @return void
     * @throws \InvalidArgumentException
     */
    public function emit($event, $arguments = []);

}
