<?php
declare(strict_types=1);

namespace Gimli;

use Gimli\Injector\Injector_Interface;
use Gimli\Http\Request;
use Gimli\Router\Router;
use Gimli\Environment\Config;
use Gimli\Injector\Injector;
use Exception;
use Gimli\Router\Route;

/**
 * @property Injector_Interface $Injector
 * @property Router $Router
 * @property Config $Config
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
	 */
	public static function create(string $app_root, array $server_variables, ?Injector_Interface $Injector = null): Application {
		if (is_null(self::$instance)) {
			self::$instance = new Application($app_root, $server_variables, $Injector);
		}
		return self::$instance;
	}

	/**
	 * Get the instance of the Application class
	 *
	 * @return Application
	 * @throws Exception
	 */
	public static function get(): Application {
		if (is_null(self::$instance)) {
			throw new Exception('Application instance not created');
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
		$this->app_root  = $app_root;
		$this->registerCoreServices($server_variables, $Injector);
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
	}

	/**
	 * Register the web routes
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function registerWebRoutes(): void {
		if (!file_exists($this->app_root . $this->Config->web_route_file)) {
			throw new Exception('Web route file not found: ' . $this->app_root . $this->Config->web_route_file);
		}

		require_once $this->app_root . $this->Config->web_route_file;
	}

	/**
	 * Load custom route files
	 *
	 * @param array $routes Route files to load
	 * @return void
	 * @throws Exception
	 */
	public function loadRouteFiles(array $routes): void {
		foreach ($routes as $route) {
			if (!file_exists($this->app_root . '/' . $route)) {
				throw new Exception('Route file not found: ' . $this->app_root . '/' . $route);
			}
			require_once $this->app_root . '/' . $route;
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
		$this->registerWebRoutes();
		$routes = Route::build();
		$Router = $this->Injector->resolve(Router::class);
		$Router->Request = $this->Injector->resolve(Request::class);
		$Router->addRoutes($routes);
		$Router->run();
		return;
	}

}