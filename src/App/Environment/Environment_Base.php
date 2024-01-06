<?php
declare(strict_types=1);

namespace Gimli\Environment;

class Environment_Base
{
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
	 * @param array $environment_settings
	 * @return void
	 */
	public function setEnvironmentSettings(array $environment_settings): void { 
		foreach (get_class_vars(get_class($this)) as $key => $value) {
			$this->{$key} = $environment_settings[$key] ?? $value;
		}
	}

	public function get(string $key) {
		if (strpos($key, '.') !== false) {
			$keys = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				$object = $object->{$key};
			}
			return $object;
		}

		return $this->{$key};
	}

	public function set(string $key, $value): void {
		if (strpos($key, '.') !== false) {
			$keys = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				$object = $object->{$key};
			}
			$object = $value;
			return;
		}
		$this->{$key} = $value;
	}

	public function has(string $key): bool {
		if (strpos($key, '.') !== false) {
			$keys = explode('.', $key);
			$object = $this;
			foreach ($keys as $key) {
				if (!isset($object->{$key})) {
					return false;
				}
				$object = $object->{$key};
			}
			return true;
		}

		return isset($this->{$key});
	}
}