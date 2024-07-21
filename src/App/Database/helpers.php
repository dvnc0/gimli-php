<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Application;
use Gimli\Database\Database;

if (!function_exists('Gimli\Database\fetch_column')) {
	/**
	 * Fetches a single column
	 *
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 */
	function fetch_column(string $sql, array $params = []): mixed {
		$Database = Application::get()->Injector->resolve(Database::class);
		return $Database->fetchColumn($sql, $params);
	}
}

if (!function_exists('Gimli\Database\fetch_row')) {
	/**
	 * Fetches a single row
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	function fetch_row(string $sql, array $params = []): array {
		$Database = Application::get()->Injector->resolve(Database::class);
		return $Database->fetchRow($sql, $params);
	}
}

if (!function_exists('Gimli\Database\fetch_all')) {
	/**
	 * Fetches a single field
	 *
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 */
	function fetch_all(string $sql, array $params = []): mixed {
		$Database = Application::get()->Injector->resolve(Database::class);
		return $Database->fetchAll($sql, $params);
	}
}

if (!function_exists('Gimli\Database\row_exists')) {
	/**
	 * Checks if a row exists
	 *
	 * @param string $sql
	 * @param array $params
	 * @return bool
	 */
	function row_exists(string $sql, array $params = []): bool {
		$Database = Application::get()->Injector->resolve(Database::class);
		$row = $Database->fetchRow($sql, $params);

		return !empty($row);
	}
}