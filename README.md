# GimliDuck ⚔️🦆
An adaptable micro PHP framework that tries to stay out of your way.

**Very much a work in progress, use at your own risk...**

**Certainty of death. Small chance of success. What are we waiting for?**

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

### Declaring Routes
There are a few things you can do with your route callbacks... pass a string, a callable, or an array.

```php

$Router->get('/', function(){
	echo "Hello World"
});

$Router->get('/', Home_Controller::class . '@homePage');

$Router->get('/', [Home_Controller::class, 'homePage']);
```
Any of those work, it's up to you how you do it.

You can add middleware if you need some extra defense

```php
$Router->get('/', [Home_Controller::class, 'homePage'])->addMiddleware(Logged_In_Middleware::class);
```

That should be an instance of `Gimli\Middleware\Middleware_Base` and you need to define the `abstract process` method, which returns `Gimli\Middleware\Middleware_Response`. Middleware does have access to the Application instance including the Injector and whatever else you decide to set on it.
