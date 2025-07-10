<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Application;
use PDO;

class Pdo_Manager {
	/**
	 * @var PDO|null $connection
	 */
	private ?PDO $connection = null;

	/**
	 * @param Application $Application
	 */
	public function __construct(
		protected Application $Application,
	) {}

	/**
	 * Create a PDO connection (cached per instance)
	 *
	 * @return PDO
	 */
	public function getConnection(): PDO {
		if ($this->connection === null) {
			$config  = $this->Application->Config->database;
			$dsn     = "{$config['driver']}:host={$config['host']};dbname={$config['database']}";
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => FALSE,
			];
			$this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
		}
		return $this->connection;
	}

	/**
	 * Close the connection
	 *
	 * @return void
	 */
	public function closeConnection(): void {
		$this->connection = null;
	}

	/**
	 * Destructor to ensure connection is closed
	 */
	public function __destruct() {
		$this->closeConnection();
	}
}