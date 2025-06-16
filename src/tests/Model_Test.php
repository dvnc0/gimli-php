<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Database\Model;
use Gimli\Database\Database;

class Model_Test extends TestCase {

	private function getDatabaseMock(): Database {
		return $this->createMock(Database::class);
	}

	private function getTestModel(?Database $database = null): Model {
		$database = $database ?? $this->getDatabaseMock();
		/**
		 * @var Model $model
		 */
		return new class($database) extends Model {
			protected string $table_name = 'test_table';
			protected string $primary_key = 'id';
			
			/**
			 * @var int $id
			 */
			public $id;

			/**
			 * @var string $name
			 */
			public $name;

			/**
			 * @var string $email
			 */
			public $email;
			
			// Expose protected methods for testing
			public function callBeforeSave(): void {
				$this->beforeSave();
			}
			
			public function callAfterSave(): void {
				$this->afterSave();
			}
			
			public function callAfterLoad(): void {
				$this->afterLoad();
			}
		};
	}

	public function testConstructor() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$this->assertInstanceOf(Model::class, $model);
	}

	public function testLoadSuccess() {
		$database = $this->getDatabaseMock();
		$testData = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
		
		$database->expects($this->once())
			->method('fetchRow')
			->with(
				$this->stringContains('SELECT * FROM test_table'),
				['param' => 'value']
			)
			->willReturn($testData);
		
		$model = $this->getTestModel($database);
		$result = $model->load('id = :param', ['param' => 'value']);
		
		$this->assertTrue($result);
		$this->assertTrue($model->isLoaded());
		$this->assertEquals(1, $model->id);
		$this->assertEquals('John', $model->name);
		$this->assertEquals('john@example.com', $model->email);
	}

	public function testLoadFailure() {
		$database = $this->getDatabaseMock();
		
		$database->expects($this->once())
			->method('fetchRow')
			->willReturn([]);
		
		$model = $this->getTestModel($database);
		$result = $model->load('id = 999');
		
		$this->assertFalse($result);
		$this->assertFalse($model->isLoaded());
	}

	public function testSaveInsertNewRecord() {
		$database = $this->getDatabaseMock();
		
		$database->expects($this->once())
			->method('insert')
			->with('test_table', ['name' => 'John', 'email' => 'john@example.com'])
			->willReturn(true);
			
		$database->expects($this->once())
			->method('lastInsertId')
			->willReturn('123');
		
		$model = $this->getTestModel($database);
		$model->name = 'John';
		$model->email = 'john@example.com';
		
		$result = $model->save();
		
		$this->assertTrue($result);
		$this->assertTrue($model->isLoaded());
		$this->assertEquals(123, $model->id);
	}

	public function testSaveUpdateExistingRecord() {
		$database = $this->getDatabaseMock();
		
		$database->expects($this->once())
			->method('update')
			->with(
				'test_table',
				'id = :id',
				['name' => 'Jane', 'email' => 'jane@example.com'],
				[':id' => 1]
			)
			->willReturn(true);
		
		$model = $this->getTestModel($database);
		$model->id = 1;
		$model->name = 'Jane';
		$model->email = 'jane@example.com';
		
		// Simulate loaded state
		$reflection = new \ReflectionClass($model);
		$property = $reflection->getProperty('is_loaded');
		$property->setAccessible(true);
		$property->setValue($model, true);
		
		$result = $model->save();
		
		$this->assertTrue($result);
	}

	public function testReset() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$model->id = 1;
		$model->name = 'John';
		$model->email = 'john@example.com';
		
		// Simulate loaded state
		$reflection = new \ReflectionClass($model);
		$property = $reflection->getProperty('is_loaded');
		$property->setAccessible(true);
		$property->setValue($model, true);
		
		$model->reset();
		
		$this->assertFalse($model->isLoaded());
	}

	public function testGetData() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$model->id = 1;
		$model->name = 'John';
		$model->email = 'john@example.com';
		
		$data = $model->getData();
		
		$this->assertEquals([
			'id' => 1,
			'name' => 'John',
			'email' => 'john@example.com'
		], $data);
	}

	public function testIsLoaded() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$this->assertFalse($model->isLoaded());
		
		// Simulate loaded state
		$reflection = new \ReflectionClass($model);
		$property = $reflection->getProperty('is_loaded');
		$property->setAccessible(true);
		$property->setValue($model, true);
		
		$this->assertTrue($model->isLoaded());
	}

	public function testLoadFromDataSet() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$data = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
		$model->loadFromDataSet($data);
		
		$this->assertTrue($model->isLoaded());
		// $this->assertEquals(1, $model->id);
		$this->assertEquals('John', $model->name);
		$this->assertEquals('john@example.com', $model->email);
	}

	public function testLoadFromDataSetNotLoaded() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$data = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
		$model->loadFromDataSet($data, false);
		
		$this->assertFalse($model->isLoaded());
		// $this->assertEquals(1, $model->id);
		$this->assertEquals('John', $model->name);
		$this->assertEquals('john@example.com', $model->email);
	}

	public function testCreateFromDataSet() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$data = ['name' => 'John', 'email' => 'john@example.com'];
		$model->createFromDataSet($data);
		
		$this->assertFalse($model->isLoaded());
		$this->assertEquals('John', $model->name);
		$this->assertEquals('john@example.com', $model->email);
	}

	public function testIgnoredFieldsNotIncludedInData() {
		$database = $this->getDatabaseMock();
		$model = $this->getTestModel($database);
		
		$model->id = 1;
		$model->name = 'John';
		
		$data = $model->getData();
		
		// Should not include ignored fields like Database, table_name, etc.
		$this->assertArrayNotHasKey('Database', $data);
		$this->assertArrayNotHasKey('table_name', $data);
		$this->assertArrayNotHasKey('primary_key', $data);
		$this->assertArrayNotHasKey('is_loaded', $data);
		$this->assertArrayNotHasKey('ignored_fields', $data);
	}

	public function testSaveExcludesPrimaryKeyFromInsertData() {
		$database = $this->getDatabaseMock();
		
		$database->expects($this->once())
			->method('insert')
			->with('test_table', ['name' => 'John', 'email' => 'john@example.com'])
			->willReturn(true);
			
		$database->method('lastInsertId')->willReturn('123');
		
		$model = $this->getTestModel($database);
		$model->id = 999; // This should be excluded from insert
		$model->name = 'John';
		$model->email = 'john@example.com';
		
		$model->save();
	}

	public function testLifecycleHooks() {
		$database = $this->getDatabaseMock();
		$database->method('insert')->willReturn(true);
		$database->method('lastInsertId')->willReturn('123');
		
		$model = $this->getTestModel($database);
		
		// Test that hooks can be called (they're empty by default but shouldn't error)
		$model->callBeforeSave();
		$model->callAfterSave();
		$model->callAfterLoad();
		
		$this->assertTrue(true); // If we get here, hooks executed without error
	}

	public function testLoadGeneratesCorrectSql() {
		$database = $this->getDatabaseMock();
		
		$database->expects($this->once())
			->method('fetchRow')
			->with(
				$this->callback(function($sql) {
					return strpos($sql, 'SELECT * FROM test_table') !== false &&
						   strpos($sql, 'WHERE id = :id') !== false &&
						   strpos($sql, 'ORDER BY id ASC') !== false &&
						   strpos($sql, 'LIMIT 1') !== false;
				}),
				[':id' => 1]
			)
			->willReturn(['id' => 1, 'name' => 'John']);
		
		$model = $this->getTestModel($database);
		$model->load('id = :id', [':id' => 1]);
	}
} 