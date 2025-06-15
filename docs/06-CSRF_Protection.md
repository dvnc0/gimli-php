# CSRF Protection

Cross-Site Request Forgery (CSRF) protection is a critical security feature that helps prevent attackers from tricking users into performing unwanted actions on your application. Gimli provides built-in CSRF protection through the `Csrf` class.

## How CSRF Protection Works

1. A unique token is generated and stored in the user's session
2. The token is included in forms via a hidden field
3. When the form is submitted, the token is validated
4. If the token is invalid or missing, the request is rejected

## Security Features

The CSRF implementation includes several security features:

1. **Cryptographically Secure Tokens**: Generated using `random_bytes()` with 32 bytes of entropy
2. **Token Expiration**: Tokens expire after 15 minutes by default
3. **One-Time Use**: Tokens are deleted after successful verification
4. **Token Rotation**: Prevents token reuse attacks
5. **Token Flooding Protection**: Limits the number of tokens per session
6. **Timing-Safe Comparison**: Prevents timing attacks during verification

## Basic Usage

### Adding CSRF Protection to Forms

To protect your forms from CSRF attacks, add a hidden field with a CSRF token:

```php
<?php
use Gimli\View\Csrf;
?>

<form method="post" action="/submit">
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <!-- Form fields -->
    <button type="submit">Submit</button>
</form>
```

### Validating CSRF Tokens

When processing form submissions, validate the CSRF token:

```php
<?php
use Gimli\View\Csrf;
use Gimli\Http\Response;

class FormController {
    public function processForm(array $post_data): Response {
        // Validate CSRF token
        if (!Csrf::validateRequest($post_data)) {
            return new Response("Invalid request", 403);
        }
        
        // Process form data
        // ...
        
        return new Response("Form processed successfully");
    }
}
```

## Advanced Usage

### CSRF Protection for AJAX Requests

For AJAX requests, you can include the CSRF token in headers:

```php
<?php
// In your view or JavaScript initialization code
$csrf_token = Csrf::getToken();
?>

<script>
// Add CSRF token to all AJAX requests
const csrfToken = '<?= $csrf_token ?>';

// Using fetch API
fetch('/api/data', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(data)
});

// Using jQuery
$.ajaxSetup({
    headers: {
        'X-CSRF-Token': csrfToken
    }
});
</script>
```

On the server side, validate the token from the header:

```php
<?php
use Gimli\View\Csrf;

class ApiController {
    public function processApiRequest() {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? '';
        
        if (!Csrf::verify($token)) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'CSRF validation failed']);
            exit;
        }
        
        // Process API request
    }
}
```

### Single-Page Applications (SPAs)

For SPAs that make multiple AJAX requests, you can reuse an existing token:

```php
<?php
// In your initial page load
$csrf_token = Csrf::getToken();
?>

<script>
// Store the token in a JavaScript variable
const csrfToken = '<?= $csrf_token ?>';

// Function to make authenticated requests
function makeAuthenticatedRequest(url, method, data) {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    });
}
</script>
```

## Implementation Details

### Token Generation

Tokens are generated using cryptographically secure random bytes:

```php
$token = bin2hex(random_bytes(self::TOKEN_LENGTH)); // 32 bytes = 64 hex characters
```

### Token Storage

Tokens are stored in the session with expiration timestamps:

```php
$tokens[$token] = time() + self::TOKEN_EXPIRY; // Default 900 seconds (15 minutes)
```

### Token Verification

The verification process includes:

1. Length validation
2. Character set validation (hex only)
3. Expiration check
4. One-time use (token is deleted after verification)

### Token Flooding Protection

To prevent session bloat from too many tokens:

```php
// Maximum of 10 tokens per session
if (count($tokens) >= self::MAX_TOKENS_PER_SESSION) {
    // Remove oldest token
    $oldest_key = array_key_first($tokens);
    unset($tokens[$oldest_key]);
}
```

## Integration with Middleware

You can create a CSRF middleware to protect all routes that accept POST/PUT/PATCH/DELETE requests:

```php
<?php
use Gimli\Middleware\Middleware_Interface;
use Gimli\Middleware\Middleware_Response;
use Gimli\Http\Request;
use Gimli\View\Csrf;

class CsrfMiddleware implements Middleware_Interface {
    public function __construct(
        protected Request $Request
    ) {}
    
    public function process(): Middleware_Response {
        // Skip CSRF check for GET and HEAD requests
        if (in_array($this->Request->REQUEST_METHOD, ['GET', 'HEAD'])) {
            return new Middleware_Response(true);
        }
        
        // Check for CSRF token in POST data
        if ($this->Request->REQUEST_METHOD === 'POST' && isset($_POST['csrf_token'])) {
            if (Csrf::verify($_POST['csrf_token'])) {
                return new Middleware_Response(true);
            }
        }
        
        // Check for CSRF token in headers (for AJAX/API)
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            if (Csrf::verify($headers['X-CSRF-Token'])) {
                return new Middleware_Response(true);
            }
        }
        
        // CSRF validation failed
        return new Middleware_Response(false, '/error/csrf');
    }
}
```

Apply the middleware to routes:

```php
<?php
use Gimli\Router\Route;

// Apply to all routes in a group
Route::group('/admin', function() {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::post('/settings', [AdminController::class, 'saveSettings']);
}, [CsrfMiddleware::class]);

// Or apply to specific routes
Route::post('/login', [AuthController::class, 'login'])->addMiddleware(CsrfMiddleware::class);
```

## Best Practices

1. **Always use CSRF protection for state-changing operations**
   - All forms that modify data should include CSRF tokens
   - All non-GET API endpoints should require CSRF tokens

2. **Use the appropriate token method**
   - `Csrf::generate()` - Creates a new token for forms
   - `Csrf::getToken()` - Gets or creates a token for AJAX requests

3. **Set appropriate token expiration**
   - The default 15-minute expiration is suitable for most applications
   - For longer forms, consider using a longer expiration time

4. **Include CSRF tokens in all forms**
   - Even forms that appear to be "safe" should include CSRF tokens
   - This creates a consistent security pattern across your application

5. **Handle CSRF failures gracefully**
   - Show user-friendly error messages
   - Log CSRF failures for security monitoring

6. **Use SameSite cookies**
   - Gimli's Session class sets cookies with `SameSite=Strict` by default
   - This provides an additional layer of protection against CSRF attacks

7. **Combine with other security measures**
   - CSRF protection works best alongside XSS prevention, Content Security Policy, etc.

## Troubleshooting

### Common Issues

1. **Token Expiration**: If users take too long to submit forms, tokens may expire. Consider extending the expiration time for longer forms.

2. **Multiple Forms**: Each form generates a unique token. If a user opens multiple tabs, ensure your application handles multiple valid tokens.

3. **AJAX Polling**: For applications that make frequent AJAX requests, use `Csrf::getToken()` to reuse existing tokens when possible.

## Configuration

The CSRF protection is configured with sensible defaults:

- Token length: 32 bytes (64 hex characters)
- Token expiration: 15 minutes
- Maximum tokens per session: 10

These values are defined as constants in the `Csrf` class and can be modified if needed for your specific application requirements.

[Docs](index.md)
