<?php
declare(strict_types=1);

namespace Gimli\Session;

use Gimli\Session\Session_Interface;

class Session implements Session_Interface {

	/**
	 * @var bool $initialized
	 */
	private static bool $initialized = false;

	/**
	 * @var array $security_config
	 */
	private static array $security_config = [
		'regenerate_interval' => 300,        // 5 minutes - regenerate session ID
		'max_lifetime' => 7200,              // 2 hours - inactivity timeout
		'absolute_max_lifetime' => 28800,    // 8 hours - absolute maximum
		'max_data_size' => 1048576,          // 1MB
		'allowed_keys_pattern' => '/^[a-zA-Z0-9._-]+$/', // Safe key pattern
		'enable_fingerprinting' => true,
		'enable_ip_validation' => false,     // Disabled by default (CDN/proxy issues)
		
		// PHP session configuration
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
	];

	private static ?Session $instance = null;

	/**
	 * Constructor
	 */
	public function __construct(?array $config = null) {
		if ($config !== null) {
			self::$security_config = array_merge(self::$security_config, $config);
		}
		$this->initializeSecureSession();
	}

	/**
	 * get method
	 * 
	 * @param non-empty-string $key key to get
	 * 
	 * @return mixed
	 */
	public function get(string $key): mixed {
		$this->validateSession();
		$this->validateKey($key);

		if (strpos($key, '.') !== FALSE) {
			$keys  = explode('.', $key);
			$value = $_SESSION;
			foreach ($keys as $subkey) {
				if (isset($value[$subkey])) {
					$value = $value[$subkey];
				} else {
					return NULL;
				}
			}
			return $value;
		}

		return $_SESSION[$key] ?? NULL;
	}

	/**
	 * set method
	 * 
	 * @param non-empty-string $key   key to set
	 * @param mixed            $value value to set
	 * 
	 * @return void
	 */
	public function set(string $key, mixed $value): void {
		$this->validateSession();
		$this->validateKey($key);
		$this->validateDataSize($value);

		if (strpos($key, '.') !== FALSE) {
			$keys    = explode('.', $key);
			$session = &$_SESSION;
			foreach ($keys as $subkey) {
				$this->validateKey($subkey);
				if (!isset($session[$subkey])) {
					$session[$subkey] = [];
				}
				$session = &$session[$subkey];
			}
			$session = $value;
			return;
		}

		$_SESSION[$key] = $value;
		$this->updateSessionActivity();
	}

	/**
	 * delete method
	 * 
	 * @param non-empty-string $key key to delete
	 * 
	 * @return void
	 */
	public function delete(string $key): void {
		$this->validateSession();
		$this->validateKey($key);

		if (strpos($key, '.') !== FALSE) {
			$keys    = explode('.', $key);
			$session = &$_SESSION;
			$lastKey = array_pop($keys);
			foreach ($keys as $subkey) {
				if (!isset($session[$subkey])) {
					return;
				}
				$session = &$session[$subkey];
			}
			unset($session[$lastKey]);
			return;
		}

		unset($_SESSION[$key]);
	}

	/**
	 * clear method
	 * 
	 * @return void
	 */
	public function clear(): void {
		$this->validateSession();
		
		// Preserve security metadata
		$security_data = [
			'_gimli_session_created' => $_SESSION['_gimli_session_created'] ?? time(),
			'_gimli_session_fingerprint' => $_SESSION['_gimli_session_fingerprint'] ?? null,
			'_gimli_session_ip' => $_SESSION['_gimli_session_ip'] ?? null,
		];
		
		session_unset();
		
		// Restore security metadata
		foreach ($security_data as $key => $value) {
			if ($value !== null) {
				$_SESSION[$key] = $value;
			}
		}
		
		$this->updateSessionActivity();
	}

	/**
	 * has method
	 * 
	 * @param non-empty-string $key key to check for
	 * 
	 * @return bool
	 */
	public function has(string $key): bool {
		$this->validateSession();
		$this->validateKey($key);

		if (strpos($key, '.') !== FALSE) {
			$keys  = explode('.', $key);
			$value = $_SESSION;
			foreach ($keys as $subkey) {
				if (isset($value[$subkey])) {
					$value = $value[$subkey];
				} else {
					return FALSE;
				}
			}
			return TRUE;
		}

		return isset($_SESSION[$key]);
	}

	/**
	 * getAll method
	 * 
	 * @return array
	 */
	public function getAll(): array {
		$this->validateSession();
		
		// Filter out internal security keys
		$filtered = $_SESSION;
		$internal_keys = [
			'_gimli_session_created',
			'_gimli_session_last_activity', 
			'_gimli_session_fingerprint',
			'_gimli_session_ip',
			'_gimli_session_regenerated'
		];
		
		foreach ($internal_keys as $key) {
			unset($filtered[$key]);
		}
		
		return $filtered;
	}

	/**
	 * Regenerate session ID (security method)
	 * 
	 * @return bool
	 */
	public function regenerate(): bool {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			return false;
		}

		$old_session_data = $_SESSION;
		
		if (session_regenerate_id(true)) {
			$_SESSION = $old_session_data;
			$_SESSION['_gimli_session_regenerated'] = time();
			$this->updateSessionActivity();
			return true;
		}
		
		return false;
	}

	/**
	 * Destroy session completely
	 * 
	 * @return bool
	 */
	public function destroy(): bool {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_unset();
			session_destroy();
			
			// Clear session cookie
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
					$params["path"], $params["domain"],
					$params["secure"], $params["httponly"]
				);
			}
			
			return true;
		}
		
		return false;
	}

	/**
	 * Get session ID
	 * 
	 * @return string
	 */
	public function getId(): string {
		return session_id();
	}

	/**
	 * Check if session is valid and secure
	 * 
	 * @return bool
	 */
	public function isValid(): bool {
		try {
			$this->validateSession();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Initialize secure session with proper configuration
	 * 
	 * @return void
	 */
	private function initializeSecureSession(): void {
		if (self::$initialized) {
			return;
		}

		// Only configure if session hasn't started yet
		if (session_status() === PHP_SESSION_NONE) {
			$this->configureSecureSession();
			session_start();
		}

		$this->setupSessionSecurity();
		self::$initialized = true;
	}

	/**
	 * Configure secure session settings
	 * 
	 * @return void
	 */
	private function configureSecureSession(): void {
		// Secure session configuration from config
		ini_set('session.cookie_httponly', $this->getConfigValue('cookie_httponly') ? '1' : '0');
		
		$cookie_secure = $this->getConfigValue('cookie_secure');
		if ($cookie_secure === 'auto') {
			ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
		} else {
			ini_set('session.cookie_secure', $cookie_secure ? '1' : '0');
		}
		
		ini_set('session.cookie_samesite', $this->getConfigValue('cookie_samesite'));
		ini_set('session.use_strict_mode', $this->getConfigValue('use_strict_mode') ? '1' : '0');
		ini_set('session.use_only_cookies', $this->getConfigValue('use_only_cookies') ? '1' : '0');
		ini_set('session.cookie_lifetime', (string)$this->getConfigValue('cookie_lifetime'));
		ini_set('session.gc_maxlifetime', (string)$this->getConfigValue('max_lifetime'));
		ini_set('session.gc_probability', (string)$this->getConfigValue('gc_probability'));
		ini_set('session.gc_divisor', (string)$this->getConfigValue('gc_divisor'));
		
		// Use strong session ID generation
		ini_set('session.entropy_length', (string)$this->getConfigValue('entropy_length'));
		ini_set('session.hash_function', $this->getConfigValue('hash_function'));
		ini_set('session.hash_bits_per_character', (string)$this->getConfigValue('hash_bits_per_character'));
	}

	/**
	 * Setup session security metadata
	 * 
	 * @return void
	 */
	private function setupSessionSecurity(): void {
		$current_time = time();
		
		// Initialize session creation time
		if (!isset($_SESSION['_gimli_session_created'])) {
			$_SESSION['_gimli_session_created'] = $current_time;
		}
		
		// Set up session fingerprinting
		if (self::$security_config['enable_fingerprinting'] && !isset($_SESSION['_gimli_session_fingerprint'])) {
			$_SESSION['_gimli_session_fingerprint'] = $this->generateFingerprint();
		}
		
		// Set up IP validation
		if (self::$security_config['enable_ip_validation'] && !isset($_SESSION['_gimli_session_ip'])) {
			$_SESSION['_gimli_session_ip'] = $this->getClientIp();
		}
		
		$this->updateSessionActivity();
	}

	/**
	 * Validate session security
	 * 
	 * @return void
	 * @throws \Exception
	 */
	private function validateSession(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			throw new \Exception('Session is not active');
		}

		$current_time = time();
		
		// Check INACTIVITY timeout instead of absolute age
		if (isset($_SESSION['_gimli_session_last_activity'])) {
			$inactive_time = $current_time - $_SESSION['_gimli_session_last_activity'];
			
			// Logout after inactivity period
			if ($inactive_time > self::$security_config['max_lifetime']) {
				$this->destroy();
				throw new \Exception('Session expired due to inactivity');
			}
			
			// Regenerate session ID periodically (security)
			if ($inactive_time > self::$security_config['regenerate_interval']) {
				$this->regenerate();
			}
		}
		
		// Absolute maximum session age (security limit)
		if (isset($_SESSION['_gimli_session_created'])) {
			$session_age = $current_time - $_SESSION['_gimli_session_created'];
			$absolute_max = self::$security_config['absolute_max_lifetime'] ?? 86400; // 24 hours
			
			if ($session_age > $absolute_max) {
				$this->destroy();
				throw new \Exception('Session expired due to absolute age limit');
			}
		}
		
		// Validate fingerprint
		if (self::$security_config['enable_fingerprinting'] && isset($_SESSION['_gimli_session_fingerprint'])) {
			if ($_SESSION['_gimli_session_fingerprint'] !== $this->generateFingerprint()) {
				$this->destroy();
				throw new \Exception('Session fingerprint mismatch');
			}
		}
		
		// Validate IP (if enabled)
		if (self::$security_config['enable_ip_validation'] && isset($_SESSION['_gimli_session_ip'])) {
			if ($_SESSION['_gimli_session_ip'] !== $this->getClientIp()) {
				$this->destroy();
				throw new \Exception('Session IP mismatch');
			}
		}
	}

	/**
	 * Validate session key format
	 * 
	 * @param string $key
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	private function validateKey(string $key): void {
		if (empty($key)) {
			throw new \InvalidArgumentException('Session key cannot be empty');
		}
		
		if (strlen($key) > 255) {
			throw new \InvalidArgumentException('Session key too long');
		}
		
		if (!preg_match(self::$security_config['allowed_keys_pattern'], $key)) {
			throw new \InvalidArgumentException('Session key contains invalid characters');
		}
		
		// Prevent access to internal security keys
		if (str_starts_with($key, '_gimli_session_')) {
			throw new \InvalidArgumentException('Cannot access internal session keys');
		}
	}

	/**
	 * Validate data size to prevent session bloat
	 * 
	 * @param mixed $value
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	private function validateDataSize(mixed $value): void {
		$serialized_size = strlen(serialize($value));
		if ($serialized_size > self::$security_config['max_data_size']) {
			throw new \InvalidArgumentException('Session data too large');
		}
	}

	/**
	 * Update session activity timestamp
	 * 
	 * @return void
	 */
	private function updateSessionActivity(): void {
		$_SESSION['_gimli_session_last_activity'] = time();
	}

	/**
	 * Generate session fingerprint
	 * 
	 * @return string
	 */
	private function generateFingerprint(): string {
		$components = [
			$_SERVER['HTTP_USER_AGENT'] ?? '',
			$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
			$_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
		];
		
		return hash('sha256', implode('|', $components));
	}

	/**
	 * Get client IP address
	 * 
	 * @return string
	 */
	private function getClientIp(): string {
		$ip_headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
			'HTTP_X_FORWARDED',          // Proxies
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxies
			'HTTP_FORWARDED',            // Proxies
			'REMOTE_ADDR'                // Standard
		];
		
		foreach ($ip_headers as $header) {
			if (!empty($_SERVER[$header])) {
				$ip = trim(explode(',', $_SERVER[$header])[0]);
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Check if connection is HTTPS
	 * 
	 * @return bool
	 */
	private function isHttps(): bool {
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
			   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
			   (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
			   (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
	}

	/**
	 * Get configuration value with fallback to default
	 * 
	 * @param string $key
	 * @return mixed
	 */
	private function getConfigValue(string $key): mixed {
		return self::$security_config[$key] ?? null;
	}

	/**
	 * Configure security settings (static method for framework integration)
	 * 
	 * @param array $config
	 * @return void
	 */
	public static function configure(array $config): void {
		// Merge with defaults, allowing partial configuration
		self::$security_config = array_merge(self::$security_config, $config);
	}

	public static function getInstance(?array $config = null): Session {
		if (self::$instance === null) {
			self::$instance = new Session($config ?? []);
		}
		return self::$instance;
	}
}