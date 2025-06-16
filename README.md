# GimliDuck ⚔️🦆
An adaptable micro PHP framework that tries to stay out of your way.

**Certainty of death. Small chance of success. What are we waiting for?**

## Installation
`composer require danc0/gimliduck-php`

Create a skeleton project with:
`composer create-project danc0/gimli-skeleton`

Add the [devtools](https://github.com/dvnc0/gimli-devtools) with `composer require --dev danc0/gimliduck-devtools`

[Docs](https://dvnc0.github.io/gimli-php/)

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
use Gimli\Application_Registry;
use Gimli\Router\Route;

$App = Application::create(__DIR__, $_SERVER);

Route::get('/', function(){
	echo "Hello World";
});

Application_Registry::set($App);
$App->run();
```
That is really all you need to get started. You can add more like a template engine, a config file, etc, but you don't **have** to.

### A more complex example
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Application;
use Gimli\Application_Registry;
use App\Core\Config;
use App\Core\Cache;

define('APP_ROOT', __DIR__);

$App = Application::create(APP_ROOT, $_SERVER);

// set up your config and add it to the Application
$config_file = parse_ini_file(APP_ROOT . '/App/Core/config.ini', true);
$App->Config = $App->Injector->resolveFresh(Config::class, ['config' => $config_file], $App);

// Register a cache class with the Injector
$App->Injector->register(Cache::class, Cache::getCache($App->Config->admin_cache));

Application_Registry::set($App);
// Run Application
$App->run();
```
[Tead the Docs](https://dvnc0.github.io/gimli-php/) for more information and examples.