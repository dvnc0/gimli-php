# Routing System

Gimli includes a powerful and flexible routing system that supports HTTP requests, RESTful routing, route groups, middleware, and CLI commands. This document explains how to use the routing features to structure your application.

## Core Components

The Routing system consists of these key components:

- **Route**: A static class that provides a fluent interface for defining routes
- **Router**: The main router that matches requests to routes and dispatches them
- **Dispatch**: Handles the response output
- **CLI Parser**: Parses command-line arguments for CLI routes

## Defining Routes

### Basic Routes

You can define routes for different HTTP methods:

```php
<?php
use Gimli\Router\Route;

// Basic GET route
Route::get('/', function() {
    echo "Welcome to the homepage";
});

// POST route
Route::post('/submit', function() {
    // Process form submission
    echo "Form submitted";
});

// Other HTTP methods
Route::put('/users/1', function() { /* ... */ });
Route::patch('/users/1', function() { /* ... */ });
Route::delete('/users/1', function() { /* ... */ });

// Match any HTTP method
Route::any('/contact', function() {
    // This will match GET, POST, PUT, PATCH, DELETE
});
```

### Route Handlers

Gimli supports different types of route handlers:

```php
<?php
// 1. Closure/anonymous function
Route::get('/welcome', function() {
    echo "Welcome!";
});

// 2. Single action controller (calls the __invoke method)
Route::get('/dashboard', DashboardController::class);

// 3. Controller and method using string notation
Route::get('/users', 'UserController@index');

// 4. Controller and method using array notation
Route::get('/users/create', [UserController::class, 'create']);
```

### Route Parameters

You can define routes with dynamic parameters:

```php
<?php
// Basic parameter
Route::get('/users/:id', function($id) {
    echo "User ID: " . $id;
});

// Named parameter
Route::get('/posts/:integer#post_id/comments/:id#comment_id', function(int $post_id, $comment_id) {
    echo "Post ID: " . $post_id . ", Comment ID: " . $comment_id;
});
```

Gimli supports various parameter patterns:

| Pattern | Description | Example |
|---------|-------------|---------|
| `:all` | Any character except / | `/product/:all` |
| `:alpha` | Alphabetic characters | `/category/:alpha` |
| `:alphanumeric` | Alphanumeric characters | `/username/:alphanumeric` |
| `:integer` | Integer values | `/page/:integer` |
| `:numeric` | Numeric values (including floats) | `/price/:numeric` |
| `:id` | ID values (numbers, hyphens, underscores) | `/post/:id` |
| `:slug` | URL-friendly slugs | `/article/:slug` |

### Type Casting

Route parameters are automatically typecast when declared in your controller methods:

```php
<?php
// Controller class
class ProductController {
    public function show(int $id) {
        // $id is automatically cast to integer
    }
    
    public function price(float $price) {
        // $price is automatically cast to float
    }
}

// Route definition
Route::get('/products/:id', [ProductController::class, 'show']);
Route::get('/price/:numeric#price', [ProductController::class, 'price']);
```

## Route Groups

You can group related routes and apply middleware to them:

```php
<?php
Route::group('/admin', function() {
    // All routes here will be prefixed with '/admin'
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/settings', [AdminController::class, 'settings']);
    
    // Nested groups
    Route::group('/reports', function() {
        // This route will be '/admin/reports/sales'
        Route::get('/sales', [ReportController::class, 'sales']);
    });
}, [AdminMiddleware::class]);
```

## Middleware

You can add middleware to routes and route groups:

```php
<?php
// Add middleware to a specific route
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->addMiddleware(AuthMiddleware::class);

// Add middleware to a group of routes
Route::group('/admin', function() {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
}, [AdminMiddleware::class, LogRequestMiddleware::class]);
```

Middleware classes need to implement the `Middleware_Interface`:

```php
<?php
use Gimli\Middleware\Middleware_Interface;
use Gimli\Middleware\Middleware_Response;

class AuthMiddleware implements Middleware_Interface {
    public function process(): Middleware_Response {
        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            // Redirect to login page if not authenticated
            return new Middleware_Response(false, '/login');
        }
        
        // Allow request to continue
        return new Middleware_Response(true);
    }
    
    private function isAuthenticated(): bool {
        // Authentication logic here
        return isset($_SESSION['user_id']);
    }
}
```

## CLI Commands

Gimli also supports CLI routes for command-line operations:

```php
<?php
// Define a CLI command
Route::cli('generate-sitemap', [SitemapGenerator::class, 'generate']);

// Using a single action controller
Route::cli('clear-cache', ClearCacheCommand::class);
```

CLI command handlers receive parsed CLI arguments:

```php
<?php
class ClearCacheCommand {
    public function __invoke(string $subcommand = '', array $options = [], array $flags = []) {
        // $subcommand: The first argument after the command name
        // $options: Named options like --name=value
        // $flags: Boolean flags like --force
        
        if (in_array('all', $flags)) {
            // Clear all caches
        } elseif (isset($options['type'])) {
            // Clear specific cache type
        }
        
        return new Response("Cache cleared successfully!");
    }
}
```

Example CLI usage:
```bash
# Basic command
php index.php clear-cache

# With subcommand
php index.php clear-cache views

# With options and flags
php index.php clear-cache --type=views --verbose
```

## Using Controllers

Controllers work seamlessly with the routing system. When defining a route with a controller, Gimli uses dependency injection to resolve the controller and automatically injects any dependencies:

```php
<?php
namespace App\Controllers;

use Gimli\Http\Request;
use Gimli\Http\Response;
use App\Services\UserService;

class UserController {
    public function __construct(
        protected UserService $userService
    ) {
        // UserService is automatically injected
    }
    
    public function show(Response $response, int $id): Response {
        // Response is injected
        // $id comes from the route parameter
        
        $user = $this->userService->find($id);
        
        if (!$user) {
            return $response->setResponse("User not found", 404);
        }
        
        return $response->setResponse("User: " . $user->name);
    }
}

// Route definition
Route::get('/users/:id', [UserController::class, 'show']);
```

## Organizing Routes

For better organization, it's recommended to define routes in separate files. By default, Gimli loads routes from the directory specified in your configuration:

```php
<?php
// App/Routes/web.php
Route::get('/', [HomeController::class, 'index']);
Route::get('/about', [PageController::class, 'about']);

// App/Routes/api.php
Route::group('/api', function() {
    Route::get('/users', [ApiController::class, 'getUsers']);
});

// App/Routes/admin.php
Route::group('/admin', function() {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
}, [AdminMiddleware::class]);
```

## Response Handling

Controller methods should return a `Response` object:

```php
<?php
use Gimli\Http\Response;

class ProductController {
    public function index(Response $response): Response {
        return $response->setResponse("Product listing");
    }
    
    public function store(Response $response): Response {
        // After creating a product
        return $response
            ->setResponse("Product created", response_code:201)
            ->addHeader("X-Product-ID: 123");
    }
    
    public function apiIndex(Response $response): Response {
        $data = [/* ... */];
        return $response->setJsonResponse($data);
    }
}
```

## Error Handling

The router handles basic error scenarios, but you should define custom error handling for more advanced use cases:

```php
<?php
// Custom 404 handler
$App->Injector->bind('NotFoundHandler', function() {
    return function() {
        $response = new Response();
        return $response
            ->setResponse("Custom 404 page not found", response_code:404);
    };
});
```

## Best Practices

1. **Organize routes by domain**
   - Group related routes in separate files (web.php, api.php, admin.php)

2. **Use descriptive route names**
   - Choose clear route paths that reflect the resource or action

3. **Keep controllers focused**
   - Follow RESTful principles with focused controller methods

4. **Use middleware for cross-cutting concerns**
   - Authentication, logging, rate limiting, etc.

5. **Use route groups for common prefixes and middleware**
   - Avoid repeating the same prefixes or middleware declarations

6. **Return Response objects from controllers**
   - For consistent response handling and header management
   
[Home](https://dvnc0.github.io/gimli-php/)