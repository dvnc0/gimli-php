<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Application;
use Gimli\Router\Dispatch;
use Gimli\Injector\Injector;
use Gimli\Http\Request;
use Gimli\Http\Response;
use Gimli\Middleware\Middleware_Base;
use Gimli\Middleware\Middleware_Response;
use Exception;

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
	 * @var string $current_route
	 */
	protected string $current_route = '';

	/**
	 * @var string $current_group
	 */
	protected string $current_group = '';

	/**
	 * @var string $current_type
	 */
	protected string $current_type = '';

	/**
	 * @var array $group_middleware
	 */
	protected array $group_middleware = [];

	/**
	 * @var bool $trailing_slash_matters
	 */
	protected bool $trailing_slash_matters = TRUE;

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
	 * add a route group
	 * 
	 * @param string   $group      group
	 * @param callable $callback   the callback
	 * @param array    $middleware the middleware
	 * 
	 * @return Router
	 */
	public function addGroup(string $group, callable $callback, array $middleware = []) {
		$existing_group            = $this->current_group ?? '';
		$existing_group_middleware = $this->group_middleware ?? [];
		$this->current_group       = $this->current_group . $group;
		$this->group_middleware    = array_merge($this->group_middleware, $middleware);
		
		$callback();
		
		$this->current_group    = $existing_group;
		$this->group_middleware = $existing_group_middleware;
		return $this;
	}

	/**
	 * add a GET route
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function get(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);
		$this->addRoute('GET', $route, $callback);
		return $this;
	}

	/**
	 * add a POST route
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function post(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);
		$this->addRoute('POST', $route, $callback);
		return $this;
	}

	/**
	 * add a PUT route
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function put(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);
		$this->addRoute('PUT', $route, $callback);
		return $this;
	}

	/**
	 * add a PATCH route
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function patch(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);
		$this->addRoute('PATCH', $route, $callback);
		return $this;
	}

	/**
	 * add a DELETE route
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function delete(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);
		$this->addRoute('DELETE', $route, $callback);
		return $this;
	}

	/**
	 * add GET, POST, PUT, PATCH, DELETE routes
	 * 
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 * 
	 * @return Router
	 */
	public function any(string $route, string|callable|array $callback) {
		$callback = $this->getFormattedCallbackForRoute($callback);

		foreach ($this->allowed_methods as $method) {
			$this->addRoute($method, $route, $callback);
		}

		return $this;
	}

	/**
	 * Return a formatted callback string
	 *
	 * @param  string|callable|array $callback the formatted callback
	 * @return string
	 */
	protected function getFormattedCallbackForRoute(string|callable|array $callback): string {
		if (is_array($callback) && count($callback) === 2 && is_string($callback[0]) && is_string($callback[1])) {
			$callback = implode('@', $callback);
		}
		return $callback;
	}

	/**
	 * add a route
	 * 
	 * @param string          $method   method for route
	 * @param string          $route    route
	 * @param string|callable $callback callback
	 * 
	 * @return void
	 */
	public function addRoute(string $method, string $route, string|callable $callback) {
		$route_with_group    = $this->current_group . $route;
		$this->current_route = $route_with_group;
		$this->current_type  = $method;
		
		$this->routes[$method][$route_with_group] = [
			'route' => $route_with_group,
			'handler' => $callback,
		];

		if (!empty($this->group_middleware)) {
			foreach($this->group_middleware as $middleware) {
				$this->routes[$method][$route_with_group]['middleware'] = $middleware;
			}
		}
	}

	/**
	 * add a middleware to a route
	 * 
	 * @param Middleware_Base $middleware middleware to add
	 * 
	 * @return Router
	 */
	public function addMiddleware(Middleware_Base $middleware): object {
		$this->routes[$this->current_type][$this->current_route]['middleware'] = $middleware;
		return $this;
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
			$middleware_response = $this->callMiddleware($route_match['route_info']['middleware']);
			if (!$middleware_response->success) {
				header("Location: " . $middleware_response->forward);
			}
		}

		if (is_callable($route_match['route_info']['handler'])) {
			call_user_func_array($route_match['route_info']['handler'], $route_match['args'] ?: []);
			return;
		}
		[$class_name, $method] = explode('@', $route_match['route_info']['handler']);
		$class_to_call        = $this->Injector->resolve($class_name);

		$response_object = new Response;
		$response        = call_user_func_array([$class_to_call, $method], [$this->Request, $response_object, ...$route_match['args']]);

		$this->Dispatch->dispatch($response);

		return;

	}

	/**
	 * call a middleware
	 * 
	 * @param Middleware_Base $Middleware middleware to call
	 * 
	 * @return Middleware_Response
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