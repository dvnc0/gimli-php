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

Route::get('/', function(){
	echo "Hello World";
});

$App->run();
```
That is really all you need to get started. You can add more like a template engine, a config file, etc, but you don't **have** to.

### A more complex example
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;
use App\Core\Config;
use App\Core\Cache;

define('APP_ROOT', __DIR__);

$App = Application::create(APP_ROOT, $_SERVER);

// set up your config and add it to the Application
$config_file = parse_ini_file(APP_ROOT . '/App/Core/config.ini', true);
$config_file['template_base_dir'] = APP_ROOT . '/App/views';
$App->Config = $App->Injector->resolveFresh(Config::class, ['environment_settings' => $config_file]);

// Register a cache class with the Injector
$App->Injector->register(Cache::class, Cache::getCache($App->Config->admin_cache));

// Bind PDO object creation method to Injector
$App->Injector->bind(PDO::class, function() use ($App) {
	$database = $App->Config->admin_database;
	$dsn = "mysql:host={$database['host']};dbname={$database['name']}";
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	return new PDO($dsn, $database['user'], $database['password'], $options);
});

// Load routes from a file
require_once APP_ROOT . '/App/routes/web.php';

// Run Application
$App->run();
```

The `Application` class also registers the basic Event handler and Session class when it is created. Additionally, if your config includes `enable_latte` the Latte template engine will be added to the Application instance using the config value for `template_base_dir` as the template directory.

### Declaring Routes
There are a few things you can do with your route callbacks... pass a string, a callable, or an array.

```php

Route::get('/', function(){
	echo "Hello World"
});

// Single action controller, must use __invoke method
Route::get('/', Home_Controller::class);

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

The built in Injector will autowire classes and resolve dependencies as needed. You can also bind a class to a closure if you need to do some setup before returning the class or register an already created object. The example below shows a single action controller that would be resolved from the Router class. The `__construct` method parameters are resolved from the Injector. 

```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Logic\Dashboard_Logic;
use Gimli\Http\Response;
use Gimli\Application;
use Gimli\View\Latte_Engine;

class Dashboard_Landing_Controller {

	/**
	 * Constructor
	 *
	 * @param Application $Application
	 */
	public function __construct(
		public Application $Application,
		protected Dashboard_Logic $Dashboard_Logic,
		protected Latte_Engine $View
	){
		//
	}

	/**
	 * Single action controller call
	 *
	 * @return Response
	 */
	public function __invoke(Response $Response): Response {		
		$template_data = $this->Dashboard_Logic->getTemplateData();
		return $Response->setResponse($this->View->render('dashboard/dashboard.latte', $template_data));
	}
}
```
The method parameters are also resolved by the Injector when the Route dispatches the method.