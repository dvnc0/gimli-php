<?php
declare(strict_types=1);

namespace Gimli;

use Gimli\Injector\Injector_Interface;
use Gimli\Http\Request;
use Gimli\Router\Router;
use Gimli\Environment\Config;
use Gimli\Injector\Injector;
use Gimli\Exceptions\Gimli_Application_Exception;
use Gimli\Exceptions\Route_Loader_Exception;
use Gimli\Http\Response;
use Gimli\Router\Route;
use Gimli\Session\Session;
use Gimli\View\Latte_Engine;
use Gimli\Events\Event_Manager;

use function Gimli\Events\publish_event;

/**
 * Main application container
 * 
 * @property Injector_Interface $Injector
 * @property Router $Router
 * @property Config $Config
 * 
 * @throws Route_Loader_Exception
 * @throws Gimli_Application_Exception
 */
class Application {

	/**
	 * @var non-empty-string $app_root
	 */
	protected string $app_root;

	/**
	 * @var Request $Request
	 */
	protected Request $Request;

	/**
	 * @var Config $Config
	 */
	public Config $Config;

	/**
	 * @var Injector_Interface $Injector
	 */
	public Injector_Interface $Injector;

	/**
	 * Create a new Application instance (no longer singleton)
	 * 
	 * @param non-empty-string        $app_root         The application root path
	 * @param array                   $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector         Injector instance
	 * @return Application
	 */
	public static function create(string $app_root, array $server_variables, ?Injector_Interface $Injector = NULL): Application {
		if (empty($app_root)) {
			throw new Gimli_Application_Exception('Application root path not set');
		}

		if (!is_dir($app_root)) {
			throw new Gimli_Application_Exception('Application root path not found: ' . $app_root);
		}

		if (empty($server_variables)) {
			throw new Gimli_Application_Exception('$_SERVER variables not set');
		}

		// Always create a new instance - no singleton
		return new Application($app_root, $server_variables, $Injector);
	}

	/**
	 * Constructor
	 *
	 * @param non-empty-string        $app_root         The application root path
	 * @param array                   $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector         Injector instance
	 */
	public function __construct(string $app_root, array $server_variables, ?Injector_Interface $Injector = NULL) {
		$this->app_root = $app_root;
		$this->registerCoreServices($server_variables, $Injector);
	}

	/**
	 * Set the Config object
	 *
	 * @param Config $Config Config object
	 * @return Application
	 */
	public function setConfig(Config $Config): Application {
		$this->Config = $Config;
		return $this;
	}

	/**
	 * Enable Latte template engine
	 *
	 * @return Application
	 * @throws Gimli_Application_Exception
	 */
	public function enableLatte(): Application {
		if (isset($this->Config) === FALSE) {
			throw new Gimli_Application_Exception('Enable Latte requires Config to be set');
		}

		$this->Injector->bind(Latte_Engine::class, fn() => new Latte_Engine($this->Config->template_base_dir, $this->app_root));
		return $this;
	}

	/**
	 * Register core services with the DI container
	 *
	 * @param array                   $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector         Injector instance
	 * @return void
	 */
	protected function registerCoreServices(array $server_variables, ?Injector_Interface $Injector = NULL): void {
		if (!is_null($Injector)) {
			$this->setCustomInjector($Injector);
		} else {
			$this->Injector = new Injector($this);
		}

		$this->Injector->bind(Request::class, fn() => new Request($server_variables));
		$this->Injector->bind(Router::class, fn() => new Router($this));
		$this->Injector->bind(Response::class, fn() => new Response);
		
		$this->Config = $this->Injector->resolve(Config::class);

		$this->Injector->register(Event_Manager::class, new Event_Manager);
		
		$this->Injector->bind(Session::class, function() {
			$App = Application_Registry::get();
			$Config = $App->Config;
			$session_config = $Config->get('session') ?? [];
			return new Session($session_config);
		});

		return;
	}

	/**
	 * Register the web routes
	 *
	 * @return void
	 * @throws Route_Loader_Exception
	 */
	protected function registerWebRoutes(): void {
		if ($this->Config->autoload_routes === FALSE) {
			return;
		}

		$route_directory = $this->Config->get('route_directory');
		if (empty($route_directory)) {
			throw new Route_Loader_Exception('Web route directory not set');
		}

		$full_route_path = rtrim($this->app_root, '/') . '/' . ltrim($route_directory, '/');
		if (!is_dir($full_route_path)) {
			throw new Route_Loader_Exception('Route directory not found: ' . $full_route_path);
		}

		$routes = glob($full_route_path . '*.php');

		if (empty($routes)) {
			throw new Route_Loader_Exception('No route files found in directory: ' . $full_route_path);
		}

		$this->loadRouteFiles($routes);
	}

	/**
	 * Register events
	 *
	 * @param array $events Events to register
	 * @return void
	 */
	protected function registerEvents(array $events): void {
		$Event_Manager = $this->Injector->resolve(Event_Manager::class);
		$Event_Manager->register($events);
	}

	/**
	 * Load custom route files
	 *
	 * @param array $routes Route files to load
	 * @return void
	 * @throws Route_Loader_Exception
	 */
	public function loadRouteFiles(array $routes): void {
		foreach ($routes as $route) {
			if (!file_exists($route)) {
				throw new Route_Loader_Exception('Route file not found: ' . $route);
			}

			if (!is_readable($route)) {
				throw new Route_Loader_Exception('Route file not readable: ' . $route);
			}

			include_once $route;
		}
	}

	/**
	 * Set the custom dependency injector
	 *
	 * @param Injector_Interface $Injector Injector instance
	 * @return void
	 */
	public function setCustomInjector(Injector_Interface $Injector): void {
		$this->Injector = $Injector;
	}

	/**
	 * Check if the application is running in CLI mode
	 *
	 * @return bool
	 */
	public function isCli(): bool {
		return \PHP_SAPI === 'cli';
	}

	/**
	 * Run the application
	 * 
	 * @return void
	 */
	public function run(): void {
		// Initialize session with proper configuration via Session class
		$this->Injector->resolve(Session::class);
		
		// might need to rethink this
		publish_event('gimli.application.start', ['time' => microtime(TRUE)]);
		if (isset($this->Config) === FALSE) {
			throw new Gimli_Application_Exception('Please set the Config object using setConfig');
		}
		$this->registerWebRoutes();
		if (!empty($this->Config->get('events'))) {
			$this->registerEvents($this->Config->events);
		}
		$routes          = Route::build();
		$Router          = $this->Injector->resolve(Router::class);
		$Router->Request = $this->Injector->resolve(Request::class);
		$Router->addRoutes($routes);
		$Router->run();
		publish_event('gimli.application.end', ['time' => microtime(TRUE)]);
		return;
	}

}