<?php
declare(strict_types=1);

namespace Gimli\Session;

use Gimli\Session\Session_Interface;

class Session implements Session_Interface {

	/**
	 * Constructor
	 */
	public function __construct() {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	}

	/**
	 * get method
	 * 
	 * @param non-empty-string $key key to get
	 * 
	 * @return mixed
	 */
	public function get(string $key): mixed {
		if (strpos($key, '.') !== FALSE) {
			$keys  = explode('.', $key);
			$value = $_SESSION;
			foreach ($keys as $key) {
				if (isset($value[$key])) {
					$value = $value[$key];
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
		if (strpos($key, '.') !== FALSE) {
			$keys    = explode('.', $key);
			$session = &$_SESSION;
			foreach ($keys as $key) {
				if (!isset($session[$key])) {
					$session[$key] = [];
				}
				$session = &$session[$key];
			}
			$session = $value;
			return;
		}

		$_SESSION[$key] = $value;
	}

	/**
	 * delete method
	 * 
	 * @param non-empty-string $key key to delete
	 * 
	 * @return void
	 */
	public function delete(string $key): void {
		if (strpos($key, '.') !== FALSE) {
			$keys    = explode('.', $key);
			$session = &$_SESSION;
			foreach ($keys as $key) {
				if (!isset($session[$key])) {
					return;
				}
				$session = &$session[$key];
			}
			unset($session);
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
		session_unset();
	}

	/**
	 * has method
	 * 
	 * @param non-empty-string $key key to check for
	 * 
	 * @return bool
	 */
	public function has(string $key): bool {
		if (strpos($key, '.') !== FALSE) {
			$keys  = explode('.', $key);
			$value = $_SESSION;
			foreach ($keys as $key) {
				if (isset($value[$key])) {
					$value = $value[$key];
				} else {
					return FALSE;
				}
			}
			return TRUE;
		}

		return isset($_SESSION[$key]);
	}
}