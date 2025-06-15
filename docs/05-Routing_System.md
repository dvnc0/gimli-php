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

| Pattern | Description | Validation |
|---------|-------------|------------|
| `:all` | Any character except / | Alphanumeric + safe chars, max 100 chars |
| `:alphanumeric` | Alphanumeric characters | Only a-zA-Z0-9, max 50 chars |
| `:alpha` | Alphabetic characters | Only a-zA-Z, max 50 chars |
| `:integer` | Integer values | Only digits, max 10 digits |
| `:numeric` | Numeric values | Proper decimal format |
| `:id` | ID values | Positive integers only, max 10 digits |
| `:slug` | URL-friendly slugs | Alphanumeric + hyphens, max 100 chars |
| `:uuid` | UUID values | Standard UUID format |

### Parameter Validation & Security

Route parameters are automatically validated for security:

```php
<?php
// Route definition with typed parameters
Route::get('/product/:id#product_id/:slug#name', [ProductController::class, 'show']);

// Router performs validation:
// 1. Length limits (prevents buffer overflows)
// 2. Character set validation (prevents injection)
// 3. Type validation (ensures expected data type)
// 4. Automatic sanitization
```

The Router automatically:
- Validates parameter length (prevents buffer overflows)
- Validates character sets (prevents injection)
- Checks parameter against expected type
- Sanitizes parameter values before passing to handlers

### Type Casting

Route parameters are automatically typecast when declared in your controller methods:

```php
<?php
// Controller class
class ProductController {
    public function show(int $id, string $name) {
        // $id is automatically cast to integer
        // $name is automatically sanitized as string
    }
    
    public function price(float $price) {
        // $price is automatically cast to float
    }
}

// Route definition
Route::get('/products/:id#id/:slug#name', [ProductController::class, 'show']);
Route::get('/price/:numeric#price', [ProductController::class, 'price']);
```

The Router supports safe type casting for:
- `int`/`integer`: Validated and cast to integer
- `float`/`double`: Validated and cast to floating point
- `string`: Sanitized properly for XSS protection
- `bool`/`boolean`: Cast to boolean value

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

// Add multiple middleware to a route
Route::get('/admin/settings', [SettingsController::class, 'index'])
    ->addMiddleware(AuthMiddleware::class)
    ->addMiddleware(AdminMiddleware::class);

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

### CLI Parser

Gimli's `Cli_Parser` handles different argument formats:

```bash
# Basic command with subcommand
php index.php clear-cache views

# With options (parsed as key-value)
php index.php deploy --environment=production --verbose

# With flags (boolean flags)
php index.php migrate --reset --force
```

The parser correctly handles:
- Space-separated options: `--option value`
- Equal-sign options: `--option=value`
- Multi-word values: `--option="value with spaces"`
- Multiple flags: `--verbose --force`
- Mixed formats: `--env=prod --verbose --timeout 30`

## CSRF Protection

Gimli provides built-in CSRF protection that integrates with the routing system:

```php
<?php
use Gimli\View\Csrf;

// In your form view:
<form method="post" action="/submit">
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <!-- Form fields -->
    <button type="submit">Submit</button>
</form>

// In your route handler or controller:
public function handleSubmit(array $post_data) {
    // Validate CSRF token
    if (!Csrf::validateRequest($post_data)) {
        // Invalid token, reject request
        return new Response("Invalid request", 403);
    }
    
    // Process form submission
}
```

Security features of CSRF protection:
- Cryptographically secure tokens using `random_bytes()`
- Token expiration (15 minutes by default)
- One-time use tokens (deleted after verification)
- Token rotation to prevent reuse
- Protection against token flooding
- Timing-safe comparison to prevent timing attacks

For more details, see [CSRF Protection](06-CSRF_Protection.md).

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

## Session Integration

The Router integrates with Gimli's secure Session handling:

```php
<?php
// Middleware that requires session
class AuthMiddleware implements Middleware_Interface {
    public function __construct(
        protected Session $Session
    ) {
        // Session is automatically injected
    }
    
    public function process(): Middleware_Response {
        if (!$this->Session->has('user_id')) {
            return new Middleware_Response(false, '/login');
        }
        
        return new Middleware_Response(true);
    }
}
```

The Session class provides:
- Secure session initialization
- HTTPS detection and secure cookie configuration
- Session fingerprinting for security
- Protection against session fixation
- Automatic session regeneration
- Session timeouts (inactivity and absolute)

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

The router handles basic error scenarios with default responses:

```php
// When a route is not found, the Router outputs:
http_response_code(404);
echo "404 page not found";

// When route parameters fail validation, the Router outputs:
http_response_code(400);
echo "Bad Request: Invalid parameters";
```

To implement custom error handling, you can create a custom middleware that checks for specific conditions or create dedicated error routes:

```php
<?php
// Create a route for handling 404 errors
Route::get('/error/404', [ErrorController::class, 'notFound']);

// Create a middleware that can redirect to error pages
class ErrorHandlingMiddleware implements Middleware_Interface {
    public function process(): Middleware_Response {
        // Your custom error handling logic
        if ($someErrorCondition) {
            return new Middleware_Response(false, '/error/404');
        }
        
        return new Middleware_Response(true);
    }
}
```

## Best Practices

1. **Organize routes by domain**
   - Group related routes in separate files (web.php, api.php, admin.php)

2. **Use descriptive route names**
   - Choose clear route paths that reflect the resource or action

3. **Keep controllers focused**
   - Follow RESTful principles with focused controller methods

4. **Use middleware for cross-cutting concerns**
   - Authentication, logging, rate limiting, CSRF protection, etc.

5. **Use route groups for common prefixes and middleware**
   - Avoid repeating the same prefixes or middleware declarations

6. **Return Response objects from controllers**
   - For consistent response handling and header management

7. **Validate and sanitize parameters**
   - Use the built-in parameter type validation and casting

8. **Keep route files clean**
   - Extract complex logic to controllers and services
   
[Home](https://dvnc0.github.io/gimli-php/)