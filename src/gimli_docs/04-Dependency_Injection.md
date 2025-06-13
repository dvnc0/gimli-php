# Dependency Injection

Gimli includes a powerful dependency injection container that makes it easy to manage object dependencies and promote loosely coupled, testable code. The DI container automatically resolves class dependencies and provides several ways to configure and retrieve services.

## Core Concepts

The Injector system consists of these key components:

- **Injector**: The main dependency injection container
- **Service Registration**: Manually registering existing instances
- **Service Binding**: Defining how to create service instances 
- **Service Resolution**: Automatically creating and injecting dependencies
- **Helper Functions**: Simplified access to injection features

## Basic Usage

### Accessing the Injector

You can access the Injector in several ways:

```php
<?php
// Through the Application Registry
use Gimli\Application_Registry;
$Injector = Application_Registry::get()->Injector;

// Using the injector() helper function
use function Gimli\Injector\injector;
$Injector = injector();

// Through dependency injection
class MyService {
    public function __construct(
        protected Gimli\Injector\Injector_Interface $Injector
    ) {
        // Injector is automatically provided
    }
}
```

### Resolving Services

The Injector automatically resolves dependencies:

```php
<?php
// Using the Injector directly
$Database = $Injector->resolve(Gimli\Database\Database::class);

// Using the helper function
use function Gimli\Injector\resolve;
$Database = resolve(Gimli\Database\Database::class);
```

When resolving a class, the Injector:

1. Checks if a singleton instance is already resolved
2. Checks if an instance is manually registered
3. Checks if there's a binding for the class
4. Creates a new instance and resolves its dependencies recursively

### Registering Existing Instances

If you have an existing object instance you want to make available:

```php
<?php
// Using the Injector directly
$Cache = new Cache($config);
$Injector->register(Cache::class, $Cache);

// Now any class that needs Cache will get this instance
$Service = $Injector->resolve(Service::class); // Service constructor gets $Cache
```

### Binding Service Factories

For services that need custom initialization:

```php
<?php
// Using the Injector directly
$Injector->bind(Database::class, function() use ($config) {
    return new Database(new PDO($config['dsn'], $config['user'], $config['pass']));
});

// Using the helper function
use function Gimli\Injector\bind;
bind(Logger::class, function() {
    $logger = new Logger('app');
    $logger->pushHandler(new StreamHandler('logs/app.log'));
    return $logger;
});
```

### Passing Manual Dependencies

Sometimes you need to pass specific dependencies:

```php
<?php
// Pass specific arguments when resolving
$User = $Injector->resolve(User::class, ['id' => 123]);

// Using the helper function with manual dependencies
$Post = resolve(Post::class, ['author' => $user, 'title' => 'Hello World']);
```

## Advanced Features

### Creating Fresh Instances

When you need a new instance instead of reusing a singleton:

```php
<?php
// Using the Injector directly
$newMailer = $Injector->resolveFresh(Mailer::class);

// Using the helper function
use function Gimli\Injector\resolve_fresh;
$newValidator = resolve_fresh(Validator::class, ['rules' => $customRules]);
```

### Method Injection

Call a method with automatic dependency injection:

```php
<?php
// Using the Injector directly
$result = $Injector->call(ReportGenerator::class, 'generate', ['format' => 'pdf']);

// Using the helper function
use function Gimli\Injector\call_method;
$result = call_method(ReportGenerator::class, 'generate', ['format' => 'pdf']);
```

The Injector will:
1. Resolve the class instance
2. Analyze the method parameters
3. Resolve any typehinted dependencies
4. Provide the explicitly passed arguments
5. Call the method with all dependencies

### Extending Services

Modify a service after it's been resolved:

```php
<?php
// Using the Injector directly
$Injector->extends(Router::class, function($router) {
    $router->addMiddleware(new AuthMiddleware());
    return $router;
});

// Using the helper function
use function Gimli\Injector\extend_class;
extend_class(EventManager::class, function($eventManager) {
    $eventManager->subscribe('app.start', function() {
        // Do something when app starts
    });
    return $eventManager;
});
```

## Auto-Wiring

The Gimli Injector supports automatic dependency resolution (auto-wiring). When a class is requested, the Injector examines its constructor and automatically resolves each parameter:

```php
<?php
// These classes will be automatically wired together
class UserRepository {
    public function __construct(
        protected Database $Database,
        protected CacheService $Cache
    ) {
        // Both $Database and $Cache are automatically injected
    }
}

class UserController {
    public function __construct(
        protected UserRepository $UserRepository,
        protected AuthService $Auth
    ) {
        // UserRepository with its dependencies is injected automatically
        // AuthService is injected automatically
    }
}

// Just resolve the controller, and all dependencies are handled
$controller = resolve(UserController::class);
```

## Circular Dependency Detection

The Injector detects circular dependencies and throws an exception to prevent infinite loops:

```php
// This would cause a circular dependency exception:
class A {
    public function __construct(B $b) {}
}

class B {
    public function __construct(A $a) {}
}
```

## Best Practices

1. **Use constructor injection**
   - Make dependencies explicit in your constructor
   - Avoid setter injection which can lead to partially initialized objects

2. **Use interfaces when appropriate**
   - Type-hint against interfaces rather than concrete implementations
   - Register concrete implementations for interfaces with the Injector

3. **Avoid service locator pattern**
   - Don't pass the Injector to your services just to resolve other services
   - Instead, request dependencies directly in your constructor

4. **Use bindings for complex initialization**
   - When a service needs complex setup, use a binding function

5. **Prefer singletons for stateless services**
   - The default resolution provides singletons for better performance
   - Use `resolveFresh` only when you need distinct instances

6. **Keep services focused**
   - Each service should have a single responsibility
   - Avoid "god objects" with too many dependencies 