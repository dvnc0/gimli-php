<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Application;
use Gimli\Database\Database;

if (!function_exists('Gimli\Database\get_database')) {
	/**
	 * Get the database instance
	 *
	 * @return Database
	 */
	function get_database(): Database {
		return Application::get()->Injector->resolve(Database::class);
	}
}

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
	 * Fetch all rows
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

if (!function_exists('Gimli\Database\seed_model')) {

	/**
	 * Seed a model
	 *
	 * @param string $model
	 * @param int $count
	 * @param int|null $seed
	 * @return int
	 */
	function seed_model(string $model, int $count = 1, int|null $seed = null): int {
		$seed = $seed ?? Seeder::getRandomSeed();
		return Seeder::make($model)->count($count)->seed($seed)->create();
	}
}

if (!function_exists('Gimli\Database\seed_data')) {

	/**
	 * Seed a model, get data instead of insert
	 *
	 * @param string $model
	 * @param int|null $seed
	 * @return int
	 */                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           
	function seed_data(string $model, int|null $seed = null): int {
		$seed = $seed ?? Seeder::getRandomSeed();
		return Seeder::make($model)->count(1)->seed($seed)->getSeededData();
	}
}