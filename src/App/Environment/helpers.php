<?php
declare(strict_types=1);

namespace App\Environment;

use Gimli\Application;

if (!function_exists('get_config')) {
	/**
	 * Get the config.
	 *
	 * @return array
	 */
	function get_config(): array {
		return Application::get()->Config->getConfig();
	}
}

if (!function_exists('get_config_value')) {
	/**
	 * get a value from config
	 * @param string $key
	 *
	 * @return mixed
	 */
	function get_config_value(string $key): mixed {
		return Application::get()->Config->get($key);
	}
}

if (!function_exists('config_has')) {
	/**
	 * check if a key exists in the config
	 * @param string $key
	 *
	 * @return string
	 */
	function config_has(string $key): bool {
		return Application::get()->Config->has($key);
	}
}