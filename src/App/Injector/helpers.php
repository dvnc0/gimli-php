<?php
declare(strict_types=1);

namespace Gimli\Injector;

use Gimli\Application;

if (!function_exists('Gimli\Injector\resolve')) {
	/**
	 * Resolve the dependency.
	 *
	 * @param  string  $injector_key
	 * @param  array   $args
	 * @return mixed
	 */
	function resolve(string $injector_key, array $args = []) {
		return Application::get()->Injector->resolve($injector_key, $args);
	}
}

if (!function_exists('Gimli\Injector\resolve_fresh')) {
	/**
	 * Resolve the dependency with a fresh instance.
	 *
	 * @param  string  $injector_key
	 * @param  array   $args
	 * @return mixed
	 */
	function resolve_fresh(string $injector_key, array $args = []) {
		return Application::get()->Injector->resolveFresh($injector_key, $args);
	}
}