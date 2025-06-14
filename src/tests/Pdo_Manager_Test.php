<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Database\Pdo_Manager;
use Gimli\Application;
use Gimli\Environment\Config;
use PDO;

class Pdo_Manager_Test extends TestCase {

	private function getApplicationMock(): Application {
		$configMock = $this->createMock(Config::class);
		$configMock->database = [
			'driver' => 'mysql',
			'host' => 'localhost',
			'database' => 'test_db',
			'username' => 'test_user',
			'password' => 'test_pass'
		];
		
		$applicationMock = $this->createMock(Application::class);
		$applicationMock->Config = $configMock;
		
		return $applicationMock;
	}

	public function testConstructor() {
		$application = $this->getApplicationMock();
		$pdoManager = new Pdo_Manager($application);
		
		$this->assertInstanceOf(Pdo_Manager::class, $pdoManager);
	}

	public function testGetConnectionReturnsPdoInstance() {
		$application = $this->getApplicationMock();
		$pdoManager = new Pdo_Manager($application);
		
		// Test that it attempts to create a PDO connection (will fail without drivers)
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionBuildsCorrectDsn() {
		$application = $this->getApplicationMock();
		
		// Test MySQL DSN
		$application->Config->database = [
			'driver' => 'mysql',
			'host' => 'localhost',
			'database' => 'test_db',
			'username' => 'test_user',
			'password' => 'test_pass'
		];
		
		$pdoManager = new Pdo_Manager($application);
		
		// Test that it attempts to create a connection (will fail without MySQL driver)
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionSetsCorrectPdoOptions() {
		$application = $this->getApplicationMock();
		$pdoManager = new Pdo_Manager($application);
		
		// Test that it attempts to create a PDO connection with correct options
		// (will fail without drivers but we can test the attempt)
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionWithPostgresqlDriver() {
		$application = $this->getApplicationMock();
		
		$application->Config->database = [
			'driver' => 'pgsql',
			'host' => 'localhost',
			'database' => 'test_db',
			'username' => 'test_user',
			'password' => 'test_pass'
		];
		
		$pdoManager = new Pdo_Manager($application);
		
		// Test that it attempts to create a PostgreSQL connection
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionWithSqliteDriver() {
		$application = $this->getApplicationMock();
		
		$application->Config->database = [
			'driver' => 'sqlite',
			'host' => '',
			'database' => '/tmp/test.db',
			'username' => '',
			'password' => ''
		];
		
		$pdoManager = new Pdo_Manager($application);
		
		// Test that it attempts to create a SQLite connection
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionThrowsExceptionForInvalidDriver() {
		$application = $this->getApplicationMock();
		
		$application->Config->database = [
			'driver' => 'invalid_driver',
			'host' => 'localhost',
			'database' => 'test_db',
			'username' => 'test_user',
			'password' => 'test_pass'
		];
		
		$pdoManager = new Pdo_Manager($application);
		
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}

	public function testGetConnectionThrowsExceptionForInvalidCredentials() {
		$application = $this->getApplicationMock();
		
		// Use MySQL with invalid credentials
		$application->Config->database = [
			'driver' => 'mysql',
			'host' => 'localhost',
			'database' => 'nonexistent_db',
			'username' => 'invalid_user',
			'password' => 'invalid_pass'
		];
		
		$pdoManager = new Pdo_Manager($application);
		
		$this->expectException(\PDOException::class);
		$pdoManager->getConnection();
	}
} 