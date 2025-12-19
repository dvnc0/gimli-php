# Database System

Gimli provides a robust database system built on PDO that simplifies database operations while providing powerful features like models, transactions, seeding, and data generation.

## Core Components

The database system consists of these key components:

- **Database**: Main class that provides database operations
- **PDO Manager**: Handles PDO connection management
- **Model**: Base class for building data models
- **Seeder**: Data generation system for testing and development
- **Faker**: Generates realistic fake data for seeds

## Configuration

Configure your database connection in your config.ini file:

```ini
[database]
driver = "mysql"
host = "localhost"
database = "my_database"
username = "db_user"
password = "db_password"
port = 3306
```

## Basic Database Operations

The Database class provides methods for common operations like queries, insert, update, and transactions.

### Helper Functions

Gimli provides simple helper functions for common database tasks:

```php
<?php
use function Gimli\Database\fetch_all;
use function Gimli\Database\fetch_row;
use function Gimli\Database\fetch_column;
use function Gimli\Database\row_exists;
use function Gimli\Database\get_database;
use function Gimli\Database\insert;
use function Gimli\Database\update;
use function Gimli\Database\insert_batch;

// Get all rows from a table
$users = fetch_all("SELECT * FROM users WHERE status = ?", [1]);

// Get a single row
$user = fetch_row("SELECT * FROM users WHERE id = ?", [123]);

// Get a single value
$count = fetch_column("SELECT COUNT(*) FROM users");

// Check if a row exists
if (row_exists("SELECT 1 FROM users WHERE email = ?", ['user@example.com'])) {
    // User with this email exists
}

// Direct access to the database instance
$Database = get_database();

// Insert a row
insert('users', ['name' => 'John', 'email' => 'user@example.com']);

// Update a row
update('users', 'email = :email', ['name' => 'James'], ['email' => 'user@example.com']);

// Insert multiple rows in a single query

$users = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'James', 'email' => 'james@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
];

insert_batch('users', $users);

```

### Using the Database Class Directly

```php
<?php
use Gimli\Application_Registry;
use Gimli\Database\Database;

$Database = Application_Registry::get()->Injector->resolve(Database::class);

// Execute a query
$Database->execute("UPDATE users SET status = ? WHERE id = ?", [1, 123]);

// Insert a row
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
];
$Database->insert('users', $data);

// Get the last insert ID
$id = $Database->lastInsertId();

// Update a row
$where = "id = :id";
$data = ['status' => 2, 'updated_at' => date('Y-m-d H:i:s')];
$params = [':id' => 123];
$Database->update('users', $where, $data, $params);

// Fetch data
$users = $Database->fetchAll("SELECT * FROM users LIMIT 10");
$user = $Database->fetchRow("SELECT * FROM users WHERE id = ?", [123]);
$count = $Database->fetchColumn("SELECT COUNT(*) FROM users");
```

## Working with Generators

For memory-efficient processing of large datasets, Gimli provides generator methods:

```php
<?php
use function Gimli\Database\yield_row_chunks;
use function Gimli\Database\yield_batch;

// Process rows one at a time
$Database = get_database();
foreach ($Database->yieldRows("SELECT * FROM logs") as $row) {
    // Process each row individually without loading all into memory
}

// Process rows in chunks
foreach (yield_row_chunks("SELECT * FROM logs", [], 500) as $chunk) {
    // $chunk is an array of up to 500 rows
    foreach ($chunk as $row) {
        // Process each row
    }
}

// Process with database-level pagination
foreach (yield_batch("SELECT * FROM users", [], 1000, "id") as $batch) {
    // Each batch is fetched with LIMIT/OFFSET for efficiency
}
```

## Transaction Management

Gimli provides several ways to work with transactions:

```php
<?php
use function Gimli\Database\begin_transaction;
use function Gimli\Database\commit_transaction;
use function Gimli\Database\rollback_transaction;
use function Gimli\Database\with_transaction;
use function Gimli\Database\in_transaction;

// Manual transaction management
try {
    begin_transaction();
    
    // Your database operations here
    
    commit_transaction();
} catch (\Exception $e) {
    rollback_transaction();
    throw $e;
}

// Or use the callback-based approach (recommended)
$result = with_transaction(function($Database) {
    // Your database operations here
    // Automatically commits on success or rolls back on exception
    return $someValue;
});

// Check if currently in a transaction
if (in_transaction()) {
    // We're inside a transaction
}
```

## Working with Models

Gimli provides a base Model class for your data models:

```php
<?php
namespace App\Models;

use Gimli\Database\Model;

class User extends Model {
    // Table name for this model
    protected string $table_name = 'users';
    
    // Primary key column (defaults to 'id')
    protected string $primary_key = 'id';
    
    // Additional properties will be populated from the database
    public $id;
    public $username;
    public $email;
    public $created_at;
    
    // Hook that runs before saving
    protected function beforeSave(): void {
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }
    
    // Hook that runs after saving
    protected function afterSave(): void {
        // For example, clear cache after saving
    }
    
    // Hook that runs after loading
    protected function afterLoad(): void {
        // For example, format dates after loading
    }
}
```

### Using Models

```php
<?php
use App\Models\User;
use function Gimli\Injector\resolve;

// Create a new model instance
$User = resolve(User::class);

// Load a user by ID
$User->load("id = ?", [123]);

// Check if the model is loaded from the database
if ($User->isLoaded()) {
    // Access properties
    echo $User->username;
    
    // Update and save
    $User->status = 'active';
    $User->save();
}

// Create a new user
$NewUser = resolve(User::class);
$NewUser->username = 'johndoe';
$NewUser->email = 'john@example.com';
$NewUser->save();

// Get model data as array
$userData = $User->getData();

// Reset the model
$User->reset();
```

## Data Seeding

Gimli includes a powerful seeding system for generating test data:

### Defining Model with Seed Attributes

```php
<?php
namespace App\Models;

use Gimli\Database\Model;
use Gimli\Database\Seed;

class Product extends Model {
    protected string $table_name = 'products';
    
    public $id;
    
    #[Seed(type: 'random_string', args: ['length' => 10, 'prefix' => 'PROD-'])]
    public $sku;
    
    #[Seed(type: 'words', args: ['count' => 4])]
    public $name;
    
    #[Seed(type: 'paragraph', args: ['count' => 2])]
    public $description;
    
    #[Seed(type: 'money', args: [10.0, 1000.0])]
    public $price;
    
    #[Seed(type: 'one_of', args: [[0, 1, 2]])]
    public $status;
    
    #[Seed(type: 'date', args: ['format' => 'Y-m-d H:i:s', 'min' => '2023-01-01', 'max' => '2023-12-31'])]
    public $created_at;
}
```

### Seeding Data

```php
<?php
use Gimli\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use function Gimli\Database\seed_model;

// Simple seeding using helper function
seed_model(Product::class, 10);

// More control using Seeder class
Seeder::make(Product::class)
    ->seed(123) // Specific seed for reproducible results
    ->count(50) // Create 50 products
    ->create();

// Seeding with predefined values
Seeder::make(Product::class)
    ->using(['status' => 1, 'featured' => true])
    ->count(5)
    ->create();

// Get seeded data without saving to database
$productData = Seeder::make(Product::class)
    ->seed(123)
    ->getSeededData();

// Related data with callbacks
Seeder::make(Product::class)
    ->seed(123)
    ->count(10)
    ->callback(function($productData) {
        return [
            Seeder::make(ProductCategory::class)
                ->using(['product_id' => $productData['id']])
                ->seed(456)
        ];
    })
    ->create();
```

## Available Faker Types

The Faker system provides various data types for seeding:

| Type | Description | Example Args |
|------|-------------|-------------|
| `int` / `integer` / `number` | Random integer | `[1, 100]` (min, max) |
| `float` / `decimal` / `money` | Random float | `[10.0, 1000.0]` (min, max) |
| `one_of` | Random item from array | `[[1, 2, 3]]` (options) |
| `date` | Random date | `['format' => 'Y-m-d', 'min' => '2020-01-01', 'max' => '2023-12-31']` |
| `bool` | Random boolean | None |
| `email` | Random email address | None |
| `unique_id` / `random_string` | Random string | `['length' => 10, 'prefix' => 'ID-']` |
| `first_name` | Random first name | None |
| `last_name` | Random last name | None |
| `full_name` | Random full name | None |
| `words` / `sentence` / `short_text` | Random words | `['count' => 5]` |
| `paragraph` / `long_text` | Random paragraphs | `['count' => 2]` |
| `password` | Hashed password | `['password' => 'secret', 'salt' => '']` |
| `username` | Random username | None |
| `phone_number` | Random phone number | None |
| `url` | Random URL | None |
| `address` | Random address | None |
| `city` | Random city | None |
| `state` | Random state abbreviation | None |
| `state_full` | Random state name | None |
| `zip` | Random ZIP code | None |
| `tiny_int` | Random 0 or 1 | None |
| `always` | Always returns the same value | `['value']` |

## Database Events

The Database class publishes events throughout its lifecycle, allowing you to monitor performance, implement logging, caching, or other cross-cutting concerns.

### Core Operation Events

```php
use function Gimli\Events\subscribe_event;

// Monitor all database queries
subscribe_event('gimli.database.start', function(string $event, array $data) {
    // $data contains: sql, time
    error_log("Query started: " . $data['sql']);
});

subscribe_event('gimli.database.end', function(string $event, array $data) {
    // $data contains: sql, time
    $duration = $data['time'] - $GLOBALS['query_start_time'];
    error_log("Query completed in {$duration}s: " . $data['sql']);
});
```

### Insert/Update Events

```php
// Monitor insert operations
subscribe_event('gimli.database.insert.start', function(string $event, array $data) {
    // $data contains: table, data, time
    error_log("Inserting into {$data['table']}");
});

subscribe_event('gimli.database.insert.end', function(string $event, array $data) {
    // $data contains: table, success, time
    if ($data['success']) {
        error_log("Successfully inserted into {$data['table']}");
    }
});

// Monitor update operations
subscribe_event('gimli.database.update.start', function(string $event, array $data) {
    // $data contains: table, data, time
});

subscribe_event('gimli.database.update.end', function(string $event, array $data) {
    // $data contains: table, success, time
});
```

### Fetch Operation Events

```php
// Monitor fetch operations
subscribe_event('gimli.database.fetch.start', function(string $event, array $data) {
    // $data contains: operation (fetchAll/fetchRow/fetchColumn), sql, time
    error_log("Starting {$data['operation']} operation");
});

subscribe_event('gimli.database.fetch.end', function(string $event, array $data) {
    // For fetchAll: operation, count, time
    // For fetchRow: operation, found, time  
    // For fetchColumn: operation, result, time
    
    if ($data['operation'] === 'fetchAll') {
        error_log("Fetched {$data['count']} rows");
    } elseif ($data['operation'] === 'fetchRow') {
        error_log("Row " . ($data['found'] ? 'found' : 'not found'));
    }
});
```

### Generator/Streaming Events

```php
// Monitor generator operations
subscribe_event('gimli.database.yield.start', function(string $event, array $data) {
    // $data contains: operation (yieldRows/yieldRowChunks), sql, time
    // For yieldRowChunks also includes: chunk_size
});

subscribe_event('gimli.database.yield.end', function(string $event, array $data) {
    // For yieldRows: operation, count, time
    // For yieldRowChunks: operation, chunks, total_rows, time
    
    if ($data['operation'] === 'yieldRowChunks') {
        error_log("Processed {$data['chunks']} chunks with {$data['total_rows']} total rows");
    }
});

// Monitor batch operations
subscribe_event('gimli.database.batch.start', function(string $event, array $data) {
    // $data contains: operation (yieldBatch), batch_size, sql, time
});

subscribe_event('gimli.database.batch.end', function(string $event, array $data) {
    // $data contains: operation, batches, total_rows, time
    error_log("Processed {$data['batches']} batches with {$data['total_rows']} total rows");
});
```

### Transaction Events

```php
// Monitor individual transaction operations
subscribe_event('gimli.database.transaction.begin', function(string $event, array $data) {
    // $data contains: time
    error_log("Beginning transaction");
});

subscribe_event('gimli.database.transaction.started', function(string $event, array $data) {
    // $data contains: success, time
});

subscribe_event('gimli.database.transaction.commit', function(string $event, array $data) {
    // $data contains: time
});

subscribe_event('gimli.database.transaction.committed', function(string $event, array $data) {
    // $data contains: success, time
});

subscribe_event('gimli.database.transaction.rollback', function(string $event, array $data) {
    // $data contains: time
});

subscribe_event('gimli.database.transaction.rolledback', function(string $event, array $data) {
    // $data contains: success, time
});

// Monitor transaction wrapper
subscribe_event('gimli.database.transaction.wrapper.start', function(string $event, array $data) {
    // $data contains: nested (bool), time
    if ($data['nested']) {
        error_log("Starting nested transaction");
    }
});

subscribe_event('gimli.database.transaction.wrapper.success', function(string $event, array $data) {
    // $data contains: nested (bool), time
});

subscribe_event('gimli.database.transaction.wrapper.error', function(string $event, array $data) {
    // $data contains: nested (bool), error (string), time
    error_log("Transaction failed: " . $data['error']);
});
```

### Event Usage Examples

#### Performance Monitoring

```php
use function Gimli\Events\subscribe_event;

class Database_Performance_Monitor {
    private array $query_times = [];
    
    public function __construct() {
        subscribe_event('gimli.database.start', [$this, 'startQuery']);
        subscribe_event('gimli.database.end', [$this, 'endQuery']);
    }
    
    public function startQuery(string $event, array $data): void {
        $this->query_times[$data['sql']] = $data['time'];
    }
    
    public function endQuery(string $event, array $data): void {
        $start = $this->query_times[$data['sql']] ?? $data['time'];
        $duration = $data['time'] - $start;
        
        if ($duration > 1.0) { // Log slow queries
            error_log("Slow query ({$duration}s): " . $data['sql']);
        }
        
        unset($this->query_times[$data['sql']]);
    }
}
```

## Complete Event Reference

| Event Name | Trigger | Data Fields |
|------------|---------|-------------|
| `gimli.database.start` | Before any SQL execution | `sql`, `time` |
| `gimli.database.end` | After any SQL execution | `sql`, `time` |
| `gimli.database.insert.start` | Before insert operation | `table`, `data`, `time` |
| `gimli.database.insert.end` | After insert operation | `table`, `success`, `time` |
| `gimli.database.update.start` | Before update operation | `table`, `data`, `time` |
| `gimli.database.update.end` | After update operation | `table`, `success`, `time` |
| `gimli.database.fetch.start` | Before fetch operation | `operation`, `sql`, `time` |
| `gimli.database.fetch.end` | After fetch operation | `operation`, varies by type, `time` |
| `gimli.database.yield.start` | Before generator operation | `operation`, `sql`, `time`, optional `chunk_size` |
| `gimli.database.yield.end` | After generator operation | `operation`, varies by type, `time` |
| `gimli.database.batch.start` | Before batch operation | `operation`, `batch_size`, `sql`, `time` |
| `gimli.database.batch.end` | After batch operation | `operation`, `batches`, `total_rows`, `time` |
| `gimli.database.transaction.begin` | Before transaction start | `time` |
| `gimli.database.transaction.started` | After transaction start | `success`, `time` |
| `gimli.database.transaction.commit` | Before transaction commit | `time` |
| `gimli.database.transaction.committed` | After transaction commit | `success`, `time` |
| `gimli.database.transaction.rollback` | Before transaction rollback | `time` |
| `gimli.database.transaction.rolledback` | After transaction rollback | `success`, `time` |
| `gimli.database.transaction.wrapper.start` | Before transaction wrapper | `nested`, `time` |
| `gimli.database.transaction.wrapper.success` | After successful transaction wrapper | `nested`, `time` |
| `gimli.database.transaction.wrapper.error` | After failed transaction wrapper | `nested`, `error`, `time` |

## Best Practices

1. **Use helper functions for simple queries**
   - The helper functions provide a clean, simple interface for common database operations.

2. **Use models for complex entities**
   - Create model classes for your database entities to encapsulate data and behavior.

3. **Use transactions for multi-step operations**
   - Always wrap related operations in transactions to maintain data integrity.
   - Prefer the `with_transaction()` helper for automatic commit/rollback.

4. **Use generators for large datasets**
   - When working with large result sets, use the generator methods to avoid memory issues.

5. **Use reproducible seeds for testing**
   - Set specific seeds when generating test data to ensure consistency across test runs.

6. **Organize models by domain**
   - Group related models together in namespaces based on their domain/functionality.

7. **Monitor database performance with events**
   - Use database events to track slow queries, monitor transaction patterns, and implement caching strategies.

8. **Handle transaction events appropriately**
   - Use transaction events for cleanup operations, cache invalidation, or audit logging.

[Home](https://dvnc0.github.io/gimli-php/)