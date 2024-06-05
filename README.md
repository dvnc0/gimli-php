# GimliDuck ⚔️🦆
An adaptable micro PHP framework that tries to stay out of your way.

**Very much a work in progress, use at your own risk...**

**Certainty of death. Small chance of success. What are we waiting for?**

## Installation
`composer require danc0/gimliduck-php`

Create a `.htaccess` file that looks something like this to point requests to your `index.php` file

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Usage
Creating a GimliDuck application is simple:

```php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;
use Gimli\Router\Route;

$App = Application::create(__DIR__, $_SERVER);
$App->Config = $App->Injector->resolveFresh(Config::class, ['environment_settings' => []]);

Route::get('/', function(){
	echo "Hello World";
});

$App->run();
```
That is really all you need to get started. You can add more like a template engine, a config file, etc, but you don't **have** to.

### Declaring Routes
There are a few things you can do with your route callbacks... pass a string, a callable, or an array.

```php

Route::get('/', function(){
	echo "Hello World"
});

Route::get('/', Home_Controller::class . '@homePage');

Route::get('/', [Home_Controller::class, 'homePage']);
```
Any of those work, it's up to you how you do it.

You can add middleware if you need some extra defense

```php
Route::get('/', [Home_Controller::class, 'homePage'])->addMiddleware(Logged_In_Middleware::class);
```

That should be an instance of `Gimli\Middleware\Middleware_Base` and you need to define the `abstract process` method, which returns `Gimli\Middleware\Middleware_Response`. Middleware does have access to the Application instance including the Injector and whatever else you decide to set on it.

You can also add groups, define a default route file that should load, and load additional route files to help organize your routes.

### Dependency Injection

You can use the built in Injector to bind or register dependencies. You can also resolve dependencies from the Injector. You can add anything you need to the Injector and access it throughout your application through the `Application` instance.