<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Application_Registry;
use Gimli\Database\Database;
use Generator;
use PDOException;

if (!function_exists('Gimli\Database\get_database')) {
	/**
	 * Get the database instance
	 *
	 * @return Database
	 */
	function get_database(): Database {
		return Application_Registry::get()->Injector->resolve(Database::class);
	}
}

if (!function_exists('Gimli\Database\fetch_column')) {
	/**
	 * Fetches a single column
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return mixed the results of the SQL query
	 */
	function fetch_column(string $sql, array $params = []): mixed {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->fetchColumn($sql, $params);
	}
}

if (!function_exists('Gimli\Database\fetch_row')) {
	/**
	 * Fetches a single row
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return array the results of the SQL query
	 */
	function fetch_row(string $sql, array $params = []): array {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->fetchRow($sql, $params);
	}
}

if (!function_exists('Gimli\Database\fetch_all')) {
	/**
	 * Fetch all rows
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return mixed the results of the SQL query
	 */
	function fetch_all(string $sql, array $params = []): mixed {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->fetchAll($sql, $params);
	}
}

if (!function_exists('Gimli\Database\row_exists')) {
	/**
	 * Checks if a row exists
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return bool the results of the SQL query
	 */
	function row_exists(string $sql, array $params = []): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		$row      = $Database->fetchRow($sql, $params);

		return !empty($row);
	}
}

if (!function_exists('Gimli\Database\seed_model')) {

	/**
	 * Seed a model
	 *
	 * @param string   $model the model to seed
	 * @param int      $count the number of records to seed
	 * @param int|null $seed  the seed for the seeder
	 * @return int the number of records seeded
	 */
	function seed_model(string $model, int $count = 1, int|null $seed = NULL): int {
		$seed = $seed ?? Seeder::getRandomSeed();
		return Seeder::make($model)->count($count)->seed($seed)->create();
	}
}

if (!function_exists('Gimli\Database\seed_data')) {

	/**
	 * Seed a model, get data instead of insert
	 *
	 * @param string   $model the model to seed
	 * @param int|null $seed  the seed for the seeder
	 * @return array the seeded data
	 */
	function seed_data(string $model, int|null $seed = NULL): array {
		$seed = $seed ?? Seeder::getRandomSeed();
		return Seeder::make($model)->count(1)->seed($seed)->getSeededData();
	}
}

if (!function_exists('Gimli\Database\begin_transaction')) {
	/**
	 * Begin a database transaction
	 *
	 * @return bool
	 * @throws \PDOException
	 */
	function begin_transaction(): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->beginTransaction();
	}
}

if (!function_exists('Gimli\Database\commit_transaction')) {
	/**
	 * Commit a database transaction
	 *
	 * @return bool
	 * @throws \PDOException
	 */
	function commit_transaction(): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->commit();
	}
}

if (!function_exists('Gimli\Database\rollback_transaction')) {
	/**
	 * Rollback a database transaction
	 *
	 * @return bool
	 * @throws \PDOException
	 */
	function rollback_transaction(): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->rollback();
	}
}

if (!function_exists('Gimli\Database\in_transaction')) {
	/**
	 * Check if currently in a transaction
	 *
	 * @return bool
	 */
	function in_transaction(): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->inTransaction();
	}
}

if (!function_exists('Gimli\Database\with_transaction')) {
	/**
	 * Execute a callback within a transaction
	 * Automatically commits on success or rolls back on exception
	 *
	 * @param callable $callback the callback to execute
	 * @return mixed Returns the result of the callback
	 * @throws \Throwable
	 */
	function with_transaction(callable $callback): mixed {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->transaction($callback);
	}
}

if (!function_exists('Gimli\Database\yield_row_chunks')) {
	/**
	 * Fetch rows from a SQL query in chunks using a generator
	 *
	 * @param string $sql        the SQL query to execute
	 * @param array  $params     the parameters for the SQL query
	 * @param int    $chunk_size the size of the chunk
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	function yield_row_chunks(string $sql, array $params = [], int $chunk_size = 1000): Generator {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->yieldRowChunks($sql, $params, $chunk_size);
	}
}

if (!function_exists('Gimli\Database\yield_batch')) {
	/**
	 * Fetch rows in batches using LIMIT/OFFSET for efficient database-level pagination
	 *
	 * @param string      $sql        Base SQL query (without LIMIT/OFFSET)
	 * @param array       $params     Query parameters
	 * @param int         $batch_size Number of rows per batch
	 * @param string|null $order_by   ORDER BY clause (required for consistent results)
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	function yield_batch(string $sql, array $params = [], int $batch_size = 1000, ?string $order_by = NULL): Generator {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->yieldBatch($sql, $params, $batch_size, $order_by);
	}
}

if (!function_exists('Gimli\Database\insert')) {
	/**
	 * Insert data into a database table
	 *
	 * @param string $table the table to insert into
	 * @param array  $data  the data to insert
	 * @return bool Returns true on success, false on failure
	 * @throws PDOException
	 */
	function insert(string $table, array $data): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->insert($table, $data);
	}
}

if (!function_exists('Gimli\Database\update')) {
	/**
	 * Update data in a database table
	 *
	 * @param string $table the table to update
	 * @param string $where the WHERE clause for the update
	 * @param array  $data  the data to update
	 * @param array  $params the parameters for the WHERE clause
	 * 
	 * @return bool Returns true on success, false on failure
	 * @throws PDOException
	 */
	function update(string $table, string $where, array $data, array $params = []): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);
		return $Database->update($table, $where, $data, $params);
	}
}

if (!function_exists('Gimli\Database\insert_batch')) {
	/**
	 * Insert multiple rows into a database table
	 *
	 * @param string $table the table to insert into
	 * @param array  $data  the data to insert
	 * @return bool Returns true on success, false on failure
	 * @throws PDOException
	 */
	function insert_batch(string $table, array $data): bool {
		$Database = Application_Registry::get()->Injector->resolve(Database::class);

		// need to make a big insert into {table} values (?, ?), (?, ?), ...
		$values = [];
		$placeholders = [];
		foreach ($data as $row) {
			$row_placeholders = [];
			foreach ($row as $value) {
				$values[] = $value;
				$row_placeholders[] = '?';
			}
			$placeholders[] = '(' . implode(', ', $row_placeholders) . ')';
		}
		$sql = "INSERT INTO {$table} VALUES " . implode(', ', $placeholders);
		return $Database->execute($sql, $values);
	}
}