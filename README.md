# Events

It allows you to run functions from outside in different places within your software. It allows you to set up a similar structure known as a hook in the Wordpress ecosystem.

## Requirements

- PHP 7.4 or higher

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
    echo 'Hello World';
}, 100);

Events::on('helloTrigger', function(){
    echo 'Hi, World' . PHP_EOL;
}, 99);

Events::trigger('helloTrigger');
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
