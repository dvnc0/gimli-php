<?php
declare(strict_types=1);

namespace Gimli\Database;

use Generator;
use Gimli\Database\Pdo_Manager;
use PDO;

class Mysql_Database {

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
		}
		$set = implode(', ', $set);
		$sql = "UPDATE {$table} SET {$set} WHERE {$where}";
		$params = array_merge($data, $params);
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
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
		return $stmt->fetch(PDO::FETCH_ASSOC);
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
	 */
	public function yieldRows(string $sql, array $params = []): Generator {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			yield $row;
		}
	}
}