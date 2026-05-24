# Events

It allows you to run functions from outside in different places within your software. It allows you to set up a similar structure known as a hook in the Wordpress ecosystem.

Starting with **2.0**, this package also bundles the low-level `EventEmitter` primitive that used to ship as the separate [`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter) package (now deprecated). See the [migration section](#migrating-from-initphpevent-emitter) below if you are coming from that package.

[![Latest Stable Version](http://poser.pugx.org/initphp/events/v)](https://packagist.org/packages/initphp/events) [![Total Downloads](http://poser.pugx.org/initphp/events/downloads)](https://packagist.org/packages/initphp/events) [![Latest Unstable Version](http://poser.pugx.org/initphp/events/v/unstable)](https://packagist.org/packages/initphp/events) [![License](http://poser.pugx.org/initphp/events/license)](https://packagist.org/packages/initphp/events) [![PHP Version Require](http://poser.pugx.org/initphp/events/require/php)](https://packagist.org/packages/initphp/events)

## Requirements

- PHP 5.6 or higher

## Installation

```
composer require initphp/events
```

## Usage

Call the `trigger()` method where the events will be added. Send event with `on()` method.

```php
require_once "vendor/autoload.php";
use \InitPHP\Events\Events;

Events::on('helloTrigger', function(){
    echo 'Hello World' . PHP_EOL;
}, 100);

Events::on('helloTrigger', function(){
    echo 'Hi, World' . PHP_EOL;
}, 99);

Events::trigger('helloTrigger');
```

**Output :**

```
Hi World
Hello World
```

### Use of Arguments

```php
require_once "vendor/autoload.php";
use \InitPHP\Events\Events;

Events::on('helloTrigger', function($name, $myName){
    echo 'Hello ' . $name . '. I am ' . $myName . '.' . PHP_EOL;
}, 100);

Events::on('helloTrigger', function($name, $myName){
    echo 'Hi ' . $name . '. I am ' . $myName . '.' . PHP_EOL;
}, 99);

Events::trigger('helloTrigger', 'World', 'John');
```

**Output :**

```
Hi World. I am John.
Hello World. I am John.
```

## Low-level `EventEmitter`

In addition to the high-level static `Events` facade, this package also exposes the underlying `EventEmitter` primitive for cases where you want a plain object you can instantiate and pass around:

```php
require_once "vendor/autoload.php";
use InitPHP\Events\EventEmitter;

$emitter = new EventEmitter();

$emitter->on('hello', function ($name) {
    echo 'Hello ' . $name . '!' . PHP_EOL;
}, 99);

$emitter->on('hello', function ($name) {
    echo 'Hi '    . $name . '!' . PHP_EOL;
}, 10);

$emitter->emit('hello', ['World']);
```

## Migrating from `initphp/event-emitter`

The standalone [`initphp/event-emitter`](https://github.com/InitPHP/EventEmitter) package has been merged into this one starting with **2.0** and is now deprecated.

If your code currently uses `\InitPHP\EventEmitter\EventEmitter`, **no source changes are required** — this package ships a backwards-compatibility alias that keeps the old fully-qualified class name working. Just switch your dependency:

```diff
- "initphp/event-emitter": "^1.0",
+ "initphp/events": "^2.0"
```

(`initphp/events:^2.0` declares a Composer `replace` for `initphp/event-emitter`, so Composer will not install both side-by-side.)

When you next touch the code, prefer the new canonical namespace:

```php
// Before
use InitPHP\EventEmitter\EventEmitter;

// After
use InitPHP\Events\EventEmitter;
```

The alias is intended as a transition aid and may be removed in a future major release.

### Notable change: `emit()` bug fix

The 1.x line of `initphp/event-emitter` shipped with a bug in `EventEmitter::emit()` where the listeners array (rather than each individual listener) was passed to `call_user_func_array`, causing emitted events to silently fail. This is fixed in `initphp/events:^2.0`. If you relied on `EventEmitter::emit()` and saw no listeners firing, you should expect them to fire now — review any code that depended on the broken behavior.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
