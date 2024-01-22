# GimliDuck ⚔️🦆
An adaptable micro PHP framework that tries to stay out of your way.

## Installation
TODO

## Usage
Creating a GimliDuck application is simple, because it should be.

```php
<?php
declare(strict_types=1);

include_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;

$App = new Application(__DIR__, $_SERVER);
$Router = $App->Router;

$Router->get('/', function(){
	echo "Hello World"
});

$Router->run();
```
That is really all you need to get started. You can add more like a template engine, a config file, etc, but you don't **have** to.