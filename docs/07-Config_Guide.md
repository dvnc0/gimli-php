# Gimli Configuration Guide

The Gimli PHP framework includes a flexible configuration system through the `Config` class. This article explores the configuration options available and how to use them effectively in your applications.

## The Config Class

The `Config` class (`Gimli\Environment\Config`) manages all configuration settings for your application. It provides methods to get, set, and check configuration values, with support for dot notation to access nested settings.

## Default Configuration Options

Gimli comes with sensible defaults for all configuration options. Here's a breakdown of the main configuration categories:

### Environment Settings

```php
'is_live' => FALSE,      // Production environment flag
'is_dev' => TRUE,        // Development environment flag
'is_staging' => FALSE,   // Staging environment flag
'is_cli' => FALSE,       // Command-line interface flag
'is_unit_test' => FALSE, // Unit testing environment flag
```

These flags help determine the current environment your application is running in, allowing for environment-specific behavior.

### Database Configuration

```php
'database' => [
    'driver' => 'mysql',  // Database driver (mysql, pgsql, sqlite, etc.)
    'host' => '',         // Database host
    'database' => '',     // Database name
    'username' => '',     // Database username
    'password' => '',     // Database password
    'port' => 3306,       // Database port
],
```

These settings are used by the Database class to establish connections to your database. You'll need to configure these values based on your specific database setup.

### Routing Configuration

```php
'autoload_routes' => TRUE,           // Automatically load route files
'route_directory' => '/App/Routes/', // Directory containing route files
```

These settings control how routes are loaded:
- `autoload_routes`: When true, Gimli automatically loads route files from the specified directory
- `route_directory`: The directory where route files are located (relative to app root)

### Template Engine Configuration

```php
'enable_latte' => TRUE,               // Enable the Latte template engine
'template_base_dir' => 'App/views/',  // Base directory for templates
'template_temp_dir' => 'tmp',         // Temporary directory for compiled templates
```

These settings configure the template engine:
- `enable_latte`: Enables or disables the Latte template engine
- `template_base_dir`: The directory containing your view templates
- `template_temp_dir`: Where compiled templates are stored

### Session Configuration

```php
'session' => [
    // Security timeouts
    'regenerate_interval' => 300,        // 5 minutes - regenerate session ID
    'max_lifetime' => 7200,              // 2 hours - inactivity timeout
    'absolute_max_lifetime' => 28800,    // 8 hours - absolute maximum
    
    // Size limits
    'max_data_size' => 1048576,          // 1MB maximum session data size
    
    // Security features
    'allowed_keys_pattern' => '/^[a-zA-Z0-9._-]+$/', // Safe key pattern
    'enable_fingerprinting' => true,     // Browser fingerprinting for session validation
    'enable_ip_validation' => false,     // IP validation (disabled by default for CDN/proxy compatibility)
    
    // Cookie settings
    'cookie_httponly' => true,           // HttpOnly flag prevents JavaScript access
    'cookie_secure' => 'auto',           // Auto-detect HTTPS ('auto', true, false)
    'cookie_samesite' => 'Strict',       // SameSite attribute ('Strict', 'Lax', 'None')
    'cookie_lifetime' => 0,              // Session cookies only (0 = browser session, non-zero = seconds until expiration)
    
    // PHP session settings
    'use_strict_mode' => true,           // Strict session mode
    'use_only_cookies' => true,          // Only use cookies for sessions
    'gc_probability' => 1,               // Garbage collection probability
    'gc_divisor' => 100,                 // Garbage collection divisor
    
    // Session ID generation
    'entropy_length' => 32,              // Entropy length for session ID
    'hash_function' => 'sha256',         // Hash function for session ID
    'hash_bits_per_character' => 6,      // Bits per character in session ID
],
```

The session configuration provides extensive options for security and performance:

#### Security Timeouts
- `regenerate_interval`: How often to regenerate the session ID (helps prevent session fixation)
- `max_lifetime`: How long a session can be inactive before expiring
- `absolute_max_lifetime`: Maximum total session duration regardless of activity

#### Security Features
- `enable_fingerprinting`: Creates a fingerprint of the user's browser to validate sessions
- `enable_ip_validation`: Validates that the IP address hasn't changed (disabled by default due to CDN/proxy issues)
- `allowed_keys_pattern`: Regular expression pattern for valid session key names

#### Cookie Settings
- `cookie_httponly`: Prevents JavaScript from accessing the session cookie
- `cookie_secure`: Ensures cookies are only sent over HTTPS
- `cookie_samesite`: Controls when cookies are sent with cross-site requests
- `cookie_lifetime`: Controls cookie persistence:
  - `0`: Session cookie (expires when browser closes)
  - `604800`: Example for 1 week persistence (in seconds)
  - **Note**: For sessions to persist across browser restarts, this must be non-zero

#### Progressive Web App (PWA) Compatibility
For applications that run as PWAs or in WebView contexts, consider these settings:
- `enable_fingerprinting`: Set to `false` for better PWA compatibility
- `cookie_samesite`: Set to `'Lax'` instead of `'Strict'` for PWA contexts
- `cookie_lifetime`: Set to non-zero value (e.g., `604800` for 1 week)

#### Server-Side Session Storage
The server's `session.gc_maxlifetime` value in php.ini also affects session persistence.
If sessions expire too quickly, check:
1. Your application's `max_lifetime` setting
2. Server's PHP configuration for `session.gc_maxlifetime`

Both values should be set to the desired session duration in seconds.

### Events Configuration

```php
'events' => [], // Array of event handlers to register
```

This array can contain event handlers that will be automatically registered when the application starts.

## Using the Config Class

### Accessing Configuration Values

You can access configuration values using either property syntax or the `get()` method:

```php
// Using property syntax
$dbHost = $Config->database['host'];
$isProduction = $Config->is_live;

// Using get() method
$dbHost = $Config->get('database.host');
$isProduction = $Config->get('is_live');
```

### Checking If a Configuration Value Exists

```php
if ($Config->has('custom_setting')) {
    // Use the custom setting
}
```

### Setting Configuration Values

```php
// Set a top-level value
$Config->set('is_dev', false);

// Set a nested value
$Config->set('database.host', 'db.example.com');
```

## Configuration Helper Functions

Gimli provides helper functions in the `Gimli\Environment` namespace that allow you to access configuration values from anywhere in your application without needing direct access to the Config instance:

```php
<?php
use function Gimli\Environment\get_config;
use function Gimli\Environment\get_config_value;
use function Gimli\Environment\config_has;

// Get the entire configuration array
$allConfig = get_config();

// Get a specific configuration value (supports dot notation)
$dbHost = get_config_value('database.host');
$isProduction = get_config_value('is_live');

// Check if a configuration value exists
if (config_has('custom_setting')) {
    // Use the custom setting
}
```

These helper functions work by accessing the Config instance stored in the Application_Registry, making it convenient to use configuration values throughout your application without dependency injection.

## Loading Custom Configuration

You can load custom configuration when creating a Config instance:

```php
// Load from an array
$customConfig = [
    'is_live' => true,
    'database' => [
        'host' => 'production-db.example.com',
    ]
];
$Config = new Config($customConfig);

// Or load after creation
$Config->load($customConfig);
```

## Environment-Specific Configuration

A common pattern is to have different configuration files for different environments:

```php
// Determine environment
$env = getenv('APP_ENV') ?: 'development';

// Load base configuration
$config = require 'config/default.php';

// Load environment-specific configuration
$envConfig = require "config/{$env}.php";

// Create config instance with merged configuration
$Config = new Config(array_merge($config, $envConfig));
```

## Best Practices

1. **Never commit sensitive information**: Store sensitive data like database credentials in environment variables or a `.env` file that isn't committed to version control.

2. **Use environment-specific configurations**: Create separate configuration files for development, staging, and production.

3. **Set reasonable session timeouts**: Balance security (shorter timeouts) with user experience (longer timeouts).

4. **Enable security features**: Keep security features like HttpOnly cookies and SameSite restrictions enabled.

5. **Adjust garbage collection**: If you have many sessions, adjust the garbage collection settings to prevent performance issues.

6. **Use helper functions for simplicity**: Prefer the helper functions (`get_config_value()`, etc.) when you need to access configuration values in multiple places.

7. **Use dependency injection when possible**: For classes that need configuration values, consider injecting the Config object through the constructor.

## Conclusion

The Gimli configuration system provides a flexible and powerful way to manage your application settings. By understanding the available options and following best practices, you can create secure, efficient, and maintainable applications.

[Docs](index.md) 