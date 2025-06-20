<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\View\Latte_Engine;
use Gimli\Environment\Config;

use function Gimli\View\render;

/**
 * @covers Gimli\View\render
 */
class View_Helpers_Test extends TestCase {

    private string $tempDir;
    private string $templateDir;
    private array $serverVars;

    protected function setUp(): void {
        // Clear any existing Application_Registry
        Application_Registry::clear();
        
        // Create temporary directories for testing
        $this->tempDir = sys_get_temp_dir() . '/view_helpers_test_' . uniqid();
        $this->templateDir = $this->tempDir . '/App/views';
        
        mkdir($this->tempDir, 0755, true);
        mkdir($this->templateDir, 0755, true);
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
        
        // Enable Latte with proper configuration
        $app->enableLatte();
        
        Application_Registry::set($app);
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

    // === RENDER HELPER FUNCTION TESTS ===

    public function testRenderFunctionWithSimpleTemplate(): void {
        $templateContent = '<h1>Hello {$name}!</h1>';
        file_put_contents($this->templateDir . '/hello.latte', $templateContent);
        
        $result = render('hello.latte', ['name' => 'World']);
        
        $this->assertEquals('<h1>Hello World!</h1>', $result);
    }

    public function testRenderFunctionWithoutData(): void {
        $templateContent = '<p>Static content</p>';
        file_put_contents($this->templateDir . '/static.latte', $templateContent);
        
        $result = render('static.latte');
        
        $this->assertEquals('<p>Static content</p>', $result);
    }

    public function testRenderFunctionWithEmptyData(): void {
        $templateContent = '<p>Empty data test</p>';
        file_put_contents($this->templateDir . '/empty.latte', $templateContent);
        
        $result = render('empty.latte', []);
        
        $this->assertEquals('<p>Empty data test</p>', $result);
    }

    public function testRenderFunctionWithComplexData(): void {
        $templateContent = '{foreach $users as $user}<div>{$user[name]}: {$user[role]}</div>{/foreach}';
        file_put_contents($this->templateDir . '/users.latte', $templateContent);
        
        $data = [
            'users' => [
                ['name' => 'Admin', 'role' => 'administrator'],
                ['name' => 'User', 'role' => 'member']
            ]
        ];
        
        $result = render('users.latte', $data);
        
        $this->assertStringContainsString('<div>Admin: administrator</div>', $result);
        $this->assertStringContainsString('<div>User: member</div>', $result);
    }

    public function testRenderFunctionResolvesLatteEngine(): void {
        $templateContent = '<span>Dependency test</span>';
        file_put_contents($this->templateDir . '/dependency.latte', $templateContent);
        
        // This test verifies that the render function properly resolves 
        // the Latte_Engine from the Application_Registry
        $result = render('dependency.latte');
        
        $this->assertEquals('<span>Dependency test</span>', $result);
    }

    public function testRenderFunctionWithSubdirectory(): void {
        $subDir = $this->templateDir . '/admin';
        mkdir($subDir, 0755, true);
        
        $templateContent = '<h2>Admin Dashboard</h2>';
        file_put_contents($subDir . '/dashboard.latte', $templateContent);
        
        $result = render('admin/dashboard.latte');
        
        $this->assertEquals('<h2>Admin Dashboard</h2>', $result);
    }

    public function testRenderFunctionWithCustomFunctions(): void {
        $templateContent = '<form>{csrf()}<input type="text" name="test"></form>';
        file_put_contents($this->templateDir . '/form.latte', $templateContent);
        
        $result = render('form.latte');
        
        $this->assertStringContainsString('<form>', $result);
        $this->assertStringContainsString('<input type=\'hidden\' name=\'csrf_token\'', $result);
        $this->assertStringContainsString('<input type="text" name="test">', $result);
        $this->assertStringContainsString('</form>', $result);
    }

    public function testRenderFunctionThrowsExceptionForNonExistentTemplate(): void {
        $this->expectException(Exception::class);
        
        render('nonexistent.latte');
    }

    public function testRenderFunctionWithSpecialCharacters(): void {
        $templateContent = '<p>{$message}</p>';
        file_put_contents($this->templateDir . '/special.latte', $templateContent);
        
        $result = render('special.latte', [
            'message' => 'Test & "quotes" <tags>'
        ]);
        
        // Should be properly escaped by Latte
        $this->assertStringContainsString('Test &amp; "quotes" &lt;tags&gt;', $result);
    }

    public function testRenderFunctionWithNestedData(): void {
        $templateContent = '<div>{$config[app][name]} v{$config[app][version]}</div>';
        file_put_contents($this->templateDir . '/nested.latte', $templateContent);
        
        $data = [
            'config' => [
                'app' => [
                    'name' => 'GimliDuck',
                    'version' => '1.0.0'
                ]
            ]
        ];
        
        $result = render('nested.latte', $data);
        
        $this->assertEquals('<div>GimliDuck v1.0.0</div>', $result);
    }

    public function testRenderFunctionWorksWithoutApplicationRegistry(): void {
        // This test ensures graceful handling if Application_Registry is not set
        Application_Registry::clear();
        
        $this->expectException(Exception::class);
        
        render('test.latte');
    }
} 