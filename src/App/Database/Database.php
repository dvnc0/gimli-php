<?php
declare(strict_types=1);

namespace Gimli\Database;

use Generator;
use Gimli\Database\Pdo_Manager;
use PDO;
use Throwable;
use PDOException;
use InvalidArgumentException;

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
	 * @param string $sql
	 * @param array $params
	 * @return bool
	 */
	public function execute(string $sql, array $params = []): bool {
		$stmt = $this->db->prepare($sql);
		return $stmt->execute($params);
	}

	/**
	 * Fetches the last insert ID
	 *
	 * @return string
	 */
	public function lastInsertId(): string {
		return $this->db->lastInsertId();
	}

	/**
	 * Update a row
	 *
	 * @param  string  $table
	 * @param  string  $where
	 * @param  array   $data
	 * @param  array   $params
	 * @return boolean
	 */
	public function update(string $table, string $where, array $data, array $params = []): bool {
		$set = [];
		foreach ($data as $key => $value) {
			$set[] = "{$key} = :{$key}";
			$params[":{$key}"] = $value;
		}
		$set = implode(', ', $set);
		$sql = "UPDATE {$table} SET {$set} WHERE {$where}";
		return $this->execute($sql, $params);
	}

	/**
	 * Insert a row
	 *
	 * @param  string  $table
	 * @param  array   $data
	 * @return boolean
	 */
	public function insert(string $table, array $data): bool {
		$keys = array_keys($data);
		$columns = implode(', ', $keys);
		$values = ':' . implode(', :', $keys);
		$sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
		return $this->execute($sql, $data);
	}

	/**
	 * Fetches all rows from a SQL query
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	public function fetchAll(string $sql, array $params = []): array {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}

	/**
	 * Fetches a single row from a SQL query
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	public function fetchRow(string $sql, array $params = []): array {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
	}

	/**
	 * Fetches a single column from a SQL query
	 *
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 */
	public function fetchColumn(string $sql, array $params = []): mixed {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchColumn();
	}

	/**
	 * Fetches rows from a SQL query using a generator
	 *
	 * @param string $sql
	 * @param array $params
	 * @return Generator
	 * @throws PDOException
	 */
	public function yieldRows(string $sql, array $params = []): Generator {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		
		try {
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				yield $row;
			}
		} finally {
			// Ensure the statement cursor is closed
			$stmt->closeCursor();
		}
	}

	/**
	 * Fetches rows from a SQL query in chunks using a generator
	 *
	 * @param string $sql
	 * @param array $params
	 * @param int $chunk_size
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	public function yieldRowChunks(string $sql, array $params = [], int $chunk_size = 1000): Generator {
		if ($chunk_size <= 0) {
			throw new InvalidArgumentException('Chunk size must be greater than 0');
		}

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		
		try {
			$chunk = [];
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$chunk[] = $row;
				
				if (count($chunk) >= $chunk_size) {
					yield $chunk;
					$chunk = [];
				}
			}
			
			if (!empty($chunk)) {
				yield $chunk;
			}
		} finally {
			$stmt->closeCursor();
		}
	}

	/**
	 * Fetches rows in batches using LIMIT/OFFSET for efficient database-level pagination
	 *
	 * @param string $sql Base SQL query (without LIMIT/OFFSET)
	 * @param array $params Query parameters
	 * @param int $batch_size Number of rows per batch
	 * @param string|null $order_by ORDER BY clause (required for consistent results)
	 * @return Generator<array<array>>
	 * @throws PDOException
	 */
	public function yieldBatch(string $sql, array $params = [], int $batch_size = 1000, ?string $order_by = null): Generator {
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

		$offset = 0;
		
		do {
			$batch_sql = $sql . " LIMIT {$batch_size} OFFSET {$offset}";
			$batch = $this->fetchAll($batch_sql, $params);
			
			if (!empty($batch)) {
				yield $batch;
			}
			
			$offset += $batch_size;
		} while (count($batch) === $batch_size);
	}

	/**
	 * Begins a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function beginTransaction(): bool {
		return $this->db->beginTransaction();
	}

	/**
	 * Commits a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function commit(): bool {
		return $this->db->commit();
	}

	/**
	 * Rolls back a transaction
	 *
	 * @return bool
	 * @throws PDOException
	 */
	public function rollback(): bool {
		return $this->db->rollback();
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
	 * @param callable $callback
	 * @return mixed Returns the result of the callback
	 * @throws Throwable
	 */
	public function transaction(callable $callback): mixed {
		$was_in_transaction = $this->inTransaction();
		
		if (!$was_in_transaction) {
			$this->beginTransaction();
		}

		try {
			$result = call_user_func($callback, $this);
			
			if (!$was_in_transaction) {
				$this->commit();
			}
			
			return $result;
		} catch (Throwable $e) {
			if (!$was_in_transaction) {
				$this->rollback();
			}
			throw $e;
		}
	}
}