<?php
declare(strict_types=1);

namespace App\Injector;

use Gimli\Application;

if (!function_exists('resolve')) {
	/**
	 * Resolve the dependency.
	 *
	 * @param  string  $injector_key
	 * @return mixed
	 */
	function resolve(string $injector_key) {
		return Application::get()->Injector->resolve($injector_key);
	}
}

if (!function_exists('resolve_fresh')) {
	/**
	 * Resolve the dependency with a fresh instance.
	 *
	 * @param  string  $injector_key
	 * @return mixed
	 */
	function resolve_fresh(string $injector_key) {
		return Application::get()->Injector->resolveFresh($injector_key);
	}
}