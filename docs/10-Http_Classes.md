# HTTP Classes

Gimli provides a set of HTTP classes to handle requests and responses in a clean, object-oriented way. This document explains the key HTTP classes and helper functions available in the framework.

## Request Class

The `Request` class (`Gimli\Http\Request`) encapsulates the HTTP request data, providing a convenient interface to access server variables, query parameters, post data, and headers.

### Basic Usage

The Request object is typically injected into your controllers or middleware:

```php
<?php
use Gimli\Http\Request;

class UserController {
    public function __construct(
        protected Request $Request
    ) {
        // Request is automatically injected
    }
    
    public function profile() {
        // Access request data
        $userId = $this->Request->getQueryParam('id');
        $userAgent = $this->Request->HTTP_USER_AGENT;
        
        // Process request...
    }
}
```

### Server Variables

The Request object provides direct access to common server variables as properties:

```php
$method = $Request->REQUEST_METHOD;  // GET, POST, etc.
$uri = $Request->REQUEST_URI;        // /users/profile?id=123
$userAgent = $Request->HTTP_USER_AGENT;
$remoteAddr = $Request->REMOTE_ADDR;
```

### Query Parameters

To access query string parameters (from the URL):

```php
// For a URL like /search?query=php&page=2
$searchQuery = $Request->getQueryParam('query');  // Returns "php"
$page = $Request->getQueryParam('page');         // Returns "2"

// Get all query parameters as an array
$allParams = $Request->getQueryParams();  // ['query' => 'php', 'page' => '2']
```

### Post Data

To access data submitted via POST, PUT, or PATCH:

```php
// Get a specific post parameter
$username = $Request->getPostParam('username');

// Get all post parameters as an array
$formData = $Request->getPostParams();
```

The Request class automatically handles both form-encoded data and JSON request bodies.

### Headers

All HTTP headers are available through the `headers` property:

```php
// Access a specific header
$contentType = $Request->headers['Content-Type'] ?? '';

// Check if a header exists
$hasAuth = isset($Request->headers['Authorization']);
```

### Route Data

When using the routing system, route parameters are stored in the `route_data` property:

```php
// For a route like /users/:id with URL /users/123
$userId = $Request->route_data['id'];  // Returns "123"
```

## Response Class

The `Response` class (`Gimli\Http\Response`) represents an HTTP response. It provides methods to set the response body, status code, headers, and to handle different response types.

### Basic Usage

```php
<?php
use Gimli\Http\Response;

class UserController {
    public function profile(Response $Response, int $id) {
        $user = $this->userService->find($id);
        
        if (!$user) {
            return $Response->setResponse("User not found", false, [], 404);
        }
        
        return $Response->setResponse("User profile for {$user->name}");
    }
}
```

### Setting Response Data

The Response class provides several methods to set response data:

```php
// Basic text response
$Response->setResponse(
    response_body: "Hello, world!",  // Response content
    success: true,                   // Success flag
    data: ['user' => $user],         // Additional data
    response_code: 200               // HTTP status code
);

// JSON response
$Response->setJsonResponse(
    body: ['user' => $user],         // Data to encode as JSON
    message: "User found",           // Message text
    success: true,                   // Success flag
    response_code: 200               // HTTP status code
);
```

### Setting Headers

You can add custom headers to the response:

```php
$Response->setHeader('Cache-Control: no-cache');
$Response->setHeader('X-Custom-Header: Value');
```

### Response Properties

The Response object has several properties you can access:

```php
$success = $Response->success;       // Boolean success flag
$body = $Response->response_body;    // Response body content
$code = $Response->response_code;    // HTTP status code
$data = $Response->data;             // Additional data array
$isJson = $Response->is_json;        // Whether response is JSON
```

## HTTP Helper Functions

Gimli provides several helper functions to create common response types.

### Basic Response

```php
<?php
use function Gimli\Http\response;

// In a controller or route handler
return response(
    response_body: "Operation completed",
    success: true,
    response_code: 200,
    data: ['result' => $result]
);
```

### JSON Response

```php
<?php
use function Gimli\Http\json_response;

// Create a JSON response
return json_response(
    body: ['users' => $users],
    message: "Users retrieved successfully",
    success: true,
    response_code: 200
);
```

### Redirects

```php
<?php
use function Gimli\Http\redirect;
use function Gimli\Http\redirect_on_success;
use function Gimli\Http\redirect_on_failure;

// Simple redirect
return redirect('/dashboard');

// Conditional redirect based on operation success
$success = $this->userService->update($user);

// Redirect only if operation was successful
return redirect_on_success('/dashboard', $success, "User updated successfully");

// Redirect only if operation failed
return redirect_on_failure('/users/edit?id=' . $user->id, $success, "Failed to update user");
```

## Integration with Routing

The Request and Response classes integrate seamlessly with Gimli's routing system:

```php
<?php
use Gimli\Router\Route;
use Gimli\Http\Request;
use Gimli\Http\Response;
use function Gimli\Http\json_response;

// Define a route with Request and Response parameters
Route::get('/api/users/:id', function(Request $Request, Response $Response, int $id) {
    $user = getUserById($id);
    
    if (!$user) {
        return json_response([], "User not found", false, 404);
    }
    
    return json_response(['user' => $user], "User retrieved");
});
```

## Best Practices

1. **Use dependency injection**: Let Gimli inject the Request and Response objects into your controllers.

2. **Return Response objects**: Always return Response objects from your controllers for consistent handling.

3. **Use helper functions**: Utilize the helper functions for common response types to keep your code clean.

4. **Set appropriate status codes**: Always set the correct HTTP status code for your responses.

5. **Validate input**: Always validate and sanitize data from the Request object before using it.

6. **Use JSON responses for APIs**: For API endpoints, consistently use JSON responses with appropriate structure.

[Docs](index.md) 