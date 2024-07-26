<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Middleware\Middleware_Base;

class Route {
	/**
	 * @var Route|null $instance
	 */
	private static ?Route $instance = null;

	/**
	 * @var string $current_group
	 */
	protected string $current_group = '';

	/**
	 * @var string $current_route
	 */
	protected string $current_route = '';

	/**
	 * @var string $current_type
	 */
	protected string $current_type = '';

	/**
	 * @var array $group_middleware
	 */
	protected array $group_middleware = [];

	/**
	 * @var array $routes
	 */
	protected array $routes = [];

	/**
	 * @var string $arg_name_pattern
	 */
	protected string $arg_name_pattern = '(#[a-zA-Z0-9_-]+)';

	/**
	 * Constructor
	 */
	private function __construct() {
		// do nothing
	}

	/**
	 * Get the instance of the Route class
	 *
	 * @return Route
	 */
	public static function getInstance(): Route {
		if (self::$instance === null) {
			self::$instance = new Route();
		}
		return self::$instance;
	}

	/**
	 * Add a GET route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function get(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('GET', $route, $callback);
		return $Route;
	}

	/**
	 * Add a POST route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function post(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('POST', $route, $callback);
		return $Route;
	}

	/**
	 * Add a PUT route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function put(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('PUT', $route, $callback);
		return $Route;
	}

	/**
	 * Add a PATCH route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function patch(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('PATCH', $route, $callback);
		return $Route;
	}

	/**
	 * Add a DELETE route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function delete(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('DELETE', $route, $callback);
		return $Route;
	}

	/**
	 * Add a route for any HTTP method
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function any(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('GET', $route, $callback);
		$Route->addRoute('POST', $route, $callback);
		$Route->addRoute('PUT', $route, $callback);
		$Route->addRoute('PATCH', $route, $callback);
		$Route->addRoute('DELETE', $route, $callback);
		return $Route;
	}

	/**
	 * Add a CLI route
	 *
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public static function cli(string $route, string|array|callable $callback): Route {
		$Route = static::getInstance();
		$Route->addRoute('CLI', $route, $callback);
		return $Route;
	}

	/**
	 * Add routes to a group
	 *
	 * @param  string   $group      group prefix
	 * @param  callable $callback   callback to add routes
	 * @param  array    $middleware routes middleware
	 * @return Route
	 */
	public static function group(string $group, callable $callback, array $middleware = []): Route {
		$Route = static::getInstance();
		$Route->addGroup($group, $callback, $middleware);
		return $Route;
	}

	/**
	 * Add a middleware to a route
	 *
	 * @param Middleware_Base $middleware Middleware instance
	 *
	 * @return Route
	 */
	public function addGroup(string $group, callable $callback, array $middleware = []) {
		$existing_group            = $this->current_group ?? '';
		$existing_group_middleware = $this->group_middleware ?? [];
		$this->current_group       = $this->current_group . $group;
		$this->group_middleware    = array_merge($this->group_middleware, $middleware);
		
		call_user_func($callback);
		
		$this->current_group    = $existing_group;
		$this->group_middleware = $existing_group_middleware;
		return $this;
	}

	/**
	 * Add a middleware to a route
	 *
	 * @param string $middleware Middleware class
	 *
	 * @return Route
	 */
	public function addMiddleware(string $middleware): object {
		$this->routes[$this->current_type][$this->current_route]['middleware'][] = $middleware;
		return $this;
	}

	/**
	 * Add a route
	 *
	 * @param string                $method   HTTP method
	 * @param string                $route    route
	 * @param string|callable|array $callback callback
	 *
	 * @return Route
	 */
	public function addRoute(string $method, string $route, string|array|callable $callback) {

		$arg_names = [];
		preg_match_all('/' . $this->arg_name_pattern . '/', $route, $matches);
		$arg_names = preg_replace('/\#/', '', $matches[1]);
		$route = preg_replace('/' . $this->arg_name_pattern . '/', '', $route);

		$route_with_group    = $this->current_group . $route;
		$this->current_route = $route_with_group;
		$this->current_type  = $method;

		$callback_formatted = $this->formatCallback($callback);
		
		$this->routes[$method][$route_with_group] = [
			'route' => $route_with_group,
			'handler' => $callback_formatted,
			'arg_names' => $arg_names,
		];

		if (!empty($this->group_middleware)) {
			foreach($this->group_middleware as $middleware) {
				$this->routes[$method][$route_with_group]['middleware'][] = $middleware;
			}
		}

		return $this;
	}

	/**
	 * Format a callback
	 *
	 * @param string|array|callable $callback callback
	 *
	 * @return string|array|callable
	 */
	protected function formatCallback(string|array|callable $callback): string|array|callable {
		if (is_array($callback) && count($callback) === 2 && is_string($callback[0]) && is_string($callback[1])) {
			$callback = implode('@', $callback);
		}
		return $callback;
	}

	/**
	 * Build the routes
	 *
	 * @return array
	 */
	public static function build(): array {
		$Route = static::getInstance();
		return $Route->routes;
	}

}