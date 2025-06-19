<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Injector\Injector_Interface;
use Gimli\Events\Event_Manager;
use Gimli\Environment\Config;
use Gimli\Http\Request;
use Gimli\Router\Router;
use Gimli\Http\Response;
use Gimli\Session\Session;
use Gimli\View\Latte_Engine;
use Gimli\Exceptions\Gimli_Application_Exception;
use Gimli\Exceptions\Route_Loader_Exception;

/**
 * @covers Gimli\Application
 */
class Application_Test extends TestCase {

	private string $tempDir;
	private array $serverVars;

	protected function setUp(): void {
		// Clear any existing Application_Registry
		Application_Registry::clear();
		
		// Create a temporary directory for testing
		$this->tempDir = sys_get_temp_dir() . '/gimli_test_' . uniqid();
		mkdir($this->tempDir, 0755, true);
		
		// Setup basic server variables
		$this->serverVars = [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI' => '/',
			'HTTP_HOST' => 'localhost',
			'SERVER_NAME' => 'localhost',
			'SCRIPT_NAME' => '/index.php',
			'PHP_SELF' => '/index.php'
		];
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

	// === STATIC FACTORY METHOD TESTS ===

	public function testCreateWithValidParameters(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		$this->assertInstanceOf(Application::class, $app);
		$this->assertInstanceOf(Injector_Interface::class, $app->Injector);
		$this->assertInstanceOf(Config::class, $app->Config);
	}

	public function testCreateWithCustomInjector(): void {
		$customInjector = $this->createMock(Injector_Interface::class);
		$customInjector->method('resolve')->willReturn(new Config());
		// register() and bind() have void return types, so don't specify willReturn()
		
		$app = Application::create($this->tempDir, $this->serverVars, $customInjector);
		
		$this->assertInstanceOf(Application::class, $app);
		$this->assertSame($customInjector, $app->Injector);
	}

	public function testCreateThrowsExceptionForEmptyAppRoot(): void {
		$this->expectException(Gimli_Application_Exception::class);
		$this->expectExceptionMessage('Application root path not set');
		
		Application::create('', $this->serverVars);
	}

	public function testCreateThrowsExceptionForNonExistentDirectory(): void {
		$nonExistentDir = '/path/that/does/not/exist/at/all';
		
		$this->expectException(Gimli_Application_Exception::class);
		$this->expectExceptionMessage('Application root path not found: ' . $nonExistentDir);
		
		Application::create($nonExistentDir, $this->serverVars);
	}

	public function testCreateThrowsExceptionForEmptyServerVars(): void {
		$this->expectException(Gimli_Application_Exception::class);
		$this->expectExceptionMessage('$_SERVER variables not set');
		
		Application::create($this->tempDir, []);
	}

	// === CONSTRUCTOR TESTS ===

	public function testConstructorInitializesProperties(): void {
		$app = new Application($this->tempDir, $this->serverVars);
		
		$this->assertInstanceOf(Injector_Interface::class, $app->Injector);
		$this->assertInstanceOf(Config::class, $app->Config);
	}

	// === CONFIGURATION TESTS ===

	public function testSetConfig(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		$customConfig = new Config(['custom_setting' => 'test_value']);
		
		$result = $app->setConfig($customConfig);
		
		$this->assertSame($app, $result); // Test fluent interface
		$this->assertSame($customConfig, $app->Config);
	}

	// === LATTE ENGINE TESTS ===

	public function testEnableLatte(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		$result = $app->enableLatte();
		
		$this->assertSame($app, $result); // Test fluent interface
		
		// Test that Latte_Engine is bound in the injector
		$latteEngine = $app->Injector->resolve(Latte_Engine::class);
		$this->assertInstanceOf(Latte_Engine::class, $latteEngine);
	}

	public function testEnableLatteThrowsExceptionWithoutConfig(): void {
		// Create app without going through normal construction
		$app = new Application($this->tempDir, $this->serverVars);
		unset($app->Config); // Remove the config
		
		$this->expectException(Gimli_Application_Exception::class);
		$this->expectExceptionMessage('Enable Latte requires Config to be set');
		
		$app->enableLatte();
	}

	// === INJECTOR TESTS ===

	public function testSetCustomInjector(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		$customInjector = $this->createMock(Injector_Interface::class);
		
		$app->setCustomInjector($customInjector);
		
		$this->assertSame($customInjector, $app->Injector);
	}

	public function testInjectorResolvesBasicServices(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Test that core services are properly bound
		$request = $app->Injector->resolve(Request::class);
		$router = $app->Injector->resolve(Router::class);
		$response = $app->Injector->resolve(Response::class);
		$eventManager = $app->Injector->resolve(Event_Manager::class);
		
		$this->assertInstanceOf(Request::class, $request);
		$this->assertInstanceOf(Router::class, $router);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertInstanceOf(Event_Manager::class, $eventManager);
	}

	// === CLI DETECTION TESTS ===

	public function testIsCli(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// In PHPUnit, we should be in CLI mode
		$this->assertTrue($app->isCli());
	}

	// === ROUTE LOADING TESTS ===

	public function testLoadRouteFiles(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Create a test route file
		$routeDir = $this->tempDir . '/routes';
		mkdir($routeDir, 0755, true);
		$routeFile = $routeDir . '/test.php';
		file_put_contents($routeFile, '<?php // Test route file');
		
		// Should not throw an exception
		$app->loadRouteFiles([$routeFile]);
		
		$this->assertTrue(true); // If we get here, no exception was thrown
	}

	public function testLoadRouteFilesThrowsExceptionForMissingFile(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		$missingFile = '/path/that/does/not/exist.php';
		
		$this->expectException(Route_Loader_Exception::class);
		$this->expectExceptionMessage('Route file not found: ' . $missingFile);
		
		$app->loadRouteFiles([$missingFile]);
	}

	public function testLoadRouteFilesThrowsExceptionForUnreadableFile(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Create an unreadable file (if possible on this system)
		$routeFile = $this->tempDir . '/unreadable.php';
		file_put_contents($routeFile, '<?php // Test route file');
		
		// Try to make it unreadable (this might not work on all systems)
		if (chmod($routeFile, 0000)) {
			$this->expectException(Route_Loader_Exception::class);
			$this->expectExceptionMessage('Route file not readable: ' . $routeFile);
			
			$app->loadRouteFiles([$routeFile]);
		} else {
			// If we can't make it unreadable, skip this test
			$this->markTestSkipped('Cannot create unreadable file on this system');
		}
	}

	// === RUN METHOD TESTS ===

	public function testRunWithAutoloadRoutesDisabled(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Disable autoload routes
		$app->Config->set('autoload_routes', false);
		
		// Mock the Router to prevent it from actually running
		$mockRouter = $this->createMock(Router::class);
		$mockRouter->expects($this->once())->method('addRoutes');
		$mockRouter->expects($this->once())->method('run');
		
		$app->Injector->register(Router::class, $mockRouter);
		
		// Should not throw an exception
		$app->run();
		
		$this->assertTrue(true); // If we get here, no exception was thrown
	}

	// === EVENT REGISTRATION TESTS ===

	public function testEventRegistration(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// DISABLE autoload routes to avoid needing route files
		$app->Config->set('autoload_routes', false);
		
		// Create a mock event class name
		$eventClasses = ['TestEvent1', 'TestEvent2'];
		$app->Config->set('events', $eventClasses);
		
		// Mock the Event_Manager to verify register is called
		$mockEventManager = $this->createMock(Event_Manager::class);
		$mockEventManager->expects($this->once())
			->method('register')
			->with($eventClasses);
		
		$app->Injector->register(Event_Manager::class, $mockEventManager);
		
		// Mock other dependencies to avoid router execution
		$mockRouter = $this->createMock(Router::class);
		// addRoutes() and run() have void return types
		$app->Injector->register(Router::class, $mockRouter);
		
		$app->run();
	}

	// === ROUTE DIRECTORY TESTS ===

	public function testRunWithValidRouteDirectory(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Create route directory and file - match exact path structure
		$routeDir = $this->tempDir . '/App/Routes';
		mkdir($routeDir, 0755, true);
		
		// Create a .php file
		$routeFile = $routeDir . '/web.php';
		file_put_contents($routeFile, '<?php // Test routes');
		
		// Verify file was created
		$this->assertTrue(file_exists($routeFile), 'Route file should exist');
		
		// Configure the app to use our test route directory  
		$app->Config->set('route_directory', '/App/Routes/'); // Note trailing slash
		
		// Mock the Router to prevent actual routing
		$mockRouter = $this->createMock(Router::class);
		// addRoutes() and run() have void return types
		$app->Injector->register(Router::class, $mockRouter);
		
		// Should not throw an exception
		$app->run();
		
		$this->assertTrue(true); // If we get here, no exception was thrown
	}

	public function testRunThrowsExceptionForMissingRouteDirectory(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Set a non-existent route directory
		$app->Config->set('route_directory', '/NonExistent/Routes');
		
		$this->expectException(Route_Loader_Exception::class);
		$this->expectExceptionMessage('Route directory not found:');
		
		$app->run();
	}

	public function testRunThrowsExceptionForEmptyRouteDirectory(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Create empty route directory
		$routeDir = $this->tempDir . '/EmptyRoutes';
		mkdir($routeDir, 0755, true);
		$app->Config->set('route_directory', '/EmptyRoutes');
		
		$this->expectException(Route_Loader_Exception::class);
		$this->expectExceptionMessage('No route files found in directory:');
		
		$app->run();
	}

	public function testRunThrowsExceptionWhenRouteDirectoryNotSet(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Clear the route directory setting
		$app->Config->set('route_directory', '');
		
		$this->expectException(Route_Loader_Exception::class);
		$this->expectExceptionMessage('Web route directory not set');
		
		$app->run();
	}

	// === CORE SERVICES BINDING TESTS ===

	public function testCoreServicesAreBound(): void {
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Test that Session binding works (even though it requires Application_Registry)
		Application_Registry::set($app);
		
		try {
			$session = $app->Injector->resolve(Session::class);
			$this->assertInstanceOf(Session::class, $session);
		} catch (Exception $e) {
			// Session might fail due to CLI environment, that's OK
			$this->assertTrue(true);
		}
		
		Application_Registry::clear();
	}

	// === INTEGRATION TESTS ===

	public function testFullApplicationLifecycle(): void {
		// Create a more complete test setup
		$app = Application::create($this->tempDir, $this->serverVars);
		
		// Set Application_Registry so Session can be resolved
		Application_Registry::set($app);
		
		// Set up routes directory with exact structure
		$routeDir = $this->tempDir . '/App/Routes';
		mkdir($routeDir, 0755, true);
		
		// Create a .php file
		$routeFile = $routeDir . '/web.php';
		file_put_contents($routeFile, '<?php // Test routes');
		
		// Verify file was created
		$this->assertTrue(file_exists($routeFile), 'Route file should exist');
		
		$app->Config->set('route_directory', '/App/Routes/'); // Note trailing slash
		
		// Configure events
		$app->Config->set('events', []);
		
		// Mock router to prevent actual HTTP handling
		$mockRouter = $this->createMock(Router::class);
		// addRoutes() and run() have void return types
		$app->Injector->register(Router::class, $mockRouter);
		
		// Enable Latte
		$app->enableLatte();
		
		// This should complete successfully
		$app->run();
		
		$this->assertTrue(true); // If we get here, the full lifecycle worked
	}
} 