# Getting started with Gimli

Gimli is a lightweight PHP framework designed to stay out of your way while providing the essential features you need for web development. This guide will walk you through setting up your first Gimli project.

## Installation

The easiest way to install Gimli is through Composer:

```bash
composer require danc0/gimliduck-php
```

For a complete skeleton project that includes all the necessary files and structure:

```bash
composer create-project danc0/gimli-skeleton
```

If you're developing, you might want to add the developer tools:

```bash
composer require --dev danc0/gimliduck-devtools
```

## Web server configuration

For Apache, create a `.htaccess` file in your project root with the following content:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

This will send all requests to your `index.php` file where Gimli can handle the routing.

## Creating your first Gimli application

Let's set up a basic Gimli application. Create an `index.php` file in your project root:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Router\Route;

// Create the application with the project root path and server variables
$App = Application::create(__DIR__, $_SERVER);

// Register the application in the global registry
Application_Registry::set($App);

// Define a simple route
Route::get('/', function() {
    echo "Hello from Gimli!";
});

// Run the application
$App->run();
```

This minimal setup gives you a working Gimli application. Visit your site, and you should see "Hello from Gimli!" displayed.

## Configuration

For more complex applications, you'll want to set up configuration. Gimli includes a `Config` class in the `Gimli\Environment` namespace.

Create an INI file (for example in `App/Core/config.ini`):

```ini
[app]
app_name = "My Gimli App"
debug = true

[templates]
enable_latte = true
template_base_dir = "App/Views"

[routes]
autoload_routes = true
route_directory = "App/Routes"
```

Then, update your `index.php` to load this configuration:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Environment\Config;

define('APP_ROOT', __DIR__);

// Create the application
$App = Application::create(APP_ROOT, $_SERVER);

// Register the application in the global registry
Application_Registry::set($App);

// Load configuration
$App->setConfig(resolve_fresh(Config::class, ['config' => parse_ini_file(APP_ROOT . '/App/Core/config.ini', true)], $App))
    ->enableLatte();

// Run the application
$App->run();
```

The built-in `Config` class automatically manages configuration values and provides methods like `get()`, `set()`, and `has()` for working with your settings. Default configuration values are set in the `Config` class, which you can override with your own values.

## Setting up routes

While you can define routes directly in `index.php`, it's better to organize them in separate files. Create a directory for your routes (by default `App/Routes`), and then create a file called `web.php`:

```php
<?php
declare(strict_types=1);

use Gimli\Router\Route;
use Gimli\Http\Response;

// Define your routes
Route::get('/', function(Response $Response) {
    return $Response->setResponse("Welcome to my Gimli app!");
});

Route::get('/users/:id', [UserController::class, 'show']);

// Group routes with middleware
Route::group('/admin', function() {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
})->addMiddleware(AdminMiddleware::class);
```

If you set `autoload_routes` to `true` in your config, Gimli will automatically load all PHP files in your routes directory.

## Using the Application_Registry

Gimli provides an `Application_Registry` class that allows you to access the application instance from anywhere in your code:

```php
<?php
// In any file where you need access to the Application
use Gimli\Application_Registry;

$App = Application_Registry::get();

// Now you can use $App for dependency injection, configuration, etc.
$config_value = $App->Config->get('some_key');
$service = $App->Injector->resolve(Some_Service::class);
```

## Dependency Injection

Gimli comes with a built-in dependency injection container. You can register services, resolve dependencies, and autowire classes:

```php
<?php
// Register a service with the injector
$App->Injector->register(Cache::class, new Cache($config));

// Bind a service with a factory function
$App->Injector->bind(Database::class, function() use ($App) {
    return new Database($App->Config->database);
});

// Resolve a service with its dependencies automatically injected
$controller = $App->Injector->resolve(UserController::class);
```

## Next steps

Now that you have a basic Gimli application running, you can:

1. Create controllers in `App/Controllers`
2. Set up a database connection using the built-in Database class
3. Create views using the Latte template engine
4. Add middleware for request filtering

Check out the other documentation sections to learn more about these features.
