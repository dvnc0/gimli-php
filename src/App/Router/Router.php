<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Application;
use Gimli\Router\Dispatch;
use Gimli\Injector\Injector;
use Gimli\Http\Request;
use Gimli\Middleware\Middleware_Base;
use Gimli\Middleware\Middleware_Response;
use Exception;
use ReflectionNamedType;

/**
 * Router
 *
 * @property Application $Application
 * @property Injector $Injector
 * @property Request $Request
 */
class Router {
	/**
	 * @var Application $Application
	 */
	protected Application $Application;

	/**
	 * @var array $routes
	 */
	protected array $routes = [];

	/**
	 * @var bool $trailing_slash_matters
	 */
	protected bool $trailing_slash_matters = TRUE;

	/**
	 * @var Request $Request
	 */
	public Request $Request;

	/**
	 * @var array $patterns
	 */
	protected array $patterns = [
		':all' => "([^/]+)",
		':alpha' => "([A-Za-z_-]+)",
		':alphanumeric' => "([\w-]+)",
		':integer' => "([0-9_-]+)",
		':numeric' => "([0-9_-.]+)",
		':id' => "([0-9_-]+)",
		':slug' => "([A-Za-z0-9_-]+)",
	];

	/**
	 * @var Dispatch $Dispatch
	 */
	protected Dispatch $Dispatch;

	/**
	 * @var array $allowed_methods
	 */
	protected array $allowed_methods = [ 
		"GET",
		"PUT",
		"POST",
		"DELETE",
		"PATCH"
	];
	
	/**
	 * Constructor
	 * 
	 * @param Application $Application Application
	 * 
	 * @return void
	 */
	public function __construct(Application $Application) {
		$this->Application = $Application;
		$this->Dispatch    = $this->Injector->resolve(Dispatch::class);
	}

	/**
	 * add routes
	 * 
	 * @param array $routes routes to add
	 * 
	 * @return void
	 * @throws Exception
	 */
	public function addRoutes(array $routes): void {
		if (empty($routes)) {
			throw new Exception("No routes provided");
		}

		$this->routes = $routes;
	}

	/**
	 * run the router
	 * 
	 * @return void
	 */
	public function run() {
		$search_keys   = array_keys($this->patterns);
		$replace_regex = array_values($this->patterns);
		$uri           = explode('?', $this->Request->REQUEST_URI)[0];

		if ($uri[strlen($uri) - 1] === '/' && !$this->trailing_slash_matters) {
			$uri                        = substr($uri, 0, strlen($uri) - 1);
			$this->Request->REQUEST_URI = $uri;
		} 
		
		$type = $this->Request->REQUEST_METHOD;

		if (!in_array($type, $this->allowed_methods)) {
			throw new Exception("Request method {$type} not allowed");
		}
		
		$route_match = [];

		foreach($this->routes[$type] as $route) {

			if ($route === $uri) {
				$route_match = $route;
				break;
			}

			$possible_route = str_replace($search_keys, $replace_regex, $route['route']);
			if (preg_match('#^' . $possible_route . '$#', $uri, $route_match)) {
				$route_match['args']       = array_slice($route_match, 1);
				if (!empty($route['arg_names'])) {
					$route_match['args']       = array_combine($route['arg_names'], $route_match['args']);
				}
				$route_match['route_info'] = $route;
				break;
			}
		}
		
		if (empty($route_match)) {
			echo "404 page not found";
			return;
		}

		$this->Request->route_data = $route_match;

		if (!empty($route_match['route_info']['middleware'])) {
			foreach($route_match['route_info']['middleware'] as $middleware) {
				$middleware_response = $this->callMiddleware($middleware);
				if (!$middleware_response->success) {
					header("Location: " . $middleware_response->forward);
				}
			}
		}

		if (is_callable($route_match['route_info']['handler'])) {
			call_user_func_array($route_match['route_info']['handler'], $route_match['args'] ?: []);
			return;
		}

		// if no @ add @__invoke
		if (strpos($route_match['route_info']['handler'], '@') === FALSE) {
			$route_match['route_info']['handler'] .= '@__invoke';
		}

		[$class_name, $method] = explode('@', $route_match['route_info']['handler']);
		$class_to_call         = $this->Injector->resolve($class_name);

		$method_args = $this->getArgumentsForMethod($class_to_call, $method, $route_match['args']);

		$response = call_user_func_array([$class_to_call, $method], $method_args);

		$this->Dispatch->dispatch($response);

		return;

	}

	/**
	 * get arguments for method
	 * 
	 * @param object $class_to_call class to call
	 * @param string $method method to call
	 * @param array $route_match_args route match args
	 * 
	 * @return array
	 */
	protected function getArgumentsForMethod(object $class_to_call, string $method, array $route_match_args): array {
		$method_args = (new \ReflectionMethod($class_to_call, $method))->getParameters();

		$method_args_types = [];
		
		foreach($method_args as $arg) {
			$type = $arg->getType();
        	if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$method_args_types[] = $type->getName();
			}
			else {
				$method_args_types[] = null;
			}

			continue;
		}
		
		foreach($method_args_types as $key => $type) {
			if ($type === null) {
				$method_args[$key] = $route_match_args;
			} else {
				$method_args[$key] = $this->Injector->resolve($type);
			}
		}

		return $method_args;
	}

	/**
	 * call a middleware
	 * 
	 * @param Middleware_Base $Middleware middleware to call
	 * 
	 * @return Middleware_Response
	 * @throws Exception
	 */
	protected function callMiddleware(Middleware_Base $Middleware): Middleware_Response {
		if (!$Middleware instanceof Middleware_Base) {	
			throw new Exception("Middleware was not an instance of Middleware_Base: " . $Middleware);
		}

		return $Middleware->process();
	}

	/**
	 * magic method
	 * 
	 * @param non-empty-string $name property name
	 * 
	 * @return object
	 */
	public function __get(string $name) {
		if (property_exists($this, $name)) {
			return $this->{$name};
		}
		return $this->Application->{$name};
	}
}