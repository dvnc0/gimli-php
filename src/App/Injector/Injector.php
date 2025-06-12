<?php
declare(strict_types=1);

namespace Gimli\Injector;

use ReflectionClass;
use Gimli\Application;
use Gimli\Injector\Injector_Interface;

class Injector implements Injector_Interface {
	
	/**
	 * @var array<string, object> $resolved_classes
	 */
	protected array $resolved_classes = [];

	/**
	 * @var array<string, object> $registered_classes
	 */
	protected array $registered_classes = [];

	/**
	 * @var array<string, callable> $bindings
	 */
	protected array $bindings = [];

	/**
	 * @var Application $Application
	 */
	protected Application $Application;

	/**
	 * @var array $resolving
	 */
	protected array $resolving = [];

	/**
	 * Constructor
	 *
	 * @param Application $Application        Application
	 * @param array       $registered_classes registered classes
	 */
	public function __construct(Application $Application, array $registered_classes = []) {
		$this->registered_classes = $registered_classes;
		$this->Application        = $Application;
	}

	/**
	 * Registers a class instance
	 *
	 * @param string $class_name class name
	 * @param object $instance   instance
	 * @return void
	 */
	public function register(string $class_name, object $instance): void {
		$this->registered_classes[$class_name] = $instance;
	}

	/**
	 * Binds a class to a creation method
	 *
	 * @param string   $class_name class name
	 * @param callable $callback   callback
	 * @return void
	 */
	public function bind(string $class_name, callable $callback): void {
		$this->bindings[$class_name] = $callback;
	}

	/**
	 * Resolves a class instance
	 *
	 * @template T
	 * @param  class-string<T>  $class_name
	 * @param  array   $dependencies dependencies
	 * @return T
	 */
	public function resolve(string $class_name, array $dependencies = []): object {
		if (!empty($this->resolved_classes[$class_name])) {
			return $this->resolved_classes[$class_name];
		}

		if (!empty($this->registered_classes[$class_name])) {
			return $this->registered_classes[$class_name];
		}

		if (!empty($this->bindings[$class_name])) {
			$callback = $this->bindings[$class_name];
			$instance = call_user_func($callback);
			$this->resolved_classes[$class_name] = $instance;
			return $instance;
		}

		if (isset($this->resolving[$class_name])) {
			throw new \RuntimeException("Circular dependency detected for class: {$class_name}");
		}

		return $this->createFreshInstance($class_name, $dependencies);
	}

	/**
	 * Resolves a fresh class instance
	 *
	 * @template T
	 * @param  class-string<T>  $class_name
	 * @param  array   $dependencies dependencies
	 * @return T
	 */
	public function resolveFresh(string $class_name, array $dependencies = []): object {
		if (!empty($this->bindings[$class_name])) {
			$callback = $this->bindings[$class_name];
			return call_user_func($callback);
		}
		
		return $this->createFreshInstance($class_name, $dependencies);
	}

	/**
	 * Checks if a class exists
	 *
	 * @param string $key class name
	 * @return bool
	 */
	public function exists(string $key): bool {
		return !empty($this->resolved_classes[$key]) || !empty($this->registered_classes[$key]) || !empty($this->bindings[$key]);
	}

	/**
	 * Creates a fresh class instance
	 *
	 * @template T
	 * @param  class-string<T>  $class_name
	 * @param  array   $dependencies dependencies
	 * @return T
	 */
	protected function createFreshInstance(string $class_name, array $dependencies): object {
		$this->resolving[$class_name] = true;

		$dependencies[Application::class] = $this->Application;
		$dependencies[Injector::class]    = $this;

		$reflection  = new ReflectionClass($class_name);
		$constructor = $reflection->getConstructor();

		if (empty($constructor)) {
			$instance                            = new $class_name();
			$this->resolved_classes[$class_name] = $instance;

			unset($this->resolving[$class_name]);
			return $instance;
		}

		$constructor_params = $constructor->getParameters();
		$constructor_args   = [];

		foreach ($constructor_params as $param) {
			$param_type = $param->getType();
			$param_name = $param->getName();
			$param_class = '';

			if ($param_type instanceof \ReflectionNamedType && !$param_type->isBuiltin()) {
				$param_class = $param_type->getName();
			}

			if (!empty($param_class)) {
				if (!empty($dependencies[$param_class])) {
					$constructor_args[] = $dependencies[$param_class];
					continue;
				} 
				
				if (class_exists($param_class)) {
					$constructor_args[] = $this->resolve($param_class);
					continue;
				}
			}

			if (!empty($param_name) && !empty($dependencies[$param_name])) {
				$constructor_args[] = $dependencies[$param_name];
				continue;
			}

			if ($param->isDefaultValueAvailable()) {
				$constructor_args[] = $param->getDefaultValue();
				continue;
			}

			if ($param->allowsNull()) {
				$constructor_args[] = NULL;
				continue;
			}

			continue;
		}

		$instance = $reflection->newInstanceArgs($constructor_args);

		$this->resolved_classes[$class_name] = $instance;

		unset($this->resolving[$class_name]);
		return $instance;
			
	}

	/**
	 * Resolves a class and calls a specific method
	 *
	 * @template T
	 * @param  class-string<T>  $class_name
	 * @param  string  $method_name
	 * @param  array   $method_args
	 * @param  array   $dependencies dependencies for class resolution
	 * @return mixed
	 */
	public function call(string $class_name, string $method_name, array $method_args = [], array $dependencies = []): mixed {
		$instance = $this->resolve($class_name, $dependencies);
		
		if (!method_exists($instance, $method_name)) {
			throw new \BadMethodCallException("Method '{$method_name}' does not exist on class '{$class_name}'");
		}

		$reflection_method = new \ReflectionMethod($instance, $method_name);
		$method_parameters = $reflection_method->getParameters();
		$resolved_args = [];

		// Auto-resolve method parameters with dependency injection
		foreach ($method_parameters as $index => $param) {
			$param_type = $param->getType();
			$param_name = $param->getName();

			// Check if argument was explicitly provided
			if (array_key_exists($index, $method_args)) {
				$resolved_args[] = $method_args[$index];
				continue;
			}

			if (array_key_exists($param_name, $method_args)) {
				$resolved_args[] = $method_args[$param_name];
				continue;
			}

			// Try to resolve by typeo
			if ($param_type && !$param_type->isBuiltin()) {
				$param_class = $param_type->getName();
				$resolved_args[] = $this->resolve($param_class);
				continue;
			}

			// Use default value if available
			if ($param->isDefaultValueAvailable()) {
				$resolved_args[] = $param->getDefaultValue();
				continue;
			}

			// Allow null if parameter allows it
			if ($param->allowsNull()) {
				$resolved_args[] = null;
				continue;
			}

			throw new \InvalidArgumentException("Cannot resolve parameter '{$param_name}' for method '{$method_name}' on class '{$class_name}'");
		}

		return call_user_func_array([$instance, $method_name], $resolved_args);
	}

	/**
	 * Extends a resolved class with additional functionality
	 *
	 * @template T
	 * @param  class-string<T>  $class_name
	 * @param  callable $callback
	 * @param  array    $dependencies dependencies for class resolution
	 * @return T
	 */
	public function extends(string $class_name, callable $callback, array $dependencies = []): object {
		$instance = $this->resolve($class_name, $dependencies);
		
		// Call the callback with the instance and return the result
		$result = call_user_func($callback, $instance);
		
		// If callback returns an object, use that as the extended instance
		if (is_object($result)) {
			return $result;
		}
		
		// Otherwise, return the original instance (callback modified it in place)
		return $instance;
	}

}