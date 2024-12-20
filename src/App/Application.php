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
	 * @var Application|null $instance
	 */
	protected static ?Application $instance = null;

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
	 * Create the instance of the Application class
	 *
	 * @param non-empty-string $app_root         The application root path
	 * @param array            $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector Injector instance
	 * @return Application
	 * @throws Gimli_Application_Exception
	 */
	public static function create(string $app_root, array $server_variables, ?Injector_Interface $Injector = null): Application {

		if (empty($app_root)) {
			throw new Gimli_Application_Exception('Application root path not set');
		}

		if (!is_dir($app_root)) {
			throw new Gimli_Application_Exception('Application root path not found: ' . $app_root);
		}

		if (empty($server_variables)) {
			throw new Gimli_Application_Exception('$_SERVER variables not set');
		}

		if (is_null(self::$instance)) {
			self::$instance = new Application($app_root, $server_variables, $Injector);
		}
		return self::$instance;
	}

	/**
	 * Get the instance of the Application class
	 *
	 * @return Application
	 * @throws Gimli_Application_Exception
	 */
	public static function get(): Application {
		if (is_null(self::$instance)) {
			throw new Gimli_Application_Exception('Application instance not created');
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param non-empty-string $app_root         The application root path
	 * @param array            $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector Injector instance
	 */
	protected function __construct(string $app_root, array $server_variables, ?Injector_Interface $Injector = null) {

		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		
		$this->app_root  = $app_root;
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
		if (isset($this->Config) === false) {
			throw new Gimli_Application_Exception('Enable Latte requires Config to be set');
		}

		$this->Injector->bind(Latte_Engine::class, fn() => new Latte_Engine($this->Config->template_base_dir, $this->app_root));
		return $this;
	}

	/**
	 * Register core services with the DI container
	 *
	 * @param array $server_variables $_SERVER values
	 * @param Injector_Interface|null $Injector Injector instance
	 * @return void
	 */
	protected function registerCoreServices(array $server_variables, ?Injector_Interface $Injector = null): void {
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
		$this->Injector->register(Session::class, new Session);

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

		// I have no clue why this needs to be like this here but nowhere else.
		// Without using get() it throws a fatal I need to look into this more.
		if (empty($this->Config->get('route_directory'))) {
			throw new Route_Loader_Exception('Web route directory not set ' . $this->Config->route_directory);
		}

		if(!is_dir($this->app_root . $this->Config->route_directory)) {
			throw new Route_Loader_Exception('Route directory not found: ' . $this->app_root . $this->Config->route_directory);
		}

		$routes = glob($this->app_root . $this->Config->route_directory . '*.php');

		if (empty($routes)) {
			throw new Route_Loader_Exception('No route files found in directory: ' . $this->app_root . $this->Config->route_directory);
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

			require_once $route;
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
		// might need to rethink this
		publish_event('gimli.application.start', ['time' => microtime(true)]);
		if (isset($this->Config) === false) {
			throw new Gimli_Application_Exception('Please set the Config object using setConfig');
		}
		$this->registerWebRoutes();
		if (!empty($this->Config->get('events'))) {
			$this->registerEvents($this->Config->events);
		}
		$routes = Route::build();
		$Router = $this->Injector->resolve(Router::class);
		$Router->Request = $this->Injector->resolve(Request::class);
		$Router->addRoutes($routes);
		$Router->run();
		publish_event('gimli.application.end', ['time' => microtime(true)]);
		return;
	}

	/**
	 * Static helper for running the application
	 *
	 * @return void
	 * @throws Gimli_Application_Exception
	 */
	public static function start(): void {
		if (self::$instance === null) {
			throw new Gimli_Application_Exception('Application instance not created');
		}

		self::$instance->run();
	}

}