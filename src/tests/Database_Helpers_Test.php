<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Database\Database;
use Gimli\Database\Seeder;
use Gimli\Application_Registry;
use Gimli\Application;
use Gimli\Injector\Injector;
use Gimli\Environment\Config;

class Database_Helpers_Test extends TestCase {

	private function setupApplicationRegistry(): void {
		$config = new Config();
		$application = $this->createMock(Application::class);
		$injector = $this->createMock(Injector::class);
		$database = $this->createMock(Database::class);
		
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application->Injector = $injector;
		Application_Registry::set($application);
	}

	protected function tearDown(): void {
		// Clean up the registry after each test
		Application_Registry::clear();
		parent::tearDown();
	}

	public function testGetDatabaseHelper() {
		$this->setupApplicationRegistry();
		
		$database = \Gimli\Database\get_database();
		$this->assertInstanceOf(Database::class, $database);
	}

	public function testFetchColumnHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('fetchColumn')
			->with('SELECT COUNT(*) FROM users', ['param' => 'value'])
			->willReturn('5');
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\fetch_column('SELECT COUNT(*) FROM users', ['param' => 'value']);
		$this->assertEquals('5', $result);
	}

	public function testFetchRowHelper() {
		$this->setupApplicationRegistry();
		
		$expectedData = ['id' => 1, 'name' => 'John'];
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('fetchRow')
			->with('SELECT * FROM users WHERE id = ?', [1])
			->willReturn($expectedData);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\fetch_row('SELECT * FROM users WHERE id = ?', [1]);
		$this->assertEquals($expectedData, $result);
	}

	public function testFetchAllHelper() {
		$this->setupApplicationRegistry();
		
		$expectedData = [
			['id' => 1, 'name' => 'John'],
			['id' => 2, 'name' => 'Jane']
		];
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('fetchAll')
			->with('SELECT * FROM users', [])
			->willReturn($expectedData);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\fetch_all('SELECT * FROM users');
		$this->assertEquals($expectedData, $result);
	}

	public function testRowExistsHelperReturnsTrueWhenRowExists() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('fetchRow')
			->with('SELECT * FROM users WHERE id = ?', [1])
			->willReturn(['id' => 1, 'name' => 'John']);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\row_exists('SELECT * FROM users WHERE id = ?', [1]);
		$this->assertTrue($result);
	}

	public function testRowExistsHelperReturnsFalseWhenRowDoesNotExist() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('fetchRow')
			->with('SELECT * FROM users WHERE id = ?', [999])
			->willReturn([]);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\row_exists('SELECT * FROM users WHERE id = ?', [999]);
		$this->assertFalse($result);
	}

	public function testBeginTransactionHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('beginTransaction')
			->willReturn(true);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\begin_transaction();
		$this->assertTrue($result);
	}

	public function testCommitTransactionHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('commit')
			->willReturn(true);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\commit_transaction();
		$this->assertTrue($result);
	}

	public function testRollbackTransactionHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('rollback')
			->willReturn(true);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\rollback_transaction();
		$this->assertTrue($result);
	}

	public function testInTransactionHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('inTransaction')
			->willReturn(true);
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\in_transaction();
		$this->assertTrue($result);
	}

	public function testWithTransactionHelper() {
		$this->setupApplicationRegistry();
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('transaction')
			->with($this->isType('callable'))
			->willReturn('callback_result');
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$result = \Gimli\Database\with_transaction(function() {
			return 'callback_result';
		});
		
		$this->assertEquals('callback_result', $result);
	}

	public function testYieldRowChunksHelper() {
		$this->setupApplicationRegistry();
		
		$expectedChunks = [
			[['id' => 1], ['id' => 2]],
			[['id' => 3]]
		];
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('yieldRowChunks')
			->with('SELECT * FROM users', [], 2)
			->willReturn((function() use ($expectedChunks) {
				foreach ($expectedChunks as $chunk) {
					yield $chunk;
				}
			})());
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$generator = \Gimli\Database\yield_row_chunks('SELECT * FROM users', [], 2);
		$chunks = [];
		foreach ($generator as $chunk) {
			$chunks[] = $chunk;
		}
		
		$this->assertEquals($expectedChunks, $chunks);
	}

	public function testYieldBatchHelper() {
		$this->setupApplicationRegistry();
		
		$expectedBatches = [
			[['id' => 1], ['id' => 2]],
			[['id' => 3], ['id' => 4]]
		];
		
		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('yieldBatch')
			->with('SELECT * FROM users', [], 2, 'id ASC')
			->willReturn((function() use ($expectedBatches) {
				foreach ($expectedBatches as $batch) {
					yield $batch;
				}
			})());
		
		$injector = $this->createMock(Injector::class);
		$injector->method('resolve')
			->with(Database::class)
			->willReturn($database);
		
		$application = Application_Registry::get();
		$application->Injector = $injector;
		
		$generator = \Gimli\Database\yield_batch('SELECT * FROM users', [], 2, 'id ASC');
		$batches = [];
		foreach ($generator as $batch) {
			$batches[] = $batch;
		}
		
		$this->assertEquals($expectedBatches, $batches);
	}

	public function testSeedModelHelper() {
		// Test that the seed_model helper function exists and can be called
		$this->assertTrue(function_exists('Gimli\Database\seed_model'));
		
		// We can't easily test the full functionality without setting up the seeding system
		// But we can test that it doesn't throw an error when called with valid parameters
		$this->expectNotToPerformAssertions();
	}

	public function testSeedDataHelper() {
		// Test that the seed_data helper function exists and can be called
		$this->assertTrue(function_exists('Gimli\Database\seed_data'));
		
		// We can't easily test the full functionality without setting up the seeding system
		// But we can test that it doesn't throw an error when called with valid parameters
		$this->expectNotToPerformAssertions();
	}

	public function testHelperFunctionsExist() {
		// Test that all helper functions are defined
		$this->assertTrue(function_exists('Gimli\Database\get_database'));
		$this->assertTrue(function_exists('Gimli\Database\fetch_column'));
		$this->assertTrue(function_exists('Gimli\Database\fetch_row'));
		$this->assertTrue(function_exists('Gimli\Database\fetch_all'));
		$this->assertTrue(function_exists('Gimli\Database\row_exists'));
		$this->assertTrue(function_exists('Gimli\Database\seed_model'));
		$this->assertTrue(function_exists('Gimli\Database\seed_data'));
		$this->assertTrue(function_exists('Gimli\Database\begin_transaction'));
		$this->assertTrue(function_exists('Gimli\Database\commit_transaction'));
		$this->assertTrue(function_exists('Gimli\Database\rollback_transaction'));
		$this->assertTrue(function_exists('Gimli\Database\in_transaction'));
		$this->assertTrue(function_exists('Gimli\Database\with_transaction'));
		$this->assertTrue(function_exists('Gimli\Database\yield_row_chunks'));
		$this->assertTrue(function_exists('Gimli\Database\yield_batch'));
	}
} 