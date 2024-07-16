<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Application;
use PDO;

class Pdo_Manager {
	/**
	 * @param Application $Application
	 */
	public function __construct(
		protected Application $Application,
	) {}

	/**
	 * Creaate a PDO connection
	 *
	 * @return PDO
	 */
	public function getConnection(): PDO {
		$config = $this->Application->Config->database;
		$dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']}";
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		return new PDO($dsn, $config['username'], $config['password'], $options);
	}
}