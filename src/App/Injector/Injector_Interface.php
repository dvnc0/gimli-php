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
	 * @param string $class_name   class name
	 * @param array  $dependencies dependencies
	 * 
	 * @return object
	 */
	public function resolve(string $class_name, array $dependencies = []): object;

	/**
	 * resolves a fresh class instance
	 * 
	 * @param string $class_name   class name
	 * @param array  $dependencies dependencies
	 * 
	 * @return object
	 */
	public function resolveFresh(string $class_name, array $dependencies = []): object;

	/**
	 * check if a class exists
	 * 
	 * @param string $class_name class name
	 * 
	 * @return bool
	 */
	public function exists(string $key): bool;
}