<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Database\Database;
use Gimli\Database\Pdo_Manager;
use PDO;
use PDOStatement;
use PDOException;
use InvalidArgumentException;

class Database_Test extends TestCase {

	private function getPdoManagerMock(): Pdo_Manager {
		$pdoMock = $this->createMock(PDO::class);
		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		return $pdoManagerMock;
	}

	private function getPdoMock(): PDO {
		return $this->createMock(PDO::class);
	}

	private function getStatementMock(): PDOStatement {
		return $this->createMock(PDOStatement::class);
	}

	public function testConstructor() {
		$pdoManagerMock = $this->getPdoManagerMock();
		$database = new Database($pdoManagerMock);
		
		$this->assertInstanceOf(Database::class, $database);
	}

	public function testExecute() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with('SELECT * FROM users WHERE id = ?')
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with([1])
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->execute('SELECT * FROM users WHERE id = ?', [1]);
		
		$this->assertTrue($result);
	}

	public function testLastInsertId() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())
			->method('lastInsertId')
			->willReturn('123');

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->lastInsertId();
		
		$this->assertEquals('123', $result);
	}

	public function testUpdate() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$expectedSql = 'UPDATE users SET name = :name, email = :email WHERE id = :id';
		$expectedParams = [':name' => 'John', ':email' => 'john@example.com', ':id' => 1];
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with($expectedSql)
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with($expectedParams)
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->update(
			'users', 
			'id = :id', 
			['name' => 'John', 'email' => 'john@example.com'], 
			[':id' => 1]
		);
		
		$this->assertTrue($result);
	}

	public function testInsert() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$expectedSql = 'INSERT INTO users (name, email) VALUES (:name, :email)';
		$expectedParams = ['name' => 'John', 'email' => 'john@example.com'];
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with($expectedSql)
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with($expectedParams)
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
		
		$this->assertTrue($result);
	}

	public function testFetchAll() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$expectedData = [
			['id' => 1, 'name' => 'John'],
			['id' => 2, 'name' => 'Jane']
		];
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with('SELECT * FROM users')
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with([]);
			
		$stmtMock->expects($this->once())
			->method('fetchAll')
			->with(PDO::FETCH_ASSOC)
			->willReturn($expectedData);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->fetchAll('SELECT * FROM users');
		
		$this->assertEquals($expectedData, $result);
	}

	public function testFetchAllReturnsEmptyArrayWhenNoResults() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$pdoMock->method('prepare')->willReturn($stmtMock);
		$stmtMock->method('fetchAll')->willReturn([]);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->fetchAll('SELECT * FROM users WHERE id = 999');
		
		$this->assertEquals([], $result);
	}

	public function testFetchRow() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$expectedData = ['id' => 1, 'name' => 'John'];
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with('SELECT * FROM users WHERE id = ?')
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with([1]);
			
		$stmtMock->expects($this->once())
			->method('fetch')
			->with(PDO::FETCH_ASSOC)
			->willReturn($expectedData);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->fetchRow('SELECT * FROM users WHERE id = ?', [1]);
		
		$this->assertEquals($expectedData, $result);
	}

	public function testFetchRowReturnsEmptyArrayWhenNoResults() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$pdoMock->method('prepare')->willReturn($stmtMock);
		$stmtMock->method('fetch')->willReturn(false);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->fetchRow('SELECT * FROM users WHERE id = 999');
		
		$this->assertEquals([], $result);
	}

	public function testFetchColumn() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->with('SELECT COUNT(*) FROM users')
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with([]);
			
		$stmtMock->expects($this->once())
			->method('fetchColumn')
			->willReturn('5');

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->fetchColumn('SELECT COUNT(*) FROM users');
		
		$this->assertEquals('5', $result);
	}

	public function testYieldRows() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$testData = [
			['id' => 1, 'name' => 'John'],
			['id' => 2, 'name' => 'Jane'],
			false // End of results
		];
		
		$pdoMock->expects($this->once())
			->method('prepare')
			->willReturn($stmtMock);
			
		$stmtMock->expects($this->once())
			->method('execute')
			->with([]);
			
		$stmtMock->expects($this->exactly(3))
			->method('fetch')
			->with(PDO::FETCH_ASSOC)
			->willReturnOnConsecutiveCalls(...$testData);
			
		$stmtMock->expects($this->once())
			->method('closeCursor');

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$generator = $database->yieldRows('SELECT * FROM users');
		
		$results = [];
		foreach ($generator as $row) {
			$results[] = $row;
		}
		
		$this->assertEquals([
			['id' => 1, 'name' => 'John'],
			['id' => 2, 'name' => 'Jane']
		], $results);
	}

	public function testYieldRowChunks() {
		$pdoMock = $this->getPdoMock();
		$stmtMock = $this->getStatementMock();
		
		$testData = [
			['id' => 1, 'name' => 'John'],
			['id' => 2, 'name' => 'Jane'],
			['id' => 3, 'name' => 'Bob'],
			false // End of results
		];
		
		$pdoMock->method('prepare')->willReturn($stmtMock);
		$stmtMock->method('execute');
		$stmtMock->method('fetch')
			->willReturnOnConsecutiveCalls(...$testData);
		$stmtMock->method('closeCursor');

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$generator = $database->yieldRowChunks('SELECT * FROM users', [], 2);
		
		$chunks = [];
		foreach ($generator as $chunk) {
			$chunks[] = $chunk;
		}
		
		$this->assertEquals([
			[
				['id' => 1, 'name' => 'John'],
				['id' => 2, 'name' => 'Jane']
			],
			[
				['id' => 3, 'name' => 'Bob']
			]
		], $chunks);
	}

	public function testYieldRowChunksThrowsExceptionForInvalidChunkSize() {
		$pdoManagerMock = $this->getPdoManagerMock();
		$database = new Database($pdoManagerMock);
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Chunk size must be greater than 0');
		
		$generator = $database->yieldRowChunks('SELECT * FROM users', [], 0);
		// Need to iterate to trigger the exception
		foreach ($generator as $chunk) {
			break;
		}
	}

	public function testYieldBatchThrowsExceptionForInvalidBatchSize() {
		$pdoManagerMock = $this->getPdoManagerMock();
		$database = new Database($pdoManagerMock);
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Batch size must be greater than 0');
		
		$generator = $database->yieldBatch('SELECT * FROM users', [], 0);
		// Need to iterate to trigger the exception
		foreach ($generator as $batch) {
			break;
		}
	}

	public function testBeginTransaction() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())
			->method('beginTransaction')
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->beginTransaction();
		
		$this->assertTrue($result);
	}

	public function testCommit() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())
			->method('commit')
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->commit();
		
		$this->assertTrue($result);
	}

	public function testRollback() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())
			->method('rollback')
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->rollback();
		
		$this->assertTrue($result);
	}

	public function testInTransaction() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())
			->method('inTransaction')
			->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		$result = $database->inTransaction();
		
		$this->assertTrue($result);
	}

	public function testTransactionCommitsOnSuccess() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
		$pdoMock->expects($this->once())->method('commit')->willReturn(true);
		$pdoMock->expects($this->never())->method('rollback');

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		
		$result = $database->transaction(function() {
			return 'success';
		});
		
		$this->assertEquals('success', $result);
	}

	public function testTransactionRollsBackOnException() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
		$pdoMock->expects($this->never())->method('commit');
		$pdoMock->expects($this->once())->method('rollback')->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Test exception');
		
		$database->transaction(function() {
			throw new \RuntimeException('Test exception');
		});
	}

	public function testTransactionRethrowsException() {
		$pdoMock = $this->getPdoMock();
		$pdoMock->method('beginTransaction')->willReturn(true);
		$pdoMock->method('rollback')->willReturn(true);

		$pdoManagerMock = $this->createMock(Pdo_Manager::class);
		$pdoManagerMock->method('getConnection')->willReturn($pdoMock);
		
		$database = new Database($pdoManagerMock);
		
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Custom exception');
		
		$database->transaction(function() {
			throw new \InvalidArgumentException('Custom exception');
		});
	}
} 