<?php
declare(strict_types=1);

namespace Gimli\Environment;

use Gimli\Application_Registry;

if (!function_exists('Gimli\Environment\get_config')) {
	/**
	 * Get the config.
	 *
	 * @return array
	 */
	function get_config(): array {
		return Application_Registry::get()->Config->getConfig();
	}
}

if (!function_exists('Gimli\Environment\get_config_value')) {
	/**
	 * get a value from config
	 *
	 * @param string $key the key to get
	 * @return mixed the value of the key
	 */
	function get_config_value(string $key): mixed {
		return Application_Registry::get()->Config->get($key);
	}
}

if (!function_exists('Gimli\Environment\config_has')) {
	/**
	 * check if a key exists in the config
	 *
	 * @param string $key the key to check
	 * @return bool the result of the check
	 */
	function config_has(string $key): bool {
		return Application_Registry::get()->Config->has($key);
	}
}