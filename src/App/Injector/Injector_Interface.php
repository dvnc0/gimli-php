<?php
declare(strict_types=1);

namespace Gimli\Injector;

interface Injector_Interface {
	/**
	 * register a class instance
	 * 
	 * @param string $class_name class name
	 * @param object $instance   instance of class
	 * 
	 * @return void
	 */
	public function register(string $class_name, object $instance): void;

	/**
	 * bind a class to a creation method
	 * 
	 * @param string   $class_name class name
	 * @param callable $callback   callback
	 * 
	 * @return void
	 */
	public function bind(string $class_name, callable $callback): void;

	/**
	 * resolves a class instance
	 * 
	 * @template T of object
	 * @param class-string<T> $class_name   class name
	 * @param array           $dependencies dependencies
	 * 
	 * @return T
	 */
	public function resolve(string $class_name, array $dependencies = []): object;

	/**
	 * resolves a fresh class instance
	 * 
	 * @template T of object
	 * @param class-string<T> $class_name   class name
	 * @param array           $dependencies dependencies
	 * 
	 * @return T
	 */
	public function resolveFresh(string $class_name, array $dependencies = []): object;

	/**
	 * check if a class exists
	 * 
	 * @param string $key class name
	 * 
	 * @return bool
	 */
	public function exists(string $key): bool;

	/**
	 * resolves a class and calls a specific method
	 * 
	 * @param string $class_name   class name
	 * @param string $method_name  method name to call
	 * @param array  $method_args  arguments to pass to the method
	 * @param array  $dependencies dependencies for class resolution
	 * 
	 * @return mixed
	 */
	public function call(string $class_name, string $method_name, array $method_args = [], array $dependencies = []): mixed;

	/**
	 * extends a resolved class with additional functionality
	 * 
	 * @template T of object
	 * @param class-string<T> $class_name   class name to extend
	 * @param callable        $callback     callback to extend the instance
	 * @param array           $dependencies dependencies for class resolution
	 * 
	 * @return T
	 */
	public function extends(string $class_name, callable $callback, array $dependencies = []): object;
}