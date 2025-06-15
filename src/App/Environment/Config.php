<?php
declare(strict_types=1);

namespace Gimli\Environment;

/**
 * @property bool $is_live
 * @property bool $is_dev
 * @property bool $is_staging
 * @property bool $is_cli
 * @property bool $is_unit_test
 * @property array $database
 * @property string $web_route_file
 * @property bool $autoload_routes
 * @property string $route_directory
 * @property bool $enable_latte
 * @property string $template_base_dir
 */
class Config {

	/**
	 * The base config array defaults
	 *
	 * @var array{
	 * 		is_live: bool,
	 * 		is_dev: bool,
	 * 		is_staging: bool,
	 * 		is_cli: bool,
	 * 		is_unit_test: bool,
	 * 		database: array{
	 * 			driver: string,
	 * 			host: string,
	 * 			database: string,
	 * 			username: string,
	 * 			password: string,
	 * 			port: int
	 * 		},
	 * 		autoload_routes: bool,
	 * 		route_directory: string,
	 * 		enable_latte: bool,
	 * 		template_base_dir: string,
	 * 		events: array,
	 * 		session: array{
	 * 			regenerate_interval: int,
	 * 			max_lifetime: int,
	 * 			absolute_max_lifetime: int,
	 * 			max_data_size: int,
	 * 			allowed_keys_pattern: string,
	 * 			enable_fingerprinting: bool,
	 * 			enable_ip_validation: bool,
	 * 			cookie_httponly: bool,
	 * 			cookie_secure: string,
	 * 			cookie_samesite: string,
	 * 			use_strict_mode: bool,
	 * 			use_only_cookies: bool,
	 * 			cookie_lifetime: int,
	 * 			gc_probability: int,
	 * 			gc_divisor: int,
	 * 			entropy_length: int,
	 * 			hash_function: string,
	 * 			hash_bits_per_character: int,
	 * 		}
	 * }
	 */
	protected array $config = [
		'is_live' => FALSE,
		'is_dev' => TRUE,
		'is_staging' => FALSE,
		'is_cli' => FALSE,
		'is_unit_test' => FALSE,
		'database' => [
			'driver' => 'mysql',
			'host' => '',
			'database' => '',
			'username' => '',
			'password' => '',
			'port' => 3306,
		],
		'autoload_routes' => TRUE,
		'route_directory' => '/App/Routes/',
		'enable_latte' => TRUE,
		'template_base_dir' => 'App/views/',
		'template_temp_dir' => 'tmp',
		'events' => [],
		
		// Add session configuration with sensible defaults
		'session' => [
			'regenerate_interval' => 300,        // 5 minutes - regenerate session ID
			'max_lifetime' => 7200,              // 2 hours - inactivity timeout
			'absolute_max_lifetime' => 28800,    // 8 hours - absolute maximum
			'max_data_size' => 1048576,          // 1MB
			'allowed_keys_pattern' => '/^[a-zA-Z0-9._-]+$/', // Safe key pattern
			'enable_fingerprinting' => true,
			'enable_ip_validation' => false,     // Disabled by default (CDN/proxy issues)
			'cookie_httponly' => true,
			'cookie_secure' => 'auto',           // Auto-detect HTTPS
			'cookie_samesite' => 'Strict',
			'use_strict_mode' => true,
			'use_only_cookies' => true,
			'cookie_lifetime' => 0,              // Session cookies only
			'gc_probability' => 1,
			'gc_divisor' => 100,
			'entropy_length' => 32,
			'hash_function' => 'sha256',
			'hash_bits_per_character' => 6,
		],
	];

	/**
	 * Config constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = []) {
		$this->load($config);
	}

	/**
	 * Get a config value
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name) {
		if (strpos($name, '.') !== FALSE) {
			$keys = explode('.', $name);
			$object = $this->config;
			foreach ($keys as $key) {
				if (is_array($object)) {
					$object = $object[$key];
					continue;
				}
				$object = $object->{$key};
			}
			return $object;
		}

		if (array_key_exists($name, $this->config)) {
			return $this->config[$name];
		}
	}

	/**
	 * get a config value
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function get(string $name) {
		if (strpos($name, '.') !== FALSE) {
			$keys = explode('.', $name);
			$object = $this->config;
			foreach ($keys as $key) {
				if (is_array($object)) {
					$object = $object[$key];
					continue;
				}
				$object = $object->{$key};
			}
			return $object;
		}

		if (array_key_exists($name, $this->config)) {
			return $this->config[$name];
		}
	}

	/**
	 * Set a config value
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function set(string $name, $value) {
		if (strpos($name, '.') !== FALSE) {
			$keys = explode('.', $name);
			$object = &$this->config;
			foreach ($keys as $key) {
				if (is_array($object)) {
					$object = &$object[$key];
					continue;
				}
				$object = &$object->{$key};
			}
			$object = $value;
			return;
		}

		if (array_key_exists($name, $this->config)) {
			$this->config[$name] = $value;
		}
	}

	/**
	 * Load the config
	 *
	 * @param array $config
	 * @return void
	 */
	public function load(array $config): void {
		if (empty($config)) {
			return;
		}

		$this->config = $this->loadConfigFile($this->config, $config);
	}

	/**
	 * Load the config file
	 *
	 * @param array $config
	 * @param array $new_config
	 * @return array
	 */
	protected function loadConfigFile(array $config, array $new_config): array {
		foreach ($new_config as $key => $value) {
			if (is_array($value)) {
				$config[$key] = $this->loadConfigFile($config[$key], $value);
				continue;
			}

			$config[$key] = $value;
		}

		return $config;
	}

	/**
	 * Get the config
	 *
	 * @return array
	 */
	public function getConfig(): array {
		return $this->config;
	}

	/**
	 * Get the config as a JSON string
	 *
	 * @return string
	 */
	public function getJson(): string {
		return json_encode($this->config);
	}

	/**
	 * Check if a key exists in the config
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key): bool {
		if (strpos($key, '.') !== FALSE) {
			$keys = explode('.', $key);
			$object = $this->config;
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

		return array_key_exists($key, $this->config);
	}
}