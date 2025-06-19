# Security Guide

GimliDuck PHP Framework provides robust security features to help you build secure web applications. This guide covers the security features built into the framework and best practices for securing your application.

## Core Security Features

### Route Parameter Validation

GimliDuck implements comprehensive route parameter validation to protect against injection attacks and malformed input:

```php
<?php
// Route with validated parameters
Route::get('/products/:id', [ProductController::class, 'show']);

// In the Router class, parameters are automatically validated:
// - Length limits are enforced
// - Format validation is performed
// - Type casting is done safely
```

The framework provides several predefined parameter patterns with built-in validation:

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

### CSRF Protection

Cross-Site Request Forgery (CSRF) protection is built into the framework:

The CSRF implementation includes:
- Cryptographically secure token generation
- Token expiration (15 minutes by default)
- One-time use tokens
- Protection against token flooding
- Timing-safe comparison

### Secure Session Management

The Session class provides comprehensive security features:

```php
<?php
// Configuration in your config file
return [
    'session' => [
        'max_lifetime' => 3600,          // 1 hour inactivity timeout
        'absolute_max_lifetime' => 14400, // 4 hours absolute max
        'regenerate_interval' => 300,     // 5 minutes - regenerate session ID
        'enable_fingerprinting' => true,  // Browser fingerprinting
        'enable_ip_validation' => false,  // IP validation (disabled by default)
        'cookie_httponly' => true,        // HttpOnly cookies
        'cookie_secure' => true,          // Secure cookies
        'cookie_samesite' => 'Strict',    // SameSite policy
        'cookie_lifetime' => 0,           // 0 = session cookies, non-zero = persistent cookies
    ],
];
```

Security features include:
- Activity-based session expiration
- Absolute session lifetime limits
- Session ID regeneration
- Browser fingerprinting
- Secure cookie settings
- Protection against session fixation
- Data size limits to prevent DoS
- Automatic session initialization
- PWA compatibility features

#### PWA and Mobile App Considerations

For Progressive Web Apps (PWAs) and mobile applications using WebView:

1. Set `cookie_samesite` to `'Lax'` for better PWA compatibility
2. Set `enable_fingerprinting` to `false` or use the enhanced fingerprinting that normalizes user-agent strings
3. Set `cookie_lifetime` to a non-zero value (in seconds) for persistent sessions

```php
'session' => [
    'enable_fingerprinting' => false,  // Disable fingerprinting for PWA compatibility
    'cookie_samesite' => 'Lax',        // Less restrictive SameSite setting for PWAs
    'cookie_lifetime' => 604800,       // 1 week in seconds (persistent sessions)
],
```

The Session class provides built-in WebView detection and user-agent normalization to improve compatibility with various mobile contexts.

### Model Mass Assignment Protection

The Model class includes protection against mass assignment vulnerabilities:

```php
<?php
class User extends Model {
    // Define which fields can be mass-assigned
    protected array $fillable_fields = ['name', 'email', 'bio'];
    
    // Or define which fields are protected from mass-assignment
    protected array $guarded_fields = ['id', 'role', 'is_admin'];
}
```

The protection includes:
- Fillable/guarded field system
- Property existence checks
- Primary key protection
- Reflection-based validation

### Database Security

The Database class uses PDO with prepared statements to prevent SQL injection:

```php
<?php
// All database methods use prepared statements
$users = $Database->fetchAll("SELECT * FROM users WHERE role = ?", ['admin']);

// Even the query builder methods use prepared statements
$Database->update('users', 'id = :id', ['status' => 'active'], [':id' => 123]);
```

### Use HTTPS

Configure your web server to use HTTPS and set the appropriate headers:

```php
<?php
// Add security headers in your middleware or bootstrap file
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
```

[Home](https://dvnc0.github.io/gimli-php/)
