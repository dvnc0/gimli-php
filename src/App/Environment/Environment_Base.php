<?php
declare(strict_types=1);

namespace Gimli\Environment;

use Exception;

class Environment_Base {
	/**
	 * Construct
	 *
	 * @param array $environment_settings The settings to apply to our environment
	 */
	public function __construct(array $environment_settings = []) {
		$this->setEnvironmentSettings($environment_settings);
	}

	/**
	 * Sets environment settings from an array if they are declared settings.
	 *
	 * @param array $environment_settings environment settings
	 * @return void
	 */
	public function setEnvironmentSettings(array $environment_settings): void {
		foreach (get_class_vars(get_class($this)) as $key => $value) {
			$this->{$key} = $environment_settings[$key] ?? $value;
		}
	}

	/**
	 * Gets a value from the environment
	 *
	 * @param string $key key to get
	 * @return mixed
	 */
	public function get(string $key) {
		if (strpos($key, '.') !== FALSE) {
			$keys   = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				if (is_array($object)) {
					$object = $object[$key];
					continue;
				}
				$object = $object->{$key};
			}
			return $object;
		}

		return $this->{$key};
	}

	/**
	 * Sets a value in the environment
	 *
	 * @param string $key   key to set
	 * @param mixed  $value value to set
	 * @return void
	 */
	public function set(string $key, $value): void {
		// check if key exists as class prop
		if (strpos($key, '.') === false && !property_exists($this, $key)) {
			throw new Exception("{$key} is not a valid environment setting.");
		}

		if (strpos($key, '.') !== FALSE) {
			$keys   = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				if (is_array($object)) {
					if (!isset($object[$key])) {
						throw new Exception("{$key} is not a valid environment setting.");
					}
					$object = &$object[$key];
					continue;
				}
				if (!property_exists($object, $key)) {
					throw new Exception("{$key} is not a valid environment setting.");
				}
				$object = &$object->{$key};
			}

			$object = $value;
			return;
		}

		$this->{$key} = $value;
	}

	/**
	 * Checks if a value exists in the environment
	 *
	 * @param string $key key to check
	 * @return bool
	 */
	public function has(string $key): bool {
		if (strpos($key, '.') !== FALSE) {
			$keys   = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				if (is_array($object)) {
					if (!isset($object[$key])) {
						return FALSE;
					}
					$object = $object[$key];
					continue;
				}

				if (!isset($object->{$key})) {
					return FALSE;
				}

				$object = $object->{$key};
			}
			return TRUE;
		}

		return isset($this->{$key});
	}
}