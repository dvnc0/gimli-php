<?php
declare(strict_types=1);

namespace Gimli;

use Gimli\Application;
use Gimli\Exceptions\Gimli_Application_Exception;

/**
 * Application registry
 *
 * @package Gimli
 */
class Application_Registry {
	/**
	 * @var Application|null $current
	 */
	private static ?Application $current = NULL;

	/**
	 * Set the current application instance
	 *
	 * @param Application $app the application instance to set
	 * @return void
	 */
	public static function set(Application $app): void {
		self::$current = $app;
	}

	/**
	 * Get the current application instance
	 *
	 * @return Application
	 * @throws Gimli_Application_Exception
	 */
	public static function get(): Application {
		if (self::$current === NULL) {
			throw new Gimli_Application_Exception('No application instance registered');
		}
		return self::$current;
	}

	/**
	 * Clear the current application instance
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$current = NULL;
	}
} 