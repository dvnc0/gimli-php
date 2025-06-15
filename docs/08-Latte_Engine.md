# Latte Template Engine

Gimli integrates the powerful Latte template engine to provide a clean, secure way to create views for your application. This document explains how to use the Latte engine in Gimli and the custom functions that have been added to enhance its capabilities.

## Overview

The `Latte_Engine` class (`Gimli\View\Latte_Engine`) is a wrapper around the Latte template engine that adds Gimli-specific functionality. It provides a simple interface for rendering templates and includes several custom functions to help with common tasks.

## Configuration

The Latte engine is configured through the Gimli configuration system:

```php
'enable_latte' => TRUE,               // Enable the Latte template engine
'template_base_dir' => 'App/views/',  // Base directory for templates
'template_temp_dir' => 'tmp',         // Temporary directory for compiled templates
```

## Basic Usage

To render a template:

```php
<?php
use Gimli\View\Latte_Engine;

// The Latte_Engine is typically injected via the dependency injection system
public function __construct(
    protected Latte_Engine $Latte
) {}

public function showPage(): string {
    // Render a template with data
    return $this->Latte->render('pages/home.latte', [
        'title' => 'Welcome to Gimli',
        'user' => $currentUser,
    ]);
}
```

### Using the Render Helper

Gimli provides a convenient helper function to render templates without directly injecting the Latte_Engine:

```php
<?php
// In any file, simply use the render function
use function Gimli\View\render;

// Render a template with data
$output = render('pages/home.latte', [
    'title' => 'Welcome to Gimli',
    'user' => $currentUser,
]);

// You can use it directly in route handlers
Route::get('/about', function() {
    return render('pages/about.latte', [
        'title' => 'About Us',
        'team' => $teamMembers,
    ]);
});
```

The helper function automatically resolves the Latte_Engine from the Application_Registry, making it easy to render templates from anywhere in your application.

## Custom Functions

The Gimli Latte engine adds several custom functions to make common tasks easier:

### CSRF Protection Functions

#### `{csrf()}`

Outputs a hidden input field containing a CSRF token:

```latte
<form method="post" action="/submit">
    {csrf()}
    <!-- Form fields -->
    <button type="submit">Submit</button>
</form>
```

This renders as:

```html
<form method="post" action="/submit">
    <input type="hidden" name="csrf_token" value="generated_token_here" autocomplete="off">
    <!-- Form fields -->
    <button type="submit">Submit</button>
</form>
```

#### `{csrfToken()}`

Returns a CSRF token as a string, useful for custom implementations:

```latte
<script>
    const csrfToken = {csrfToken()};
    
    // Use the token in fetch requests
    fetch('/api/data', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    });
</script>
```

#### `{csrfMeta()}`

Outputs a meta tag containing a CSRF token, useful for JavaScript frameworks:

```latte
<head>
    <title>My App</title>
    {csrfMeta()}
</head>
```

This renders as:

```html
<head>
    <title>My App</title>
    <meta name="csrf-token" content="generated_token_here">
</head>
```

### Asset Management Functions

#### `{getVue($path)}`

Loads a Vue.js component from a manifest file, useful for Single-Page Applications:

```latte
<body>
    <div id="app"></div>
    {getVue('src/main.js')}
</body>
```

This function:
1. Reads the manifest file at `/public/js/manifest.json`
2. Finds the compiled file corresponding to the specified path
3. Outputs a script tag with the correct attributes

Example output:

```html
<script src="/public/js/main.a1b2c3d4.js" type="module" defer crossorigin></script>
```

#### `{getCss($path)}`

Loads CSS files associated with a Vue.js component:

```latte
<head>
    <title>My App</title>
    {getCss('src/main.js')}
</head>
```

This function:
1. Reads the manifest file at `/public/js/manifest.json`
2. Finds any CSS files associated with the specified component
3. Outputs link tags for each CSS file

Example output:

```html
<link href="/public/js/main.a1b2c3d4.css" rel="stylesheet">
```

## Using Latte Templates

Latte templates use a syntax similar to PHP but with cleaner, more secure constructs. Here's a basic example:

```latte
{* templates/layout.latte *}
<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    {csrfMeta()}
    {getCss('src/main.js')}
</head>
<body>
    <header>
        <h1>{$title}</h1>
        {if $user}
            <p>Welcome, {$user->name}!</p>
        {else}
            <p>Please log in</p>
        {/if}
    </header>
    
    <main>
        {block content}{/block}
    </main>
    
    <footer>
        &copy; {date('Y')} Gimli Application
    </footer>
    
    {getVue('src/main.js')}
</body>
</html>
```

A page template can then extend this layout:

```latte
{* templates/pages/home.latte *}
{layout '../layout.latte'}

{block content}
    <h2>Welcome to our site!</h2>
    
    <form method="post" action="/contact">
        {csrf()}
        <div class="form-group">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <button type="submit">Submit</button>
    </form>
{/block}
```

## Best Practices

1. **Keep templates simple**: Templates should primarily handle presentation logic, not business logic.

2. **Use layouts and blocks**: Take advantage of Latte's layout system to avoid code duplication.

3. **Escape output properly**: Latte automatically escapes variables to prevent XSS attacks, but be aware of contexts where you might need custom escaping.

4. **Use CSRF protection**: Always include CSRF tokens in forms that modify data using the provided helper functions.

5. **Organize templates logically**: Consider organizing templates by feature or page type for better maintainability.

6. **Use the render helper**: For simpler code, use the `render()` helper function when you don't need direct access to the Latte_Engine instance.

For more information on Latte's features, see the [official Latte documentation](https://latte.nette.org/en/guide).

[Docs](index.md) 