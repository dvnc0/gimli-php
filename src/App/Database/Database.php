<?php
declare(strict_types=1);

namespace Gimli\Database;

use Generator;
use Gimli\Database\Pdo_Manager;
use PDO;
use Throwable;
use PDOException;
use InvalidArgumentException;

use function Gimli\Events\publish_event;

class Database {

	/**
	 * @var PDO $db
	 */
	protected PDO $db;

	/**
	 * @param Pdo_Manager $Pdo_Manager
	 */
	public function __construct(
		protected Pdo_Manager $Pdo_Manager,
	) {
		$this->db = $this->Pdo_Manager->getConnection();
	}

	/**
	 * Executes a SQL query
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return bool
	 */
	public function execute(string $sql, array $params = []): bool {
		publish_event('gimli.database.start', ['sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($params);
		publish_event('gimli.database.end', ['sql' => $sql, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Fetches the last insert ID
	 *
	 * @return string the last insert ID
	 */
	public function lastInsertId(): string {
		return $this->db->lastInsertId();
	}

	/**
	 * Update a row
	 *
	 * @param  string $table  the table to update
	 * @param  string $where  the where clause
	 * @param  array  $data   the data to update
	 * @param  array  $params the parameters for the SQL query
	 * @return boolean
	 */
	public function update(string $table, string $where, array $data, array $params = []): bool {
		publish_event('gimli.database.update.start', ['table' => $table, 'data' => $data, 'time' => microtime(TRUE)]);
		$set = [];
		foreach ($data as $key => $value) {
			$set[]             = "{$key} = :{$key}";
			$params[":{$key}"] = $value;
		}
		$set = implode(', ', $set);
		$sql = "UPDATE {$table} SET {$set} WHERE {$where}";
		$result = $this->execute($sql, $params);
		publish_event('gimli.database.update.end', ['table' => $table, 'success' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Insert a row
	 *
	 * @param  string $table the table to insert into
	 * @param  array  $data  the data to insert
	 * @return boolean
	 */
	public function insert(string $table, array $data): bool {
		publish_event('gimli.database.insert.start', ['table' => $table, 'data' => $data, 'time' => microtime(TRUE)]);
		$keys    = array_keys($data);
		$columns = implode(', ', $keys);
		$values  = ':' . implode(', :', $keys);
		$sql     = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
		$result = $this->execute($sql, $data);
		publish_event('gimli.database.insert.end', ['table' => $table, 'success' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Fetches all rows from a SQL query
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return array the results of the SQL query
	 */
	public function fetchAll(string $sql, array $params = []): array {
		publish_event('gimli.database.fetch.start', ['operation' => 'fetchAll', 'sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		publish_event('gimli.database.fetch.end', ['operation' => 'fetchAll', 'count' => count($result), 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Fetches a single row from a SQL query
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return array the results of the SQL query
	 */
	public function fetchRow(string $sql, array $params = []): array {
		publish_event('gimli.database.fetch.start', ['operation' => 'fetchRow', 'sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		publish_event('gimli.database.fetch.end', ['operation' => 'fetchRow', 'found' => !empty($result), 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Fetches a single column from a SQL query
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return mixed the results of the SQL query
	 */
	public function fetchColumn(string $sql, array $params = []): mixed {
		publish_event('gimli.database.fetch.start', ['operation' => 'fetchColumn', 'sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetchColumn();
		publish_event('gimli.database.fetch.end', ['operation' => 'fetchColumn', 'result' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Fetches rows from a SQL query using a generator
	 *
	 * @param string $sql    the SQL query to execute
	 * @param array  $params the parameters for the SQL query
	 * @return Generator the results of the SQL query
	 * @throws PDOException
	 */
	public function yieldRows(string $sql, array $params = []): Generator {
		publish_event('gimli.database.yield.start', ['operation' => 'yieldRows', 'sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		
		$row_count = 0;
		try {
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$row_count++;
				yield $row;
			}
		} finally {
			// Ensure the statement cursor is closed
			$stmt->closeCursor();
			publish_event('gimli.database.yield.end', ['operation' => 'yieldRows', 'count' => $row_count, 'time' => microtime(TRUE)]);
		}
	}

	/**
	 * Fetches rows from a SQL query in chunks using a generator
	 *
	 * @param string $sql        the SQL query to execute
	 * @param array  $params     the parameters for the SQL query
	 * @param int    $chunk_size the size of the chunk
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	public function yieldRowChunks(string $sql, array $params = [], int $chunk_size = 1000): Generator {
		if ($chunk_size <= 0) {
			throw new InvalidArgumentException('Chunk size must be greater than 0');
		}

		publish_event('gimli.database.yield.start', ['operation' => 'yieldRowChunks', 'chunk_size' => $chunk_size, 'sql' => $sql, 'time' => microtime(TRUE)]);
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		
		$chunk_count = 0;
		$total_rows = 0;
		try {
			$chunk = [];
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$chunk[] = $row;
				$total_rows++;
				
				if (count($chunk) >= $chunk_size) {
					$chunk_count++;
					yield $chunk;
					$chunk = [];
				}
			}
			
			if (!empty($chunk)) {
				$chunk_count++;
				yield $chunk;
			}
		} finally {
			$stmt->closeCursor();
			publish_event('gimli.database.yield.end', ['operation' => 'yieldRowChunks', 'chunks' => $chunk_count, 'total_rows' => $total_rows, 'time' => microtime(TRUE)]);
		}
	}

	/**
	 * Fetches rows in batches using LIMIT/OFFSET for efficient database-level pagination
	 *
	 * @param string      $sql        the SQL query to execute
	 * @param array       $params     Query parameters
	 * @param int         $batch_size Number of rows per batch
	 * @param string|null $order_by   ORDER BY clause (required for consistent results)
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	public function yieldBatch(string $sql, array $params = [], int $batch_size = 1000, ?string $order_by = NULL): Generator {
		if ($batch_size <= 0) {
			throw new InvalidArgumentException('Batch size must be greater than 0');
		}

		// Ensure we have an ORDER BY for consistent pagination
		$sql = trim($sql);
		if (!preg_match('/ORDER\s+BY/i', $sql)) {
			if ($order_by) {
				$sql .= " ORDER BY {$order_by}";
			} else {
				throw new InvalidArgumentException('ORDER BY clause is required for consistent batch results. Either include it in your SQL or provide the $order_by parameter.');
			}
		}

		publish_event('gimli.database.batch.start', ['operation' => 'yieldBatch', 'batch_size' => $batch_size, 'sql' => $sql, 'time' => microtime(TRUE)]);
		$offset = 0;
		$batch_count = 0;
		$total_rows = 0;
		
		do {
			$batch_sql = $sql . " LIMIT {$batch_size} OFFSET {$offset}";
			$batch     = $this->fetchAll($batch_sql, $params);
			
			if (!empty($batch)) {
				$batch_count++;
				$total_rows += count($batch);
				yield $batch;
			}
			
			$offset += $batch_size;
		} while (count($batch) === $batch_size);
		
		publish_event('gimli.database.batch.end', ['operation' => 'yieldBatch', 'batches' => $batch_count, 'total_rows' => $total_rows, 'time' => microtime(TRUE)]);
	}

	/**
	 * Begins a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function beginTransaction(): bool {
		publish_event('gimli.database.transaction.begin', ['time' => microtime(TRUE)]);
		$result = $this->db->beginTransaction();
		publish_event('gimli.database.transaction.started', ['success' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Commits a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function commit(): bool {
		publish_event('gimli.database.transaction.commit', ['time' => microtime(TRUE)]);
		$result = $this->db->commit();
		publish_event('gimli.database.transaction.committed', ['success' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Rolls back a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function rollback(): bool {
		publish_event('gimli.database.transaction.rollback', ['time' => microtime(TRUE)]);
		$result = $this->db->rollback();
		publish_event('gimli.database.transaction.rolledback', ['success' => $result, 'time' => microtime(TRUE)]);
		return $result;
	}

	/**
	 * Checks if we're currently in a transaction
	 *
	 * @return bool
	 */
	public function inTransaction(): bool {
		return $this->db->inTransaction();
	}

	/**
	 * Executes a callback within a transaction
	 * Automatically commits on success or rolls back on exception
	 *
	 * @param callable $callback the callback to execute
	 * @return mixed Returns the result of the callback
	 * @throws Throwable
	 */
	public function transaction(callable $callback): mixed {
		$was_in_transaction = $this->inTransaction();
		
		publish_event('gimli.database.transaction.wrapper.start', ['nested' => $was_in_transaction, 'time' => microtime(TRUE)]);
		
		if (!$was_in_transaction) {
			$this->beginTransaction();
		}

		try {
			$result = call_user_func($callback, $this);
			
			if (!$was_in_transaction) {
				$this->commit();
			}
			
			publish_event('gimli.database.transaction.wrapper.success', ['nested' => $was_in_transaction, 'time' => microtime(TRUE)]);
			return $result;
		} catch (Throwable $e) {
			if (!$was_in_transaction) {
				$this->rollback();
			}
			publish_event('gimli.database.transaction.wrapper.error', ['nested' => $was_in_transaction, 'error' => $e->getMessage(), 'time' => microtime(TRUE)]);
			throw $e;
		}
	}


}