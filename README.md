# GimliDuck ⚔️🦆
An adaptable micro PHP framework that tries to stay out of your way.

**Very much a work in progress, use at your own risk...**

**Certainty of death. Small chance of success. What are we waiting for?**

## Installation
`composer require danc0/gimliduck-php`

Create a skeleton project with:
`composer create-project danc0/gimli-skeleton`

Add the [devtools](https://github.com/dvnc0/gimli-devtools) with `composer require --dev danc0/gimliduck-devtools`

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

Application::create(__DIR__, $_SERVER);

Route::get('/', function(){
	echo "Hello World";
});

Application::start();
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
$App->Config = $App->Injector->resolveFresh(Config::class, ['config' => $config_file]);

// Register a cache class with the Injector
$App->Injector->register(Cache::class, Cache::getCache($App->Config->admin_cache));

// Run Application
$App->run();
```

The `Application` class also registers the basic Event handler and Session class when it is created. Additionally, if your config includes `enable_latte` the Latte template engine will be added to the Application instance using the config value for `template_base_dir` as the template directory.

### Declaring Routes
By default Gimli will require any files in the `App/Routes` directory. You can disable this by setting `autoload_routes` to `false` in your config file. You can change the directory by setting the value of `route_directory` in your config file. You can also load additional route files with the following method:
```php
// Load routes from a file(s)
$App->loadRouteFiles([
	'App/routes/web.php',
]);
```
There are a few things you can do with your route callbacks... pass a string, a callable, or an array. 
```php

Route::get('/', function(){
	echo "Hello World"
});

// Single action controller, must use __invoke method
Route::get('/', Home_Controller::class);
// cli routes are single action Job classes
Route::cli('build-cache', Cache_Job::class);

Route::get('/', Home_Controller::class . '@homePage');

Route::get('/', [Home_Controller::class, 'homePage']);
```
Any of those work, it's up to you how you do it.

You can add middleware if you need some extra defense

```php
Route::get('/', [Home_Controller::class, 'homePage'])->addMiddleware(Logged_In_Middleware::class);
```

That should be a class that implements `Gimli\Middleware\Middleware_Interface` which requires a `process` method that returns `Gimli\Middleware\Middleware_Response`. Middleware does have access to the Application instance including the Injector and whatever else you decide to set on it.

You can also add groups, define a default route file that should load, and load additional route files to help organize your routes.

Your routes can also contain variable arguments that meet the following patterns:

```php
protected array $patterns = [
	':all' => "([^/]+)",
	':alpha' => "([A-Za-z_-]+)",
	':alphanumeric' => "([\w-]+)",
	':integer' => "([0-9_-]+)",
	':numeric' => "([0-9_-.]+)",
	':id' => "([0-9_-]+)",
	':slug' => "([A-Za-z0-9_-]+)",
];
```
You will need to add their variable name using the `#` symbol in the route definition. 

```php
Route::get('/user/:integer#id', [User_Controller::class, 'getUser']);
```

This variable name is passed to the router and set as a dependency for your controller method. You should use the defined variable name as an argument in your controller method. The value will be typecast based on the available types to `settype`, possible types:

```
integer or int
float or double
string
array
object
boolean or bool
```

Example controller method:

```php
public function getUser(Response $Response, int $id): Response {
	// do something with $id
}
```

Controllers should return a `Gimli\Http\Response` object. There are helper methods that return formatted `Response` objects to help limit some conditional logic:
- `response` A basic Response
- `redirect` Redirect Response
- `redirect_on_success` Redirect if the response is successful
- `redirect_on_failure` Redirect if the response is not successful
- `json_response` JSON Response

Job files are also given the following arguments `subcommand`, `options`, and `flags`. The `options` argument is an array of arrays containing the name and value. The `flags` argument is an array with the given flags. The `subcommand` argument is just a string if a subcommand was given.

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

There are also Injector helper methods that cut down on some inline code. Typically if you wanted to inject a class inline you could do it with `$this->Application->Injector->resolve(Some_Class::class)` or `Application::get()->Injector->resolve(Some_Class::class)`. The methods `resolve` and `resolve_fresh` are available to cut on that inline code.

### Database
There is a basic PDO wrapper `Database` as well as a `Pdo_Manager` class you can use to manage database queries. The `Pdo_Manager` class returns and instance of `PDO` and can be used to run queries directly. The `Database` class is a wrapper around the `Pdo_Manager` class and provides some basic query methods. There is also a very basic `Model` base class and additional helper methods for `Database`, like elsewhere these methods handle the dependency injection and call the methods on the injected `Database` class. The helpers are:
- `fetch_column`
- `fetch_row` 
- `fetch_all`
- `row_exists`

### Model Seeders
There is a basic seeder class that can be used to seed your database. This relies on attributes placed in the model classes to instruct the `Seeder` how to create the data.

```php
<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Database\Model;
use Gimli\Database\Seed;


class User_Model extends Model {
	
	/**
	 * @var string $table_name
	 */
	protected string $table_name = 'users';

	
	/**
	 * ID
	 * 
	 * @var int $id 
	 */
	public $id;

	/**
	 * Unique_Id
	 * 
	 * @var string $unique_id 
	 */
	#[Seed(type: 'unique_id', args: ['length' => 12])]
	public $unique_id;

	/**
	 * Username
	 * 
	 * @var string $username 
	 */
	#[Seed(type: 'username')]
	public $username;

	/**
	 * Email
	 * 
	 * @var string $email 
	 */
	#[Seed(type: 'email')]
	public $email;

	/**
	 * Password
	 * 
	 * @var string $password 
	 */
	#[Seed(type: 'password')]
	public $password;

	/**
	 * Is_Active
	 * 
	 * @var int $is_active 
	 */
	#[Seed(type: 'tiny_int')]
	public $is_active;

	/**
	 * First Name
	 * 
	 * @var string $first_name 
	 */
	#[Seed(type: 'first_name')]
	public $first_name;

	/**
	 * Last Name
	 * 
	 * @var string $last_name 
	 */
	#[Seed(type: 'last_name')]
	public $last_name;

	/**
	 * Status
	 * 
	 * @var int $status 
	 */
	#[Seed(type: 'one_of', args: [0,1])]
	public $status;

	/**
	 * Created_At
	 * 
	 * @var string $created_at 
	 */
	#[Seed(type: 'date', args: ['format' => 'Y-m-d H:i:s', 'min' => '2021-01-01', 'max' => '2021-04-01 00:00:00'])]
	public $created_at;

	/**
	 * Updated_At
	 * 
	 * @var string $updated_at 
	 */
	#[Seed(type: 'date', args: ['format' => 'Y-m-d H:i:s', 'min' => '2021-04-01 00:02:00'])]
	public $updated_at;

	/**
	 * bio
	 * 
	 * @var string $about 
	 */
	#[Seed(type: 'paragraph', args: ['count' => 1])]
	public $about;
}
```

You can then seed the database with the following code:

```php
Seeder::make(User_Model::class)
	->seed(123)
	->count(1)
	->create();
```

Instead of create you can call `getSeededData` to get the data that would be inserted into the database. This is useful for testing or manually loading a Model without saving it. You can also pass a callback method that will be given the data of the initial Model. This helps to seed related data. The callback should return an array of `Seeder` instances.

```php
Seeder::make(User_Model::class)
	->seed(123)
	->count(1)
	->callback(function($data) {
		return [
			Seeder::make(User_Hobby_Model::class)->with(['user_id' => $data['id']]),
			Seeder::make(User_Group_Model::class)->with(['user_id' => $data['id']]),
		]
	})
	->create();
```
The passed seed ensures the data remains the same each time that seeder is run, resulting in reproducible datasets. The `Seeder` class has a `getRandomSeed` method that will return a random seed value. This is useful for creating random data that you don't need to be reproducible, or to generate a random seed you can copy and use.

### Config Helpers
There are also a few Config helpers:
- `get_config` to get the entire config array
- `get_config_value` to get a specific value from the config array
- `config_has` to check if a key exists in the config array