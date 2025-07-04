<?php
declare(strict_types=1);

namespace Gimli\Injector;

use Gimli\Application;
use Gimli\Application_Registry;

if (!function_exists('Gimli\Injector\resolve')) {
	/**
	 * Resolve the dependency.
	 * 
	 * @template T of object
	 * @param  class-string<T> $injector_key
	 * @param  array           $args
	 * @return T
	 */
	function resolve(string $injector_key, array $args = [], ?Application $app = NULL) {
		$Application = $app ?? Application_Registry::get();
		return $Application->Injector->resolve($injector_key, $args);
	}
}

if (!function_exists('Gimli\Injector\resolve_fresh')) {
	/**
	 * Resolve the dependency with a fresh instance.
	 *
	 * @template T of object
	 * @param  class-string<T> $injector_key
	 * @param  array           $args
	 * @return T
	 */
	function resolve_fresh(string $injector_key, array $args = [], ?Application $app = NULL) {
		$Application = $app ?? Application_Registry::get();
		return $Application->Injector->resolveFresh($injector_key, $args);
	}
}

if (!function_exists('Gimli\Injector\call_method')) {
	/**
	 * Resolve a class and call a specific method on it.
	 *
	 * @template T of object
	 * @param  class-string<T> $class_name
	 * @param  string          $method_name
	 * @param  array           $method_args
	 * @param  array           $dependencies dependencies for class resolution
	 * @return mixed
	 */
	function call_method(string $class_name, string $method_name, array $method_args = [], array $dependencies = []) {
		return Application_Registry::get()->Injector->call($class_name, $method_name, $method_args, $dependencies);
	}
}

if (!function_exists('Gimli\Injector\extend_class')) {
	/**
	 * Extend a resolved class with additional functionality.
	 *
	 * @template T of object
	 * @param  class-string<T> $class_name
	 * @param  callable        $callback
	 * @param  array           $dependencies dependencies for class resolution
	 * @return T
	 */
	function extend_class(string $class_name, callable $callback, array $dependencies = []): object {
		return Application_Registry::get()->Injector->extends($class_name, $callback, $dependencies);
	}
}

if (!function_exists('Gimli\Injector\bind')) {
	/**
	 * Bind a class to the injector.
	 *
	 * @template T of object
	 * @param class-string<T> $class_name the class name to bind
	 * @param callable        $callback   the callback to bind
	 * 
	 * @return void
	 */
	function bind(string $class_name, callable $callback, ?Application $app = NULL): void {
		$Application = $app ?? Application_Registry::get();
		$Application->Injector->bind($class_name, $callback);
	}
}

if (!function_exists('Gimli\Injector\injector')) {
	/**
	 * Get the injector instance.
	 *
	 * @return Injector_Interface the injector instance
	 */
	function injector(): Injector_Interface {
		return Application_Registry::get()->Injector;
	}
}