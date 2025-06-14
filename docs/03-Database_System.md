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

[Home](https://dvnc0.github.io/gimli-php/)