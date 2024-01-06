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
class Router{
	protected Application $Application;
	public array $routes                = [];
	public string $current_route        = '';
	public string $current_group        = '';
	public string $current_type         = '';
	public string $group_middleware     = '';
	public bool $trailing_slash_matters = TRUE;
	public array $patterns              = [
		':all' => "([^/]+)",
		':alpha' => "([A-Za-z_-]+)",
		':alphanumeric' => "([\w-]+)",
		':integer' => "([0-9_-]+)",
		':numeric' => "([0-9_-.]+)",
		':id' => "([0-9_-]+)",
	];
	protected Dispatch $Dispatch;
	protected string $allowed_methods = "GET|PUT|POST|DELETE|PATCH";
	

	public function __construct(Application $Application){
		$this->Application = $Application;
		$this->Dispatch = $this->Injector->resolve(Dispatch::class);
	}

	public function get(string $route, string|callable $callback) {
		$this->addRoute('GET', $route, $callback);
		return $this;
	}

	public function post(string $route, string|callable $callback) {
		$this->addRoute('POST', $route, $callback);
		return $this;
	}

	public function put(string $route, string|callable $callback) {
		$this->addRoute('PUT', $route, $callback);
		return $this;
	}

	public function patch(string $route, string|callable $callback) {
		$this->addRoute('PATCH', $route, $callback);
		return $this;
	}

	public function delete(string $route, string|callable $callback) {
		$this->addRoute('DELETE', $route, $callback);
		return $this;
	}

	public function options(string $route, string|callable $callback) {
		$this->addRoute('OPTIONS', $route, $callback);
		return $this;
	}

	public function any(string $route, string|callable $callback) {
		$this->addRoute('GET', $route, $callback);
		$this->addRoute('POST', $route, $callback);
		$this->addRoute('PUT', $route, $callback);
		$this->addRoute('PATCH', $route, $callback);
		$this->addRoute('DELETE', $route, $callback);
		$this->addRoute('OPTIONS', $route, $callback);
		return $this;
	}

	public function addRoute(string $method, string $route, string|callable $callback) {
		$route_with_group    = $this->current_group . $route;
		$this->current_route = $route_with_group;
		$this->current_type  = $method;
		
		$this->routes[$method][$route_with_group] = [
			'route' => $route_with_group,
			'handler' => $callback,
		];

		if (!empty($this->group_middleware)) {
			$this->addMiddleware($this->group_middleware);
		}
	}

	public function addMiddleware(string $middleware): object {
		$this->routes[$this->current_type][$this->current_route]['middleware'] = $middleware;
		return $this;
	}

	public function addGroupMiddleware(string $middleware): object {
		$this->group_middleware = $middleware;
		return $this;
	}

	public function run() {
		$search_keys   = array_keys($this->patterns);
		$replace_regex = array_values($this->patterns);
		$uri           = explode('?', $this->Request->REQUEST_URI)[0];

		if ($uri[strlen($uri) - 1] === '/' && !$this->trailing_slash_matters) {
			$uri                        = substr($uri, 0, strlen($uri) - 1);
			$this->Request->REQUEST_URI = $uri;
		} 
		
		$type = $this->Request->REQUEST_METHOD;

		if (!in_array($type, explode('|', $this->allowed_methods))) {
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
		[$className, $method] = explode('@', $route_match['route_info']['handler']);
		$class_to_call        = $this->Injector->resolve($className);

		$response_object = new Response;
		$response = call_user_func_array([$class_to_call, $method], [$this->Request, $response_object, ...$route_match['args']]);

		$this->Dispatch->dispatch($response);

		return;

	}

	protected function callMiddleware(string $Middleware): Middleware_Response {
		$instance = $this->Injector->resolve($Middleware);

		if (!$instance instanceof Middleware_Base) {	
			throw new Exception("Middleware was not an instance of Middleware_Base: " . $Middleware);
		}

		return $instance->process();
	}

	public function __get($name) {
		if (property_exists($this, $name)) {
			return $this->{$name};
		}
		return $this->Application->{$name};
	}
}