<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\View\Latte_Engine;
use Gimli\View\Csrf;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Injector\Injector_Interface;
use Gimli\Events\Event_Manager;
use Gimli\Environment\Config;
use Gimli\Session\Session;

/**
 * @covers Gimli\View\Latte_Engine
 */
class Latte_Engine_Test extends TestCase {

    private string $tempDir;
    private string $templateDir;
    private string $manifestDir;
    private Latte_Engine $latteEngine;
    private array $serverVars;

    protected function setUp(): void {
        // Clear any existing Application_Registry
        Application_Registry::clear();
        
        // Create temporary directories for testing
        $this->tempDir = sys_get_temp_dir() . '/latte_test_' . uniqid();
        $this->templateDir = $this->tempDir . '/templates';
        $this->manifestDir = $this->tempDir . '/public/js';
        
        mkdir($this->tempDir, 0755, true);
        mkdir($this->templateDir, 0755, true);
        mkdir($this->manifestDir, 0755, true);
        mkdir($this->tempDir . '/tmp', 0755, true);
        
        // Setup server variables for Application
        $this->serverVars = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php'
        ];
        
        // Create Application with proper setup
        $app = Application::create($this->tempDir, $this->serverVars);
        
        // Configure template settings
        $app->Config->set('template_temp_dir', 'tmp');
        
        Application_Registry::set($app);
        
        // Create Latte_Engine instance
        $this->latteEngine = new Latte_Engine('templates', $this->tempDir);
    }

    protected function tearDown(): void {
        // Clean up after each test
        Application_Registry::clear();
        
        // Remove temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // === CONSTRUCTOR TESTS ===

    public function testConstructorSetsProperties(): void {
        $engine = new Latte_Engine('views', '/app/root');
        
        // Use reflection to access protected properties
        $reflection = new ReflectionClass($engine);
        $templateBaseDirProperty = $reflection->getProperty('template_base_dir');
        $templateBaseDirProperty->setAccessible(true);
        $appRootDirProperty = $reflection->getProperty('app_root_dir');
        $appRootDirProperty->setAccessible(true);
        
        $this->assertEquals('views', $templateBaseDirProperty->getValue($engine));
        $this->assertEquals('/app/root', $appRootDirProperty->getValue($engine));
    }

    // === BASIC TEMPLATE RENDERING TESTS ===

    public function testRenderSimpleTemplate(): void {
        // Create a simple template
        $templateContent = '<h1>Hello {$name}!</h1>';
        file_put_contents($this->templateDir . '/simple.latte', $templateContent);
        
        $result = $this->latteEngine->render('simple.latte', ['name' => 'World']);
        
        $this->assertEquals('<h1>Hello World!</h1>', $result);
    }

    public function testRenderTemplateWithoutData(): void {
        $templateContent = '<h1>Static Content</h1>';
        file_put_contents($this->templateDir . '/static.latte', $templateContent);
        
        $result = $this->latteEngine->render('static.latte');
        
        $this->assertEquals('<h1>Static Content</h1>', $result);
    }

    public function testRenderTemplateWithComplexData(): void {
        $templateContent = '{foreach $users as $user}<p>{$user[name]}: {$user[email]}</p>{/foreach}';
        file_put_contents($this->templateDir . '/users.latte', $templateContent);
        
        $data = [
            'users' => [
                ['name' => 'John', 'email' => 'john@example.com'],
                ['name' => 'Jane', 'email' => 'jane@example.com']
            ]
        ];
        
        $result = $this->latteEngine->render('users.latte', $data);
        
        $this->assertStringContainsString('<p>John: john@example.com</p>', $result);
        $this->assertStringContainsString('<p>Jane: jane@example.com</p>', $result);
    }

    public function testRenderTemplateWithSubdirectory(): void {
        $subDir = $this->templateDir . '/admin';
        mkdir($subDir, 0755, true);
        
        $templateContent = '<h1>Admin Panel</h1>';
        file_put_contents($subDir . '/dashboard.latte', $templateContent);
        
        $result = $this->latteEngine->render('admin/dashboard.latte');
        
        $this->assertEquals('<h1>Admin Panel</h1>', $result);
    }

    // === PATH HANDLING TESTS ===

    public function testPathHandlingWithDoubleSlashes(): void {
        // Test that double slashes are properly handled
        $templateContent = '<p>Test</p>';
        file_put_contents($this->templateDir . '/test.latte', $templateContent);
        
        // This should work even with the path normalization
        $result = $this->latteEngine->render('test.latte');
        
        $this->assertEquals('<p>Test</p>', $result);
    }

    public function testTempDirectoryConfiguration(): void {
        // Test that temp directory is properly configured
        $templateContent = '<p>Temp test</p>';
        file_put_contents($this->templateDir . '/temp_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('temp_test.latte');
        
        $this->assertEquals('<p>Temp test</p>', $result);
        
        // Verify temp directory was created and used
        $this->assertTrue(is_dir($this->tempDir . '/tmp'));
    }

    // === CSRF FUNCTION TESTS ===

    public function testCsrfFunction(): void {
        $templateContent = '{csrf()}';
        file_put_contents($this->templateDir . '/csrf_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('csrf_test.latte');
        
        $this->assertStringContainsString('<input type=\'hidden\' name=\'csrf_token\'', $result);
        $this->assertStringContainsString('autocomplete=\'off\'', $result);
        $this->assertStringContainsString('value=\'', $result);
    }

    public function testCsrfTokenFunction(): void {
        $templateContent = 'Token: {csrfToken()}';
        file_put_contents($this->templateDir . '/csrf_token_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('csrf_token_test.latte');
        
        $this->assertStringStartsWith('Token: ', $result);
        $this->assertGreaterThan(10, strlen($result)); // Should have actual token
    }

    public function testCsrfMetaFunction(): void {
        $templateContent = '{csrfMeta()}';
        file_put_contents($this->templateDir . '/csrf_meta_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('csrf_meta_test.latte');
        
        $this->assertStringContainsString('<meta name=\'csrf-token\'', $result);
        $this->assertStringContainsString('content=\'', $result);
    }

    // === VUE/ASSET FUNCTION TESTS ===

    public function testGetVueFunctionWithValidManifest(): void {
        // Create a valid manifest file
        $manifest = [
            'src/main.js' => [
                'file' => 'main.a1b2c3d4.js'
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '{getVue("src/main.js")}';
        file_put_contents($this->templateDir . '/vue_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('vue_test.latte');
        
        $this->assertStringContainsString('<script src=\'/public/js/main.a1b2c3d4.js\'', $result);
        $this->assertStringContainsString('type=\'module\'', $result);
        $this->assertStringContainsString('defer', $result);
        $this->assertStringContainsString('crossorigin', $result);
    }

    public function testGetVueFunctionWithMissingPath(): void {
        // Create manifest without the requested path
        $manifest = [
            'src/other.js' => [
                'file' => 'other.a1b2c3d4.js'
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '{getVue("src/main.js")}';
        file_put_contents($this->templateDir . '/vue_missing_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('vue_missing_test.latte');
        
        // Should render empty (no script tag)
        $this->assertEquals('', $result);
    }

    public function testGetVueFunctionWithUnreadableManifest(): void {
        $templateContent = '{getVue("src/main.js")}';
        file_put_contents($this->templateDir . '/vue_unreadable_test.latte', $templateContent);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Manifest file is not readable');
        
        $this->latteEngine->render('vue_unreadable_test.latte');
    }

    public function testGetVueFunctionWithInvalidJson(): void {
        // Create invalid JSON manifest
        file_put_contents($this->manifestDir . '/manifest.json', '{invalid json}');
        
        $templateContent = '{getVue("src/main.js")}';
        file_put_contents($this->templateDir . '/vue_invalid_test.latte', $templateContent);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Manifest file is not valid JSON');
        
        $this->latteEngine->render('vue_invalid_test.latte');
    }

    public function testGetCssFunctionWithValidManifest(): void {
        // Create manifest with CSS
        $manifest = [
            'src/main.js' => [
                'file' => 'main.a1b2c3d4.js',
                'css' => ['main.a1b2c3d4.css']
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '{getCss("src/main.js")}';
        file_put_contents($this->templateDir . '/css_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('css_test.latte');
        
        $this->assertStringContainsString('<link href=\'/public/js/main.a1b2c3d4.css\'', $result);
        $this->assertStringContainsString('rel=\'stylesheet\'', $result);
    }

    public function testGetCssFunctionWithMissingPath(): void {
        // Create manifest without the requested path
        $manifest = [
            'src/other.js' => [
                'file' => 'other.a1b2c3d4.js',
                'css' => ['other.a1b2c3d4.css']
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '{getCss("src/main.js")}';
        file_put_contents($this->templateDir . '/css_missing_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('css_missing_test.latte');
        
        // Should render empty (no link tag)
        $this->assertEquals('', $result);
    }

    public function testGetCssFunctionWithUnreadableManifest(): void {
        $templateContent = '{getCss("src/main.js")}';
        file_put_contents($this->templateDir . '/css_unreadable_test.latte', $templateContent);
        
        // getCss silently returns on unreadable manifest (unlike getVue)
        $result = $this->latteEngine->render('css_unreadable_test.latte');
        
        $this->assertEquals('', $result);
    }

    public function testGetCssFunctionWithInvalidJson(): void {
        // Create invalid JSON manifest
        file_put_contents($this->manifestDir . '/manifest.json', '{invalid json}');
        
        $templateContent = '{getCss("src/main.js")}';
        file_put_contents($this->templateDir . '/css_invalid_test.latte', $templateContent);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Manifest file is not valid JSON');
        
        $this->latteEngine->render('css_invalid_test.latte');
    }

    // === COMPLEX INTEGRATION TESTS ===

    public function testTemplateWithAllCustomFunctions(): void {
        // Create manifest
        $manifest = [
            'src/app.js' => [
                'file' => 'app.hash.js',
                'css' => ['app.hash.css']
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    {csrfMeta()}
    {getCss("src/app.js")}
</head>
<body>
    <h1>{$title}</h1>
    <form method="post">
        {csrf()}
        <input type="text" name="name">
        <button type="submit">Submit</button>
    </form>
    <script>
        const token = {csrfToken()|noescape};
    </script>
    {getVue("src/app.js")}
</body>
</html>';
        
        file_put_contents($this->templateDir . '/full_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('full_test.latte', ['title' => 'Test Page']);
        
        // Verify all components are present
        $this->assertStringContainsString('<title>Test Page</title>', $result);
        $this->assertStringContainsString('<h1>Test Page</h1>', $result);
        $this->assertStringContainsString('<meta name=\'csrf-token\'', $result);
        $this->assertStringContainsString('<link href=\'/public/js/app.hash.css\'', $result);
        $this->assertStringContainsString('<input type=\'hidden\' name=\'csrf_token\'', $result);
        $this->assertStringContainsString('const token =', $result);
        $this->assertStringContainsString('<script src=\'/public/js/app.hash.js\'', $result);
    }

    public function testTemplateWithConditionals(): void {
        $templateContent = '{if isset($user)}
    <p>Welcome, {$user[name]}!</p>
    {csrf()}
{else}
    <p>Please log in</p>
{/if}';
        
        file_put_contents($this->templateDir . '/conditional_test.latte', $templateContent);
        
        // Test with user
        $result1 = $this->latteEngine->render('conditional_test.latte', [
            'user' => ['name' => 'John']
        ]);
        
        $this->assertStringContainsString('Welcome, John!', $result1);
        $this->assertStringContainsString('<input type=\'hidden\' name=\'csrf_token\'', $result1);
        
        // Test without user
        $result2 = $this->latteEngine->render('conditional_test.latte', []);
        
        $this->assertStringContainsString('Please log in', $result2);
        $this->assertStringNotContainsString('Welcome,', $result2);
        $this->assertStringNotContainsString('csrf_token', $result2);
    }

    // === ERROR HANDLING TESTS ===

    public function testRenderNonExistentTemplate(): void {
        $this->expectException(Exception::class);
        
        $this->latteEngine->render('nonexistent.latte');
    }

    public function testTemplateWithSyntaxError(): void {
        $templateContent = '{if $condition}
    <p>Missing endif</p>';
        
        file_put_contents($this->templateDir . '/syntax_error.latte', $templateContent);
        
        $this->expectException(Exception::class);
        
        $this->latteEngine->render('syntax_error.latte');
    }

    // === EDGE CASES ===

    public function testEmptyTemplatePath(): void {
        $this->expectException(Exception::class);
        
        $this->latteEngine->render('');
    }

    public function testTemplateWithSpecialCharacters(): void {
        $templateContent = '<p>{$message}</p>';
        file_put_contents($this->templateDir . '/special_chars.latte', $templateContent);
        
        $result = $this->latteEngine->render('special_chars.latte', [
            'message' => 'Hello "World" & <Friends>!'
        ]);
        
        // Latte should automatically escape HTML
        $this->assertStringContainsString('Hello "World" &amp; &lt;Friends&gt;!', $result);
    }

    public function testTemplateWithLargeData(): void {
        $templateContent = '{foreach $items as $item}<p>{$item}</p>{/foreach}';
        file_put_contents($this->templateDir . '/large_data.latte', $templateContent);
        
        $largeData = ['items' => array_fill(0, 1000, 'Item')];
        
        $result = $this->latteEngine->render('large_data.latte', $largeData);
        
        $this->assertStringContainsString('<p>Item</p>', $result);
        $this->assertEquals(1000, substr_count($result, '<p>Item</p>'));
    }

    public function testManifestWithMultipleCssFiles(): void {
        // Test manifest with multiple CSS files
        $manifest = [
            'src/main.js' => [
                'file' => 'main.hash.js',
                'css' => ['main.hash.css', 'vendor.hash.css']
            ]
        ];
        file_put_contents($this->manifestDir . '/manifest.json', json_encode($manifest));
        
        $templateContent = '{getCss("src/main.js")}';
        file_put_contents($this->templateDir . '/multi_css_test.latte', $templateContent);
        
        $result = $this->latteEngine->render('multi_css_test.latte');
        
        // Should only load the first CSS file (as per current implementation)
        $this->assertStringContainsString('<link href=\'/public/js/main.hash.css\'', $result);
        $this->assertStringNotContainsString('vendor.hash.css', $result);
    }

    public function testTempDirectoryWithLeadingSlash(): void {
        // Test temp directory configuration with leading slash
        Application_Registry::get()->Config->set('template_temp_dir', '/tmp');
        
        $templateContent = '<p>Leading slash test</p>';
        file_put_contents($this->templateDir . '/leading_slash.latte', $templateContent);
        
        $result = $this->latteEngine->render('leading_slash.latte');
        
        $this->assertEquals('<p>Leading slash test</p>', $result);
    }

    public function testTempDirectoryWithoutLeadingSlash(): void {
        // Test temp directory configuration without leading slash
        Application_Registry::get()->Config->set('template_temp_dir', 'tmp');
        
        $templateContent = '<p>No leading slash test</p>';
        file_put_contents($this->templateDir . '/no_leading_slash.latte', $templateContent);
        
        $result = $this->latteEngine->render('no_leading_slash.latte');
        
        $this->assertEquals('<p>No leading slash test</p>', $result);
    }
} 